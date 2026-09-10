/* =============================================================
   Gestión del catálogo de productos.
   ============================================================= */
(() => {
  'use strict';

  const cuerpoTabla     = document.getElementById('tablaProductos');
  const formulario      = document.getElementById('formularioProducto');
  const errorProducto   = document.getElementById('errorProducto');
  const listaCategorias = document.getElementById('categorias');

  const dialogoEditar     = document.getElementById('dialogoEditar');
  const formularioEditar  = document.getElementById('formularioEditar');
  const errorEditar       = document.getElementById('errorEditar');

  const RUTA_ICONOS = '../assets/img/productos/';
  // Sufijo de versión: evita que el navegador siga mostrando recortes viejos.
  const VERSION = typeof VERSION_ICONOS !== 'undefined' ? `?v=${VERSION_ICONOS}` : '';

  let catalogoIconos = [];
  let catalogoIngredientes = [];

  const euros = (n) => Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
  }

  function mostrarError(elemento, mensaje) {
    elemento.textContent = mensaje;
    elemento.hidden = !mensaje;
  }

  /** Llama a la API y devuelve los datos, o null si hubo error. */
  async function api(opciones) {
    try {
      const respuesta = await fetch('../api/productos.php', opciones);
      if (respuesta.status === 401) {
        window.location.href = 'index.php';
        return null;
      }
      const datos = await respuesta.json();
      if (!datos.ok) {
        return { error: datos.error || Object.values(datos.errores || {}).join(' ') };
      }
      return datos;
    } catch {
      return { error: 'Sin conexión con el servidor.' };
    }
  }

  const enviar = (cuerpo) => api({
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...cuerpo, csrf: CSRF }),
  });

  /* ---------- Selector de iconos ----------
     Un mismo componente se usa tanto en el formulario de alta como en el
     diálogo de edición: una rejilla de miniaturas donde se elige una
     (o "Sin icono"), guardando el valor en un atributo data-* del propio
     contenedor. */

  function pintarSelectorIconos(contenedor, valorSeleccionado) {
    contenedor.dataset.valor = valorSeleccionado || '';
    contenedor.innerHTML = `
      <button type="button" class="icono-opcion icono-opcion--ninguno" data-archivo=""
              aria-pressed="${!valorSeleccionado}" title="Sin icono">
        <span>Sin<br>icono</span>
      </button>
    ` + catalogoIconos.map((icono) => `
      <button type="button" class="icono-opcion" data-archivo="${escapar(icono.archivo)}"
              aria-pressed="${valorSeleccionado === icono.archivo}" title="${escapar(icono.etiqueta)}">
        <img src="${RUTA_ICONOS}${escapar(icono.archivo)}${VERSION}" alt="" loading="lazy">
      </button>
    `).join('');
  }

  function iniciarSelectorIconos(contenedor) {
    contenedor.addEventListener('click', (evento) => {
      const boton = evento.target.closest('.icono-opcion');
      if (!boton) return;
      contenedor.querySelectorAll('.icono-opcion').forEach((b) => b.setAttribute('aria-pressed', 'false'));
      boton.setAttribute('aria-pressed', 'true');
      contenedor.dataset.valor = boton.dataset.archivo;
    });
  }

  const selectorAlta   = document.getElementById('selectorIconosAlta');
  const selectorEditar = document.getElementById('selectorIconosEditar');
  iniciarSelectorIconos(selectorAlta);
  iniciarSelectorIconos(selectorEditar);

  /* ---------- Receta (ingredientes que usa el producto) ----------
     Un mismo componente para el alta y la edición: una lista de filas
     {ingrediente, cantidad} que el propio usuario va añadiendo. */

  async function cargarIngredientes() {
    try {
      const respuesta = await fetch('../api/ingredientes.php', { headers: { Accept: 'application/json' } });
      const datos = await respuesta.json();
      catalogoIngredientes = datos.ok ? datos.ingredientes : [];
    } catch {
      catalogoIngredientes = [];
    }
  }

  function filaReceta(ingredienteIdSeleccionado, cantidad) {
    const fila = document.createElement('div');
    fila.className = 'receta__fila';
    fila.innerHTML = `
      <select class="receta__ingrediente">
        ${catalogoIngredientes.map((i) => `
          <option value="${i.id}" ${i.id === ingredienteIdSeleccionado ? 'selected' : ''}>${escapar(i.nombre)}</option>
        `).join('')}
      </select>
      <input type="number" class="receta__cantidad" min="1" step="1" value="${cantidad || 1}" aria-label="Cantidad">
      <button type="button" class="receta__quitar" aria-label="Quitar ingrediente">✕</button>
    `;
    fila.querySelector('.receta__quitar').addEventListener('click', () => fila.remove());
    return fila;
  }

  function pintarReceta(contenedor, filas) {
    contenedor.innerHTML = '';
    if (!catalogoIngredientes.length) {
      contenedor.innerHTML = '<p class="campo__ayuda">Todavía no hay ingredientes creados en <a href="stock.php">Stock</a>.</p>';
      return;
    }
    (filas || []).forEach((f) => contenedor.appendChild(filaReceta(f.ingrediente_id, f.cantidad)));
  }

  function añadirFilaReceta(contenedor) {
    if (!catalogoIngredientes.length) return;
    contenedor.querySelector('.campo__ayuda')?.remove();
    contenedor.appendChild(filaReceta(catalogoIngredientes[0].id, 1));
  }

  function leerReceta(contenedor) {
    return [...contenedor.querySelectorAll('.receta__fila')].map((fila) => ({
      ingrediente_id: Number(fila.querySelector('.receta__ingrediente').value),
      cantidad: Number(fila.querySelector('.receta__cantidad').value) || 1,
    }));
  }

  const recetaAlta    = document.getElementById('recetaAlta');
  const recetaEditar  = document.getElementById('recetaEditar');
  document.getElementById('botonAñadirIngredienteAlta').addEventListener('click', () => añadirFilaReceta(recetaAlta));
  document.getElementById('botonAñadirIngredienteEditar').addEventListener('click', () => añadirFilaReceta(recetaEditar));

  /* ---------- Listado ---------- */

  async function cargar() {
    const datos = await api({ headers: { Accept: 'application/json' } });
    if (!datos || datos.error) {
      mostrarError(errorProducto, datos?.error || 'No se ha podido cargar el catálogo.');
      return;
    }
    mostrarError(errorProducto, '');

    catalogoIconos = datos.iconos || [];
    pintarSelectorIconos(selectorAlta, selectorAlta.dataset.valor);

    cuerpoTabla.innerHTML = '';

    datos.productos.forEach((producto) => {
      const fila = document.createElement('tr');
      fila.className = producto.activo ? '' : 'fila--inactiva';
      const miniatura = producto.icono
        ? `<img class="tabla__icono" src="${RUTA_ICONOS}${escapar(producto.icono)}${VERSION}" alt="">`
        : '<span class="tabla__icono tabla__icono--vacio" aria-hidden="true">—</span>';

      const tieneReceta = producto.ingredientes && producto.ingredientes.length > 0;
      const stockTexto = (producto.stock === null
        ? '<span class="tabla__stock-ilimitado">Ilimitado</span>'
        : (producto.stock === 0
          ? '<span class="tabla__stock-agotado">Agotado</span>'
          : String(producto.stock))
      ) + (tieneReceta ? ' <span class="tabla__stock-ilimitado">(receta)</span>' : '');

      fila.innerHTML = `
        <td>${miniatura}</td>
        <td>${escapar(producto.nombre)}</td>
        <td>${escapar(producto.categoria)}</td>
        <td class="tabla__derecha">${euros(producto.precio)}</td>
        <td class="tabla__derecha">${stockTexto}</td>
        <td>
          <label class="interruptor">
            <input type="checkbox" data-accion="activar" ${producto.activo ? 'checked' : ''}>
            <span class="visualmente-oculto">Mostrar en la web</span>
          </label>
        </td>
        <td class="tabla__acciones">
          <button class="boton boton--pequeno boton--texto" data-accion="editar">Editar</button>
          <button class="boton boton--pequeno boton--texto boton--peligro" data-accion="eliminar">Borrar</button>
        </td>
      `;
      fila.dataset.id = producto.id;
      fila.dataset.nombre = producto.nombre;
      fila.dataset.categoria = producto.categoria;
      fila.dataset.precio = producto.precio;
      fila.dataset.stock = producto.stock_propio === null ? '' : producto.stock_propio;
      fila.dataset.icono = producto.icono || '';
      fila.dataset.ingredientes = JSON.stringify(producto.ingredientes || []);
      cuerpoTabla.appendChild(fila);
    });

    // Sugerencias de categoría en los formularios: las que ya están en uso
    // más una batería de categorías habituales, por si aún no hay ningún
    // producto de ese tipo.
    const categoriasSugeridas = [
      'Bocatas', 'Croasants', 'Sandwiches', 'Bebidas frías', 'Bebidas calientes',
      'Zumos y batidos', 'Dulces y bollería', 'Snacks y aperitivos', 'Fruta',
      'Menú del día', 'Ensaladas', 'Postres', 'Helados',
    ];
    const categorias = [...new Set([
      ...datos.productos.map((p) => p.categoria),
      ...categoriasSugeridas,
    ])];
    listaCategorias.innerHTML = categorias
      .map((c) => `<option value="${escapar(c)}">`)
      .join('');
  }

  /* ---------- Alta ---------- */

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const stockValor = document.getElementById('stockProducto').value.trim();

    const resultado = await enviar({
      accion:       'crear',
      nombre:       document.getElementById('nombreProducto').value.trim(),
      categoria:    document.getElementById('categoriaProducto').value.trim(),
      precio:       Number(document.getElementById('precioProducto').value),
      stock:        stockValor === '' ? null : Number(stockValor),
      icono:        selectorAlta.dataset.valor || '',
      ingredientes: leerReceta(recetaAlta),
    });

    if (resultado?.error) {
      mostrarError(errorProducto, resultado.error);
      return;
    }

    mostrarError(errorProducto, '');
    formulario.reset();
    document.getElementById('precioProducto').value = '0.00';
    pintarSelectorIconos(selectorAlta, '');
    pintarReceta(recetaAlta, []);
    document.getElementById('nombreProducto').focus();
    cargar();
  });

  /* ---------- Acciones de cada fila ---------- */

  cuerpoTabla.addEventListener('click', async (evento) => {
    const boton = evento.target.closest('button[data-accion]');
    if (!boton) return;

    const fila = boton.closest('tr');
    const id = Number(fila.dataset.id);

    if (boton.dataset.accion === 'eliminar') {
      if (!confirm(`¿Borrar "${fila.dataset.nombre}"?`)) return;

      const resultado = await enviar({ accion: 'eliminar', id });
      if (resultado?.error) { alert(resultado.error); return; }
      if (resultado?.mensaje) alert(resultado.mensaje);
      cargar();
      return;
    }

    if (boton.dataset.accion === 'editar') {
      abrirEdicion(fila);
    }
  });

  cuerpoTabla.addEventListener('change', async (evento) => {
    const casilla = evento.target.closest('input[data-accion="activar"]');
    if (!casilla) return;

    const fila = casilla.closest('tr');
    const resultado = await enviar({
      accion: 'activar',
      id: Number(fila.dataset.id),
      activo: casilla.checked,
    });

    if (resultado?.error) {
      alert(resultado.error);
      casilla.checked = !casilla.checked;
    } else {
      fila.classList.toggle('fila--inactiva', !casilla.checked);
    }
  });

  /* ---------- Edición (diálogo) ---------- */

  function abrirEdicion(fila) {
    document.getElementById('editarId').value = fila.dataset.id;
    document.getElementById('editarNombre').value = fila.dataset.nombre;
    document.getElementById('editarCategoria').value = fila.dataset.categoria;
    document.getElementById('editarPrecio').value = fila.dataset.precio;
    document.getElementById('editarStock').value = fila.dataset.stock;
    pintarSelectorIconos(selectorEditar, fila.dataset.icono);
    pintarReceta(recetaEditar, JSON.parse(fila.dataset.ingredientes || '[]'));
    mostrarError(errorEditar, '');

    if (typeof dialogoEditar.showModal === 'function') {
      dialogoEditar.showModal();
    } else {
      dialogoEditar.setAttribute('open', '');
    }
  }

  function cerrarEdicion() {
    dialogoEditar.close();
  }

  document.getElementById('botonCerrarEditar').addEventListener('click', cerrarEdicion);
  document.getElementById('botonCancelarEditar').addEventListener('click', cerrarEdicion);
  dialogoEditar.addEventListener('click', (evento) => {
    if (evento.target === dialogoEditar) cerrarEdicion();
  });

  formularioEditar.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const boton = document.getElementById('botonGuardarEditar');
    boton.disabled = true;

    const stockValor = document.getElementById('editarStock').value.trim();

    const resultado = await enviar({
      accion:       'editar',
      id:           Number(document.getElementById('editarId').value),
      nombre:       document.getElementById('editarNombre').value.trim(),
      categoria:    document.getElementById('editarCategoria').value.trim(),
      precio:       Number(document.getElementById('editarPrecio').value),
      stock:        stockValor === '' ? null : Number(stockValor),
      icono:        selectorEditar.dataset.valor || '',
      ingredientes: leerReceta(recetaEditar),
    });

    boton.disabled = false;

    if (resultado?.error) {
      mostrarError(errorEditar, resultado.error);
      return;
    }

    cerrarEdicion();
    cargar();
  });

  (async () => {
    await cargarIngredientes();
    pintarReceta(recetaAlta, []);
    cargar();
  })();

  /* ---------- Indicador de conexión ---------- */
  if (window.ConexionIndicador) {
    ConexionIndicador(document.getElementById('indicadorConexion'), {
      urlPing: '../api/ping.php',
      intervalo: 15000,
    });
  }
})();
