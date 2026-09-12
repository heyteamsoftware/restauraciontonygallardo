/* =============================================================
   Venta en mostrador: catálogo grande y táctil para crear pedidos
   manualmente desde la propia cafetería. Cada tarjeta es un botón
   grande: tocarla añade una unidad directamente, con una animación
   que "vuela" hacia el total.

   El stock que se muestra en cada tarjeta tiene en cuenta lo que ya
   hay metido en la cesta actual (no solo lo que dice el servidor):
   como varios productos pueden compartir el mismo ingrediente (p. ej.
   el mismo pan), añadir uno descuenta al momento lo que queda
   disponible también en los demás productos que lo usan, para no
   poder meter en la cesta más de lo que realmente hay.
   ============================================================= */
(() => {
  'use strict';

  const cargando   = document.getElementById('ventaCargando');
  const contenido  = document.getElementById('ventaContenido');
  const catalogo   = document.getElementById('ventaCatalogo');
  const barra      = document.getElementById('ventaBarra');
  const cestaEl    = document.getElementById('ventaCesta');
  const unidadesEl = document.getElementById('ventaUnidades');
  const totalEl    = document.getElementById('ventaTotal');
  const nombreEl   = document.getElementById('ventaNombre');
  const botonCobrar = document.getElementById('botonCobrar');
  const botonVaciar = document.getElementById('botonVaciar');
  const dialogo    = document.getElementById('dialogoConfirmacion');
  const codigoEl   = document.getElementById('ventaCodigo');
  const botonNuevaVenta = document.getElementById('botonNuevaVenta');

  const RUTA_ICONOS = '../assets/img/productos/';
  const VERSION = typeof VERSION_ICONOS !== 'undefined' ? `?v=${VERSION_ICONOS}` : '';

  const cantidades = {}; // producto_id -> cantidad en la cesta
  let productos = [];
  let disponible = {}; // clave de recurso -> unidades libres ahora mismo (ya restando la cesta)

  const euros = (n) => Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
  }

  /* ---------- Recursos que consume cada producto ----------
     Un producto con receta consume sus ingredientes (clave "ing:<id>");
     uno sin receta y con stock propio se trata como si consumiera una
     unidad de sí mismo (clave "prod:<id>"); uno sin límite no consume
     nada (no hay cuello de botella que vigilar). */

  function recursosDe(producto) {
    if (producto.ingredientes && producto.ingredientes.length) {
      return producto.ingredientes.map((i) => ({ clave: `ing:${i.ingrediente_id}`, cantidad: i.cantidad }));
    }
    if (producto.stock !== null) {
      return [{ clave: `prod:${producto.id}`, cantidad: 1 }];
    }
    return [];
  }

  /** Unidades del producto que aún se podrían añadir, según lo que queda ahora. */
  function disponiblesPara(producto) {
    const recursos = recursosDe(producto);
    if (!recursos.length) return Infinity;
    return Math.min(...recursos.map((r) => Math.floor((disponible[r.clave] ?? Infinity) / r.cantidad)));
  }

  /** Reconstruye el mapa de recursos disponibles a partir del catálogo (cesta vacía). */
  function reconstruirDisponible() {
    disponible = {};
    productos.forEach((p) => {
      recursosDe(p).forEach((r) => {
        const [tipo, id] = r.clave.split(':');
        const valor = tipo === 'ing'
          ? p.ingredientes.find((i) => i.ingrediente_id === Number(id))?.stock_ingrediente
          : p.stock;
        disponible[r.clave] = valor === null || valor === undefined ? Infinity : valor;
      });
    });
  }

  /** Aplica al mapa de disponibilidad lo que hay metido en la cesta ahora mismo. */
  function descontarCestaDeDisponible() {
    Object.entries(cantidades).forEach(([id, cantidad]) => {
      const producto = productos.find((p) => p.id === Number(id));
      if (!producto || cantidad <= 0) return;
      recursosDe(producto).forEach((r) => {
        if (disponible[r.clave] !== Infinity) disponible[r.clave] -= r.cantidad * cantidad;
      });
    });
  }

  /* ---------- Carga del catálogo ---------- */

  async function cargar() {
    try {
      const respuesta = await fetch('../api/productos.php', { headers: { Accept: 'application/json' } });
      if (respuesta.status === 401) {
        window.location.href = 'index.php';
        return;
      }
      const datos = await respuesta.json();
      if (!datos.ok) throw new Error('No se ha podido cargar el catálogo.');

      productos = datos.productos.filter((p) => p.activo);
      reconstruirDisponible();
      pintarCatalogo();
      cargando.hidden = true;
      contenido.hidden = false;
      barra.hidden = false;
    } catch (error) {
      cargando.textContent = 'No se ha podido cargar el catálogo. Recarga la página.';
      console.error(error);
    }
  }

  function pintarCatalogo() {
    const porCategoria = {};
    productos.forEach((p) => {
      (porCategoria[p.categoria] ||= []).push(p);
    });

    catalogo.innerHTML = Object.entries(porCategoria).map(([categoria, lista]) => `
      <h2 class="venta__categoria">${escapar(categoria)}</h2>
      <div class="venta__rejilla">
        ${lista.map((p) => `
          <button type="button" class="venta__producto" data-producto="${p.id}">
            <span class="venta__cantidad-insignia" id="insignia-${p.id}" hidden>0</span>
            ${p.icono ? `<img class="venta__icono" src="${RUTA_ICONOS}${encodeURIComponent(p.icono)}${VERSION}" alt="" loading="lazy">` : ''}
            <span class="venta__nombreProducto">${escapar(p.nombre)}</span>
            <span class="venta__precio">${euros(p.precio)}</span>
            <span class="venta__stock" id="stock-${p.id}"></span>
          </button>
        `).join('')}
      </div>
    `).join('');

    actualizarTodasLasTarjetas();
  }

  /** Repinta la etiqueta "Quedan X"/"Agotado" y el estado de cada tarjeta. */
  function actualizarTodasLasTarjetas() {
    productos.forEach((p) => {
      const tarjeta  = catalogo.querySelector(`.venta__producto[data-producto="${p.id}"]`);
      const etiqueta = document.getElementById(`stock-${p.id}`);
      if (!tarjeta || !etiqueta) return;

      const restante = disponiblesPara(p);
      const sinLimite = restante === Infinity;
      const agotado = !sinLimite && restante <= 0;

      etiqueta.hidden = sinLimite;
      etiqueta.textContent = agotado ? 'Agotado' : `Quedan ${restante}`;
      tarjeta.classList.toggle('venta__producto--agotado', agotado);
      tarjeta.disabled = agotado;
    });
  }

  /* ---------- Refresco del stock en vivo ----------
     Se relee el catálogo cada pocos segundos (y justo después de cobrar)
     para que, si hay varias personas vendiendo a la vez, las unidades
     realmente disponibles se mantengan al día. Se reconstruye desde cero
     con los datos del servidor y luego se vuelve a restar lo que haya
     ahora mismo en la cesta (el servidor no sabe nada de ella todavía). */

  async function refrescarStock() {
    try {
      const respuesta = await fetch('../api/productos.php', { headers: { Accept: 'application/json' } });
      if (!respuesta.ok) return;
      const datos = await respuesta.json();
      if (!datos.ok) return;

      const actualizados = datos.productos.filter((p) => p.activo);
      actualizados.forEach((actualizado) => {
        const previo = productos.find((p) => p.id === actualizado.id);
        if (previo) {
          previo.stock = actualizado.stock;
          previo.ingredientes = actualizado.ingredientes;
        }
      });

      reconstruirDisponible();
      descontarCestaDeDisponible();
      actualizarTodasLasTarjetas();
    } catch {
      /* Se reintenta en el siguiente ciclo. */
    }
  }
  setInterval(refrescarStock, 5000);

  // Al volver a esta pestaña (otra venta pudo haberse hecho mientras
  // tanto) se refresca al momento, sin esperar al siguiente ciclo.
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refrescarStock();
  });
  window.addEventListener('focus', refrescarStock);

  /* ---------- Tocar un producto = añadir una unidad ---------- */

  catalogo.addEventListener('click', (evento) => {
    const tarjeta = evento.target.closest('.venta__producto');
    if (!tarjeta || tarjeta.disabled) return;
    const id = Number(tarjeta.dataset.producto);
    añadir(id, tarjeta);
  });

  function añadir(id, tarjeta) {
    const producto = productos.find((p) => p.id === id);
    if (!producto) return;

    if (disponiblesPara(producto) <= 0) {
      tarjeta.classList.remove('venta__producto--rechazo');
      void tarjeta.offsetWidth;
      tarjeta.classList.add('venta__producto--rechazo');
      return;
    }

    cantidades[id] = (cantidades[id] || 0) + 1;
    recursosDe(producto).forEach((r) => {
      if (disponible[r.clave] !== Infinity) disponible[r.clave] -= r.cantidad;
    });

    const insignia = document.getElementById(`insignia-${id}`);
    insignia.textContent = String(cantidades[id]);
    insignia.hidden = false;

    // Pulso en la tarjeta tocada.
    tarjeta.classList.remove('venta__producto--pulso');
    void tarjeta.offsetWidth;
    tarjeta.classList.add('venta__producto--pulso');

    volarHaciaTotal(tarjeta);
    actualizarTodasLasTarjetas();
    actualizarResumen();
  }

  /** Pequeña bolita que "vuela" desde la tarjeta hasta el total, puramente decorativa. */
  function volarHaciaTotal(tarjeta) {
    const origen = tarjeta.getBoundingClientRect();
    const destino = totalEl.getBoundingClientRect();

    const bola = document.createElement('span');
    bola.className = 'venta__volador';
    bola.textContent = '+1';
    bola.style.left = `${origen.left + origen.width / 2}px`;
    bola.style.top = `${origen.top + origen.height / 2}px`;
    document.body.appendChild(bola);

    const dx = (destino.left + destino.width / 2) - (origen.left + origen.width / 2);
    const dy = (destino.top + destino.height / 2) - (origen.top + origen.height / 2);
    bola.style.setProperty('--dx', `${dx}px`);
    bola.style.setProperty('--dy', `${dy}px`);

    bola.addEventListener('animationend', () => bola.remove());
  }

  /* ---------- Resumen / cesta ---------- */

  function actualizarResumen() {
    let unidades = 0;
    let total = 0;
    const lineas = [];

    Object.entries(cantidades).forEach(([id, cantidad]) => {
      const producto = productos.find((p) => p.id === Number(id));
      if (!producto || cantidad <= 0) return;
      unidades += cantidad;
      total += cantidad * producto.precio;
      lineas.push({ id: Number(id), nombre: producto.nombre, cantidad });
    });

    unidadesEl.textContent = unidades === 1 ? '1 producto' : `${unidades} productos`;

    totalEl.classList.remove('venta__total--rebote');
    void totalEl.offsetWidth;
    totalEl.textContent = euros(total);
    totalEl.classList.add('venta__total--rebote');

    botonCobrar.disabled = unidades === 0;
    barra.classList.toggle('venta__barra--con-cesta', unidades > 0);

    cestaEl.innerHTML = lineas.map((l) => `
      <span class="venta__chip">
        ${l.cantidad}× ${escapar(l.nombre)}
        <button type="button" class="venta__chip-quitar" data-quitar="${l.id}" aria-label="Quitar una unidad de ${escapar(l.nombre)}">×</button>
      </span>
    `).join('');
  }

  /** Quita una unidad de la cesta y devuelve sus recursos al mapa disponible. */
  function quitarUnaUnidad(id) {
    const producto = productos.find((p) => p.id === id);
    if (!producto || !(cantidades[id] > 0)) return;

    cantidades[id] -= 1;
    recursosDe(producto).forEach((r) => {
      if (disponible[r.clave] !== Infinity) disponible[r.clave] += r.cantidad;
    });

    const insignia = document.getElementById(`insignia-${id}`);
    if (insignia) {
      if (cantidades[id] > 0) {
        insignia.textContent = String(cantidades[id]);
      } else {
        insignia.hidden = true;
        delete cantidades[id];
      }
    }
  }

  cestaEl.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-quitar]');
    if (!boton) return;
    quitarUnaUnidad(Number(boton.dataset.quitar));
    actualizarTodasLasTarjetas();
    actualizarResumen();
  });

  botonVaciar.addEventListener('click', () => {
    Object.keys(cantidades).map(Number).forEach((id) => {
      while (cantidades[id] > 0) quitarUnaUnidad(id);
    });
    actualizarTodasLasTarjetas();
    actualizarResumen();
  });

  /* ---------- Crear el pedido ---------- */

  botonCobrar.addEventListener('click', async () => {
    const lineas = Object.entries(cantidades)
      .filter(([, cantidad]) => cantidad > 0)
      .map(([producto_id, cantidad]) => ({ producto_id: Number(producto_id), cantidad }));
    if (!lineas.length) return;

    botonCobrar.disabled = true;
    botonCobrar.textContent = 'Creando…';

    try {
      const respuesta = await fetch('../api/crear_pedido_mostrador.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nombre: nombreEl.value.trim(), lineas, csrf: CSRF }),
      });
      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.ok) {
        alert(resultado.error || 'No se ha podido crear el pedido.');
        return;
      }

      codigoEl.textContent = resultado.codigo;
      dialogo.showModal();

      // El pedido ya ha descontado stock de verdad en el servidor: se vacía
      // la cesta local (esas unidades ya están vendidas, no "reservadas")
      // y se relee el catálogo para partir de los números reales.
      Object.keys(cantidades).forEach((id) => delete cantidades[id]);
      document.querySelectorAll('.venta__cantidad-insignia').forEach((el) => { el.hidden = true; el.textContent = '0'; });
      await refrescarStock();
    } catch (error) {
      alert('No hay conexión. Comprueba el wifi e inténtalo otra vez.');
    } finally {
      botonCobrar.textContent = 'Crear pedido';
      actualizarResumen();
    }
  });

  botonNuevaVenta.addEventListener('click', () => {
    nombreEl.value = '';
    dialogo.close();
  });

  cargar();

  /* ---------- Indicador de conexión ---------- */
  if (window.ConexionIndicador) {
    ConexionIndicador(document.getElementById('indicadorConexion'), {
      urlPing: '../api/ping.php',
      intervalo: 20000,
    });
  }
})();
