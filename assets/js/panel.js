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

    const avisoFallido = pedido.estado === 'completado' && !pedido.aviso_enviado
      ? '<p class="pedido__aviso-fallido">Sin aviso por correo</p>'
      : '';

    articulo.innerHTML = `
      <header class="pedido__cabecera">
        <span class="pedido__codigo">${escapar(pedido.codigo)}</span>
        <span class="pedido__hora">${escapar(pedido.hora)}</span>
      </header>
      <p class="pedido__alumno">${escapar(pedido.nombre)}</p>
      <p class="pedido__dni">${escapar(pedido.dni)}</p>
      <ul class="pedido__lineas">${lineas}</ul>
      ${notas}
      <p class="pedido__total">${euros(pedido.total)}</p>
      ${avisoFallido}
      <div class="pedido__acciones">${botones(pedido)}</div>
    `;
    return articulo;
  }

  function botones(pedido) {
    switch (pedido.estado) {
      case 'pendiente':
        return `<button class="boton boton--pequeno boton--principal" data-estado="en_curso">Empezar</button>
                <button class="boton boton--pequeno boton--texto" data-estado="cancelado">Cancelar</button>`;
      case 'en_curso':
        return `<button class="boton boton--pequeno boton--principal" data-estado="completado">Listo · avisar</button>
                <button class="boton boton--pequeno boton--texto" data-estado="pendiente">Volver atrás</button>`;
      default:
        return `<button class="boton boton--pequeno boton--texto" data-estado="en_curso">Reabrir</button>`;
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

  /* ---------- Consulta al servidor ---------- */

  async function refrescar() {
    try {
      const parametros = new URLSearchParams({ fecha });
      if (firma) parametros.set('firma', firma);

      const respuesta = await fetch(`../api/listar_pedidos.php?${parametros}`, {
        headers: { Accept: 'application/json' },
      });

      if (respuesta.status === 401) {
        estadoConexion.textContent = 'Sesión caducada';
        estadoConexion.className = 'estado-conexion estado-conexion--error';
        clearInterval(temporizador);
        window.location.href = 'index.php';
        return;
      }

      const datos = await respuesta.json();
      if (!datos.ok) throw new Error(datos.error || 'Respuesta inesperada');

      firma = datos.firma;
      if (!datos.sin_cambios) pintar(datos);

      estadoConexion.textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-ES');
      estadoConexion.className = 'estado-conexion';

    } catch (error) {
      estadoConexion.textContent = 'Sin conexión, reintentando…';
      estadoConexion.className = 'estado-conexion estado-conexion--error';
    }
  }

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
    } catch {
      alert('Sin conexión. El cambio no se ha guardado.');
    } finally {
      firma = null;          // fuerza el repintado en la siguiente consulta
      await refrescar();
    }
  });

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
