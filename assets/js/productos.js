/* =============================================================
   Gestión del catálogo de productos.
   ============================================================= */
(() => {
  'use strict';

  const cuerpoTabla   = document.getElementById('tablaProductos');
  const formulario    = document.getElementById('formularioProducto');
  const errorProducto = document.getElementById('errorProducto');
  const listaCategorias = document.getElementById('categorias');

  const euros = (n) => Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
  }

  function mostrarError(mensaje) {
    errorProducto.textContent = mensaje;
    errorProducto.hidden = !mensaje;
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
        mostrarError(datos.error || Object.values(datos.errores || {}).join(' '));
        return null;
      }
      mostrarError('');
      return datos;
    } catch {
      mostrarError('Sin conexión con el servidor.');
      return null;
    }
  }

  const enviar = (cuerpo) => api({
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ...cuerpo, csrf: CSRF }),
  });

  /* ---------- Listado ---------- */

  async function cargar() {
    const datos = await api({ headers: { Accept: 'application/json' } });
    if (!datos) return;

    cuerpoTabla.innerHTML = '';

    datos.productos.forEach((producto) => {
      const fila = document.createElement('tr');
      fila.className = producto.activo ? '' : 'fila--inactiva';
      fila.innerHTML = `
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
      cuerpoTabla.appendChild(fila);
    });

    // Sugerencias de categoría en el formulario de alta.
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
    });

    if (resultado) {
      formulario.reset();
      document.getElementById('precioProducto').value = '0.00';
      document.getElementById('nombreProducto').focus();
      cargar();
    }
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
      if (resultado?.mensaje) alert(resultado.mensaje);
      if (resultado) cargar();
      return;
    }

    if (boton.dataset.accion === 'editar') {
      const nombre = prompt('Nombre del producto:', fila.dataset.nombre);
      if (nombre === null) return;

      const categoria = prompt('Categoría:', fila.dataset.categoria);
      if (categoria === null) return;

      const precio = prompt('Precio en euros (usa punto decimal):', fila.dataset.precio);
      if (precio === null) return;

      const resultado = await enviar({
        accion: 'editar',
        id,
        nombre: nombre.trim(),
        categoria: categoria.trim(),
        precio: Number(String(precio).replace(',', '.')),
      });
      if (resultado) cargar();
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

    if (resultado) {
      fila.classList.toggle('fila--inactiva', !casilla.checked);
    } else {
      casilla.checked = !casilla.checked;   // se deshace si falló
    }
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
