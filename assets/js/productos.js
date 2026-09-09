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

      fila.innerHTML = `
        <td>${miniatura}</td>
        <td>${escapar(producto.nombre)}</td>
        <td>${escapar(producto.categoria)}</td>
        <td class="tabla__derecha">${euros(producto.precio)}</td>
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
      fila.dataset.icono = producto.icono || '';
      cuerpoTabla.appendChild(fila);
    });

    // Sugerencias de categoría en los formularios.
    const categorias = [...new Set(datos.productos.map((p) => p.categoria))];
    listaCategorias.innerHTML = categorias
      .map((c) => `<option value="${escapar(c)}">`)
      .join('');
  }

  /* ---------- Alta ---------- */

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const resultado = await enviar({
      accion:    'crear',
      nombre:    document.getElementById('nombreProducto').value.trim(),
      categoria: document.getElementById('categoriaProducto').value.trim(),
      precio:    Number(document.getElementById('precioProducto').value),
      icono:     selectorAlta.dataset.valor || '',
    });

    if (resultado?.error) {
      mostrarError(errorProducto, resultado.error);
      return;
    }

    mostrarError(errorProducto, '');
    formulario.reset();
    document.getElementById('precioProducto').value = '0.00';
    pintarSelectorIconos(selectorAlta, '');
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
    pintarSelectorIconos(selectorEditar, fila.dataset.icono);
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

    const resultado = await enviar({
      accion:    'editar',
      id:        Number(document.getElementById('editarId').value),
      nombre:    document.getElementById('editarNombre').value.trim(),
      categoria: document.getElementById('editarCategoria').value.trim(),
      precio:    Number(document.getElementById('editarPrecio').value),
      icono:     selectorEditar.dataset.valor || '',
    });

    boton.disabled = false;

    if (resultado?.error) {
      mostrarError(errorEditar, resultado.error);
      return;
    }

    cerrarEdicion();
    cargar();
  });

  cargar();

  /* ---------- Indicador de conexión ---------- */
  if (window.ConexionIndicador) {
    ConexionIndicador(document.getElementById('indicadorConexion'), {
      urlPing: '../api/ping.php',
      intervalo: 15000,
    });
  }
})();
