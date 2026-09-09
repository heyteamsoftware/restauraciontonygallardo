/* =============================================================
   Página del alumno: contadores, validación y envío del pedido.
   ============================================================= */
(() => {
  'use strict';

  const formulario = document.getElementById('formularioPedido');
  if (!formulario) return;                 // no hay productos que pedir

  const bloquePedido   = document.getElementById('bloquePedido');
  const confirmacion   = document.getElementById('confirmacion');
  const botonEnviar    = document.getElementById('botonEnviar');
  const resumenUnidades = document.getElementById('resumenUnidades');
  const resumenTotal   = document.getElementById('resumenTotal');

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
        lineas.push({
          producto_id: Number(contador.dataset.producto),
          cantidad,
          precio: Number(contador.closest('.producto').dataset.precio),
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
    botonEnviar.disabled = unidades === 0;

    // Marca visualmente las filas con unidades elegidas.
    document.querySelectorAll('.producto').forEach((fila) => {
      const cantidad = Number(fila.querySelector('.contador__valor').value || 0);
      fila.classList.toggle('producto--elegido', cantidad > 0);
    });
  }

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

  const LETRAS_DNI = 'TRWAGMYFPDXBNJZSQVHLCKE';

  function dniValido(valor) {
    const dni = valor.replace(/[\s\-.]/g, '').toUpperCase();
    if (!/^[XYZ0-9]\d{7}[A-Z]$/.test(dni)) return false;
    const numero = dni.slice(0, 8).replace(/^X/, '0').replace(/^Y/, '1').replace(/^Z/, '2');
    return LETRAS_DNI[Number(numero) % 23] === dni.slice(-1);
  }

  function validar(datos) {
    let correcto = true;

    if (datos.nombre.length < 3) {
      mostrarError('nombre', 'Escribe tu nombre y apellidos.');
      correcto = false;
    }
    if (!dniValido(datos.dni)) {
      mostrarError('dni', 'El DNI no es válido. Revisa los números y la letra.');
      correcto = false;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(datos.email)) {
      mostrarError('email', 'Escribe un correo electrónico válido.');
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
      dni:    document.getElementById('dni').value.trim(),
      email:  document.getElementById('email').value.trim(),
      notas:  document.getElementById('notas').value.trim(),
      lineas: lineasElegidas().map(({ producto_id, cantidad }) => ({ producto_id, cantidad })),
    };

    if (!validar(datos)) {
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
        } else {
          mostrarError('general', resultado.error || 'No se ha podido enviar el pedido.');
        }
        return;
      }

      // Todo bien: se muestra el código de recogida.
      document.getElementById('codigoPedido').textContent = resultado.codigo;
      document.getElementById('emailConfirmacion').textContent = datos.email;
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
    confirmacion.hidden = true;
    bloquePedido.hidden = false;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });

  actualizarResumen();
})();
