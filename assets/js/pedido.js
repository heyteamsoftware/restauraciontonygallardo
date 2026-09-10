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

  /* ---------- Stock compartido entre productos ----------
     Varios productos pueden usar el mismo ingrediente (p. ej. el mismo
     pan). Al mover el contador de uno, hay que recalcular al momento
     cuánto queda disponible en los demás que lo comparten, para no
     poder elegir en el formulario más de lo que realmente hay. */

  const productosInfo = [...document.querySelectorAll('.producto[data-producto-id]')].map((fila) => ({
    id: Number(fila.dataset.productoId),
    fila,
    entrada: fila.querySelector('.contador__valor'),
    stockBase: fila.dataset.stock === '' ? null : Number(fila.dataset.stock),
    ingredientes: JSON.parse(fila.dataset.ingredientes || '[]'),
    maxConfigurado: Number(fila.querySelector('.contador__valor').max),
  }));

  let disponibleBase = {}; // clave de recurso -> stock del servidor (cesta vacía)

  function recursosDe(info) {
    if (info.ingredientes.length) {
      return info.ingredientes.map((i) => ({ clave: `ing:${i.ingrediente_id}`, cantidad: i.cantidad }));
    }
    if (info.stockBase !== null) {
      return [{ clave: `prod:${info.id}`, cantidad: 1 }];
    }
    return [];
  }

  function reconstruirDisponibleBase() {
    disponibleBase = {};
    productosInfo.forEach((info) => {
      recursosDe(info).forEach((r) => {
        if (r.clave.startsWith('ing:')) {
          const ingredienteId = Number(r.clave.slice(4));
          const dato = info.ingredientes.find((i) => i.ingrediente_id === ingredienteId);
          disponibleBase[r.clave] = dato?.stock_ingrediente ?? Infinity;
        } else {
          disponibleBase[r.clave] = info.stockBase ?? Infinity;
        }
      });
    });
  }
  reconstruirDisponibleBase();

  /** Recalcula, para cada producto, cuánto se puede elegir ahora mismo
   *  teniendo en cuenta lo que ya está eligiendo el propio alumno de
   *  cualquier otro producto que comparta ingrediente. */
  function recalcularDisponibilidad() {
    const disponible = { ...disponibleBase };

    productosInfo.forEach((info) => {
      const cantidad = Number(info.entrada.value || 0);
      recursosDe(info).forEach((r) => {
        if (disponible[r.clave] !== Infinity) disponible[r.clave] -= r.cantidad * cantidad;
      });
    });

    let huboCambios = false;

    productosInfo.forEach((info) => {
      const recursos = recursosDe(info);
      const cantidadActual = Number(info.entrada.value || 0);

      let techo = info.maxConfigurado;
      recursos.forEach((r) => {
        if (disponible[r.clave] === Infinity) return;
        const conPropio = disponible[r.clave] + r.cantidad * cantidadActual;
        techo = Math.min(techo, Math.floor(conPropio / r.cantidad));
      });
      techo = Math.max(0, techo);

      const etiqueta = info.fila.querySelector('.producto__stock');
      const botones  = info.fila.querySelectorAll('.contador__boton');
      const sinLimite = !recursos.length;

      info.entrada.max = techo;
      if (cantidadActual > techo) {
        info.entrada.value = String(techo);
        huboCambios = true;
      }

      if (etiqueta) {
        etiqueta.hidden = sinLimite;
        etiqueta.textContent = techo === 0 ? 'Agotado' : `Quedan ${techo}`;
      }

      const agotado = !sinLimite && techo === 0;
      info.fila.classList.toggle('producto--agotado', agotado);
      info.entrada.disabled = agotado;
      botones.forEach((b) => { b.disabled = agotado; });
    });

    if (huboCambios) actualizarResumen();
  }

  /* ---------- Contadores de unidades ---------- */

  function ajustar(entrada, delta) {
    const min = Number(entrada.min || 0);
    const max = Number(entrada.max || 99);
    const valor = Number(entrada.value || 0) + delta;
    entrada.value = String(Math.min(max, Math.max(min, valor)));
    recalcularDisponibilidad();
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
      recalcularDisponibilidad();
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
    const activo = numero === 1 ? paso1 : paso2;
    activo.classList.remove('paso--entrando');
    // Forzar reflow para poder repetir la animación aunque ya la tuviera.
    void activo.offsetWidth;
    activo.classList.add('paso--entrando');
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

    if (!/^[0-9XYZxyz][0-9]{7}[A-Za-z]$/.test(datos.dni)) {
      mostrarError('dni', 'Escribe un DNI o NIE válido, con letra (p. ej. 12345678A).');
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
      dni:    document.getElementById('dni').value.trim().toUpperCase(),
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

      // Todo bien: se muestra el nombre y el código de recogida.
      document.getElementById('confirmacionNombre').textContent = resultado.nombre;
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
    // El stock pudo cambiar con el pedido que se acaba de hacer (el propio
    // y el de cualquier otra persona pidiendo a la vez): se refresca ya,
    // sin esperar al siguiente ciclo automático.
    refrescarStock();
  });

  recalcularDisponibilidad();
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

  /* ---------- Stock en vivo ----------
     Se consulta cada pocos segundos para que, si hay varias personas
     pidiendo a la vez, el número de unidades disponibles (y el máximo que
     se puede elegir) se mantenga al día sin tener que recargar la página. */

  async function refrescarStock() {
    try {
      const respuesta = await fetch('api/stock.php', { headers: { Accept: 'application/json' } });
      const datos = await respuesta.json();
      if (!datos.ok) return;

      productosInfo.forEach((info) => {
        if (info.id in datos.stock) info.stockBase = datos.stock[info.id];
        if (info.id in datos.ingredientes) info.ingredientes = datos.ingredientes[info.id];
      });

      reconstruirDisponibleBase();
      recalcularDisponibilidad();
      actualizarResumen();
    } catch {
      /* Si falla, se reintenta en el siguiente ciclo sin avisar. */
    }
  }

  setInterval(refrescarStock, 12000);
})();
