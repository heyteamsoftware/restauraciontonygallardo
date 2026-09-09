/* =============================================================
   Panel de pedidos.
   El hosting gratuito no permite WebSockets, así que el navegador
   pregunta al servidor cada pocos segundos. Si nada ha cambiado, la
   respuesta viene vacía (se compara una "firma") y no se repinta.
   ============================================================= */
(() => {
  'use strict';

  const INTERVALO = 5000;   // milisegundos entre consultas

  const columnas = {
    pendiente:  document.getElementById('col-pendiente'),
    en_curso:   document.getElementById('col-en_curso'),
    completado: document.getElementById('col-completado'),
  };
  const cuentas = {
    pendiente:  document.getElementById('cuentaPendiente'),
    en_curso:   document.getElementById('cuentaEnCurso'),
    completado: document.getElementById('cuentaCompletado'),
  };
  const entradaFecha   = document.getElementById('fecha');
  const estadoConexion = document.getElementById('estadoConexion');
  const mensajeVacio   = document.getElementById('mensajeVacio');
  const casillaSonido  = document.getElementById('sonido');

  let firma = null;
  let fecha = FECHA_INICIAL;
  let pendientesConocidos = new Set();
  let primeraCarga = true;
  let temporizador = null;

  const euros = (n) => n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

  /* ---------- Aviso sonoro al entrar un pedido nuevo ----------
     Se genera con la Web Audio API para no depender de un archivo. */
  function pitido() {
    if (!casillaSonido.checked) return;
    try {
      const audio = new (window.AudioContext || window.webkitAudioContext)();
      const oscilador = audio.createOscillator();
      const volumen = audio.createGain();
      oscilador.connect(volumen);
      volumen.connect(audio.destination);
      oscilador.frequency.value = 880;
      volumen.gain.setValueAtTime(0.001, audio.currentTime);
      volumen.gain.exponentialRampToValueAtTime(0.25, audio.currentTime + 0.02);
      volumen.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 0.35);
      oscilador.start();
      oscilador.stop(audio.currentTime + 0.35);
    } catch {
      /* Si el navegador bloquea el audio, no pasa nada. */
    }
  }

  /* ---------- Pintado ---------- */

  function tarjetaPedido(pedido) {
    const articulo = document.createElement('article');
    articulo.className = `pedido pedido--${pedido.estado}`;
    articulo.dataset.id = pedido.id;

    const lineas = pedido.lineas
      .map((l) => `<li><b>${l.cantidad}×</b> ${escapar(l.nombre)}</li>`)
      .join('');

    const notas = pedido.notas
      ? `<p class="pedido__notas">📝 ${escapar(pedido.notas)}</p>`
      : '';

    // Solo se avisa del fallo si dejó un correo y aun así no se pudo enviar;
    // si no dejó correo, no haber avisado es lo esperado, no un error.
    const avisoFallido = pedido.estado === 'completado' && !pedido.aviso_enviado && pedido.email
      ? '<p class="pedido__aviso-fallido">No se pudo avisar por correo</p>'
      : '';

    articulo.innerHTML = `
      <header class="pedido__cabecera">
        <span class="pedido__codigo">${escapar(pedido.codigo)}</span>
        <span class="pedido__hora">${escapar(pedido.hora)}</span>
      </header>
      <p class="pedido__alumno">${escapar(pedido.nombre)}</p>
      ${pedido.email ? `<p class="pedido__email">${escapar(pedido.email)}</p>` : ''}
      <ul class="pedido__lineas">${lineas}</ul>
      ${notas}
      <p class="pedido__total">${euros(pedido.total)}</p>
      ${avisoFallido}
      <div class="pedido__acciones">${botones(pedido)}</div>
    `;
    return articulo;
  }

  function botones(pedido) {
    const borrar = `<button class="boton boton--pequeno boton--texto boton--peligro" data-accion="borrar">Borrar</button>`;

    switch (pedido.estado) {
      case 'pendiente':
        return `<button class="boton boton--pequeno boton--principal" data-estado="en_curso">Empezar</button>
                <button class="boton boton--pequeno boton--texto" data-estado="cancelado">Cancelar</button>
                ${borrar}`;
      case 'en_curso':
        return `<button class="boton boton--pequeno boton--principal" data-estado="completado">Listo · avisar</button>
                <button class="boton boton--pequeno boton--texto" data-estado="pendiente">Volver atrás</button>
                ${borrar}`;
      default:
        return `<button class="boton boton--pequeno boton--texto" data-estado="en_curso">Reabrir</button>
                ${borrar}`;
    }
  }

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
  }

  function pintar(datos) {
    Object.values(columnas).forEach((columna) => { columna.innerHTML = ''; });

    datos.pedidos.forEach((pedido) => {
      const columna = columnas[pedido.estado];
      if (columna) columna.appendChild(tarjetaPedido(pedido));
    });

    cuentas.pendiente.textContent  = datos.resumen.pendiente;
    cuentas.en_curso.textContent   = datos.resumen.en_curso;
    cuentas.completado.textContent = datos.resumen.completado;

    mensajeVacio.hidden = datos.pedidos.length > 0;

    // Sonido solo con pedidos pendientes que no habíamos visto antes.
    const pendientesAhora = new Set(
      datos.pedidos.filter((p) => p.estado === 'pendiente').map((p) => p.id)
    );
    if (!primeraCarga) {
      const hayNuevos = [...pendientesAhora].some((id) => !pendientesConocidos.has(id));
      if (hayNuevos) pitido();
    }
    pendientesConocidos = pendientesAhora;
    primeraCarga = false;
  }

  /* ---------- Indicador de conexión ----------
     Combina la red del dispositivo (navigator.onLine) con el resultado
     real de cada consulta al servidor: puede haber wifi y aun así el
     servidor no responder (o al revés, un falso "sin red" del navegador). */

  function pintarConexion(estado, texto) {
    estadoConexion.className = 'estado-conexion indicador-conexion indicador-conexion--' + estado;
    estadoConexion.innerHTML = '<span class="indicador-conexion__punto" aria-hidden="true"></span><span>' + texto + '</span>';
  }

  window.addEventListener('offline', () => pintarConexion('error', 'Sin red'));
  window.addEventListener('online', refrescar);

  /* ---------- Consulta al servidor ---------- */

  async function refrescar() {
    if (!navigator.onLine) {
      pintarConexion('error', 'Sin red');
      return;
    }

    try {
      const parametros = new URLSearchParams({ fecha });
      if (firma) parametros.set('firma', firma);

      const respuesta = await fetch(`../api/listar_pedidos.php?${parametros}`, {
        headers: { Accept: 'application/json' },
      });

      if (respuesta.status === 401) {
        pintarConexion('error', 'Sesión caducada');
        clearInterval(temporizador);
        window.location.href = 'index.php';
        return;
      }

      const datos = await respuesta.json();
      if (!datos.ok) throw new Error(datos.error || 'Respuesta inesperada');

      firma = datos.firma;
      if (!datos.sin_cambios) pintar(datos);

      pintarConexion('ok', 'Actualizado ' + new Date().toLocaleTimeString('es-ES'));

    } catch (error) {
      pintarConexion('error', 'Sin conexión, reintentando…');
    }
  }

  /* ---------- Borrar pedido (disponible en cualquier estado) ---------- */

  document.querySelector('.tablero').addEventListener('click', async (evento) => {
    const botonBorrar = evento.target.closest('button[data-accion="borrar"]');
    if (!botonBorrar) return;

    const tarjeta = botonBorrar.closest('.pedido');
    const id = Number(tarjeta.dataset.id);
    const codigo = tarjeta.querySelector('.pedido__codigo')?.textContent || '';

    if (!confirm(`¿Borrar el pedido ${codigo}? No se puede deshacer.`)) return;

    tarjeta.classList.add('pedido--ocupado');
    tarjeta.querySelectorAll('button').forEach((b) => { b.disabled = true; });

    try {
      const respuesta = await fetch('../api/eliminar_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, csrf: CSRF }),
      });
      const datos = await respuesta.json();

      if (!datos.ok) {
        alert(datos.error || 'No se ha podido borrar el pedido.');
        tarjeta.classList.remove('pedido--ocupado');
        tarjeta.querySelectorAll('button').forEach((b) => { b.disabled = false; });
      }
    } catch {
      alert('Sin conexión. El pedido no se ha borrado.');
      tarjeta.classList.remove('pedido--ocupado');
      tarjeta.querySelectorAll('button').forEach((b) => { b.disabled = false; });
    } finally {
      firma = null;
      await refrescar();
    }
  });

  /* ---------- Cambio de estado ---------- */

  document.querySelector('.tablero').addEventListener('click', async (evento) => {
    const boton = evento.target.closest('button[data-estado]');
    if (!boton) return;

    const tarjeta = boton.closest('.pedido');
    const id = Number(tarjeta.dataset.id);
    const nuevoEstado = boton.dataset.estado;

    if (nuevoEstado === 'cancelado' && !confirm('¿Cancelar este pedido?')) return;

    tarjeta.classList.add('pedido--ocupado');
    tarjeta.querySelectorAll('button').forEach((b) => { b.disabled = true; });

    try {
      const respuesta = await fetch('../api/cambiar_estado.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, estado: nuevoEstado, csrf: CSRF }),
      });
      const datos = await respuesta.json();

      if (!datos.ok) {
        alert(datos.error || 'No se ha podido cambiar el estado.');
      } else if (datos.aviso === 'fallido') {
        alert('El pedido se ha marcado como listo, pero no se ha podido enviar el correo. Avisa al alumno de viva voz.');
      }

      // El aviso por correo y el registro en el Sheet los hace este
      // navegador, no el servidor (ver más abajo).
      if (datos.ok && datos.registro) {
        procesarRegistro(datos.registro);
      }
    } catch {
      alert('Sin conexión. El cambio no se ha guardado.');
    } finally {
      firma = null;          // fuerza el repintado en la siguiente consulta
      await refrescar();
    }
  });

  /* ---------- Aviso por correo y registro en Google Sheets ----------
     Ambos se hacen llamando directamente al Apps Script desde este
     navegador: el servidor PHP no puede alcanzar script.google.com desde
     InfinityFree, pero el navegador del panel sí. El truco de usar
     Content-Type: text/plain evita el preflight CORS — Apps Script
     igualmente lee el cuerpo como JSON. */

  async function llamarAppsScript(accion, datosExtra) {
    const respuesta = await fetch(HOJA_WEBHOOK, {
      method: 'POST',
      headers: { 'Content-Type': 'text/plain;charset=utf-8' },
      body: JSON.stringify({ action: accion, password: HOJA_PASSWORD, ...datosExtra }),
    });
    return respuesta.json();
  }

  async function procesarRegistro(registro) {
    if (!HOJA_WEBHOOK) return; // Apps Script no configurado: se omite en silencio

    const datosPedido = {
      codigo: registro.codigo,
      nombre: registro.nombre,
      email: registro.email,
      productos: registro.productos,
      notas: registro.notas,
      total: registro.total,
    };

    if (registro.necesitaHoja) {
      try {
        const resultado = await llamarAppsScript('registrarPedido', datosPedido);
        if (resultado.ok) {
          await confirmarAlServidor('../api/marcar_registrado_hoja.php', registro.id);
        } else {
          console.error('No se pudo registrar en la hoja:', resultado.error);
        }
      } catch (error) {
        console.error('Error al registrar en la hoja:', error);
      }
    }

    if (registro.necesitaAviso) {
      try {
        const resultado = await llamarAppsScript('email', {
          to: registro.email,
          asunto: `Tu pedido ${registro.codigo} ya está listo`,
          cuerpo: textoAvisoCorreo(registro),
        });
        if (resultado.ok) {
          await confirmarAlServidor('../api/marcar_aviso_enviado.php', registro.id);
        } else {
          console.error('No se pudo enviar el aviso por correo:', resultado.error);
          alert('El pedido se ha marcado como listo, pero no se ha podido enviar el correo. Avisa al alumno de viva voz.');
        }
      } catch (error) {
        console.error('Error al enviar el aviso por correo:', error);
        alert('El pedido se ha marcado como listo, pero no se ha podido enviar el correo. Avisa al alumno de viva voz.');
      }
    }
  }

  function textoAvisoCorreo(registro) {
    return `Hola ${registro.nombre},\n\n`
      + `Tu pedido está listo para recoger. Enseña este código en la cafetería:\n\n`
      + `  ${registro.codigo}\n\n`
      + `Pedido: ${registro.productos}\n`
      + `Total: ${euros(registro.total)}`
      + (registro.notas ? `\n\nNotas: ${registro.notas}` : '');
  }

  async function confirmarAlServidor(url, id) {
    // Para no reintentarlo si el pedido se reabre y se vuelve a completar.
    await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, csrf: CSRF }),
    });
  }

  /* ---------- Cambio de día ---------- */

  entradaFecha.addEventListener('change', () => {
    fecha = entradaFecha.value || new Date().toISOString().slice(0, 10);
    firma = null;
    primeraCarga = true;
    refrescar();
  });

  /* ---------- Arranque ---------- */

  refrescar();
  temporizador = setInterval(refrescar, INTERVALO);

  // Al volver a la pestaña se refresca de inmediato.
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) refrescar();
  });
})();
