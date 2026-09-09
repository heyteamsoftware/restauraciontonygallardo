/* =============================================================
   Página del alumno: contadores, paso 1/2, validación y envío.
   ============================================================= */
(() => {
  'use strict';

  const formulario = document.getElementById('formularioPedido');
  if (!formulario) return;                 // no hay productos que pedir

  const bloquePedido    = document.getElementById('bloquePedido');
  const confirmacion    = document.getElementById('confirmacion');
  const paso1           = document.getElementById('paso1');
  const paso2           = document.getElementById('paso2');
  const botonSiguiente  = document.getElementById('botonSiguiente');
  const botonVolver     = document.getElementById('botonVolver');
  const botonEnviar     = document.getElementById('botonEnviar');
  const resumenUnidades = document.getElementById('resumenUnidades');
  const resumenTotal    = document.getElementById('resumenTotal');
  const resumenPedido   = document.getElementById('resumenPedido');

  const euros = (n) => n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

  /* ---------- Contadores de unidades ---------- */

  function ajustar(entrada, delta) {
    const min = Number(entrada.min || 0);
    const max = Number(entrada.max || 99);
    const valor = Number(entrada.value || 0) + delta;
    entrada.value = String(Math.min(max, Math.max(min, valor)));
    actualizarResumen();
  }

  document.querySelectorAll('.contador').forEach((contador) => {
    const entrada = contador.querySelector('.contador__valor');

    contador.querySelectorAll('.contador__boton').forEach((boton) => {
      boton.addEventListener('click', () => {
        ajustar(entrada, boton.dataset.accion === 'sumar' ? 1 : -1);
      });
    });

    // Si se teclea a mano, se sanea el valor.
    entrada.addEventListener('input', () => {
      const limpio = entrada.value.replace(/\D/g, '');
      entrada.value = limpio === '' ? '0' : String(Math.min(Number(entrada.max), Number(limpio)));
      actualizarResumen();
    });
  });

  function lineasElegidas() {
    const lineas = [];
    document.querySelectorAll('.contador').forEach((contador) => {
      const cantidad = Number(contador.querySelector('.contador__valor').value || 0);
      if (cantidad > 0) {
        const fila = contador.closest('.producto');
        lineas.push({
          producto_id: Number(contador.dataset.producto),
          cantidad,
          precio: Number(fila.dataset.precio),
          nombre: fila.querySelector('.producto__nombre').textContent,
        });
      }
    });
    return lineas;
  }

  function actualizarResumen() {
    const lineas = lineasElegidas();
    const unidades = lineas.reduce((suma, l) => suma + l.cantidad, 0);
    const total = lineas.reduce((suma, l) => suma + l.cantidad * l.precio, 0);

    resumenUnidades.textContent = unidades === 1 ? '1 producto' : `${unidades} productos`;
    resumenTotal.textContent = euros(total);
    botonSiguiente.disabled = unidades === 0;

    // Marca visualmente las filas con unidades elegidas.
    document.querySelectorAll('.producto').forEach((fila) => {
      const cantidad = Number(fila.querySelector('.contador__valor').value || 0);
      fila.classList.toggle('producto--elegido', cantidad > 0);
    });
  }

  function pintarResumenPaso2() {
    const lineas = lineasElegidas();
    const total = lineas.reduce((suma, l) => suma + l.cantidad * l.precio, 0);

    resumenPedido.innerHTML = lineas
      .map((l) => `<div class="resumen-pedido__linea"><span>${l.cantidad}× ${escapar(l.nombre)}</span><span>${euros(l.cantidad * l.precio)}</span></div>`)
      .join('') + `<div class="resumen-pedido__total"><span>Total</span><span>${euros(total)}</span></div>`;
  }

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
  }

  /* ---------- Navegación entre pasos ---------- */

  function irAPaso(numero) {
    if (numero === 2) pintarResumenPaso2();
    paso1.hidden = numero !== 1;
    paso2.hidden = numero !== 2;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  botonSiguiente.addEventListener('click', () => {
    limpiarErrores();
    if (lineasElegidas().length === 0) {
      mostrarError('productos', 'Elige al menos un producto.');
      return;
    }
    irAPaso(2);
  });

  botonVolver.addEventListener('click', () => irAPaso(1));

  /* ---------- Errores ---------- */

  function mostrarError(campo, mensaje) {
    const parrafo = document.getElementById(`error-${campo}`);
    if (!parrafo) return;
    parrafo.textContent = mensaje;
    parrafo.hidden = false;
    document.getElementById(campo)?.setAttribute('aria-invalid', 'true');
  }

  function limpiarErrores() {
    document.querySelectorAll('.campo__error').forEach((p) => {
      p.hidden = true;
      p.textContent = '';
    });
    formulario.querySelectorAll('[aria-invalid]').forEach((c) => c.removeAttribute('aria-invalid'));
  }

  /* ---------- Validación en el navegador ----------
     El servidor vuelve a validarlo todo; esto es solo para dar
     una respuesta inmediata al alumno.                          */

  function validar(datos) {
    let correcto = true;

    if (datos.nombre.length < 3) {
      mostrarError('nombre', 'Escribe tu nombre y apellidos.');
      correcto = false;
    }
    // El correo es opcional: solo se valida el formato si se ha rellenado.
    if (datos.email !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(datos.email)) {
      mostrarError('email', 'Ese correo no parece válido. Corrígelo o déjalo en blanco.');
      correcto = false;
    }
    if (datos.lineas.length === 0) {
      mostrarError('productos', 'Elige al menos un producto.');
      correcto = false;
    }

    return correcto;
  }

  /* ---------- Envío ---------- */

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    limpiarErrores();

    const datos = {
      nombre: document.getElementById('nombre').value.trim(),
      email:  document.getElementById('email').value.trim(),
      notas:  document.getElementById('notas').value.trim(),
      lineas: lineasElegidas().map(({ producto_id, cantidad }) => ({ producto_id, cantidad })),
    };

    if (!validar(datos)) {
      if (datos.lineas.length === 0) irAPaso(1);
      document.querySelector('.campo__error:not([hidden])')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    botonEnviar.disabled = true;
    botonEnviar.textContent = 'Enviando…';

    try {
      const respuesta = await fetch('api/crear_pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(datos),
      });
      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.ok) {
        if (resultado.errores) {
          Object.entries(resultado.errores).forEach(([campo, mensaje]) => mostrarError(campo, mensaje));
          if (resultado.errores.productos) irAPaso(1);
        } else {
          mostrarError('general', resultado.error || 'No se ha podido enviar el pedido.');
        }
        return;
      }

      // Todo bien: se muestra el código de recogida.
      document.getElementById('codigoPedido').textContent = resultado.codigo;
      document.getElementById('confirmacionTexto').textContent = datos.email
        ? `Te avisaremos a ${datos.email} en cuanto esté preparado.`
        : 'Pásate por la cafetería de vez en cuando: al no dejar un correo, no podemos avisarte.';
      bloquePedido.hidden = true;
      confirmacion.hidden = false;
      window.scrollTo({ top: 0, behavior: 'smooth' });

    } catch (error) {
      mostrarError('general', 'No hay conexión. Comprueba el wifi e inténtalo otra vez.');
    } finally {
      botonEnviar.disabled = false;
      botonEnviar.textContent = 'Enviar pedido';
    }
  });

  document.getElementById('botonOtroPedido')?.addEventListener('click', () => {
    formulario.reset();
    document.querySelectorAll('.contador__valor').forEach((e) => { e.value = '0'; });
    limpiarErrores();
    actualizarResumen();
    irAPaso(1);
    confirmacion.hidden = true;
    bloquePedido.hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  actualizarResumen();

  /* ---------- Política de privacidad ---------- */
  const botonPrivacidad = document.getElementById('botonPrivacidad');
  const dialogoPrivacidad = document.getElementById('dialogoPrivacidad');

  botonPrivacidad?.addEventListener('click', () => {
    if (typeof dialogoPrivacidad.showModal === 'function') {
      dialogoPrivacidad.showModal();
    } else {
      // Navegadores muy antiguos sin soporte de <dialog>: se muestra igual.
      dialogoPrivacidad.setAttribute('open', '');
    }
  });

  // Cerrar al hacer clic fuera del cuadro (en el fondo oscuro).
  dialogoPrivacidad?.addEventListener('click', (evento) => {
    if (evento.target === dialogoPrivacidad) {
      dialogoPrivacidad.close();
    }
  });

  /* ---------- Indicador de conexión ---------- */
  if (window.ConexionIndicador) {
    ConexionIndicador(document.getElementById('indicadorConexion'), {
      intervalo: 15000,
      textoConectado: 'En línea',
    });
  }
})();
