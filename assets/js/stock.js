/* =============================================================
   Gestión del stock de ingredientes.
   ============================================================= */
(() => {
  'use strict';

  const tablaVacio      = document.getElementById('stockVacio');
  const cuerpoTabla      = document.getElementById('tablaIngredientes');
  const formulario       = document.getElementById('formularioIngrediente');
  const errorIngrediente = document.getElementById('errorIngrediente');

  const dialogoEditar    = document.getElementById('dialogoEditarIngrediente');
  const formularioEditar = document.getElementById('formularioEditarIngrediente');
  const errorEditar      = document.getElementById('errorEditarIngrediente');

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
  }

  function mostrarError(elemento, mensaje) {
    elemento.textContent = mensaje;
    elemento.hidden = !mensaje;
  }

  async function api(opciones) {
    try {
      const respuesta = await fetch('../api/ingredientes.php', opciones);
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

  /* ---------- Listado ---------- */

  async function cargar() {
    const datos = await api({ headers: { Accept: 'application/json' } });
    if (!datos || datos.error) {
      mostrarError(errorIngrediente, datos?.error || 'No se ha podido cargar el stock.');
      return;
    }
    mostrarError(errorIngrediente, '');

    cuerpoTabla.innerHTML = '';
    tablaVacio.hidden = datos.ingredientes.length > 0;

    datos.ingredientes.forEach((ingrediente) => {
      const fila = document.createElement('tr');
      const stockTexto = ingrediente.stock === null
        ? '<span class="tabla__stock-ilimitado">Ilimitado</span>'
        : (ingrediente.stock === 0
          ? '<span class="tabla__stock-agotado">Agotado</span>'
          : String(ingrediente.stock));

      fila.innerHTML = `
        <td>${escapar(ingrediente.nombre)}</td>
        <td class="tabla__derecha">${stockTexto}</td>
        <td class="tabla__usado-en">${ingrediente.usado_en.length ? escapar(ingrediente.usado_en.join(', ')) : '<span class="tabla__stock-ilimitado">—</span>'}</td>
        <td class="tabla__acciones">
          <button class="boton boton--pequeno boton--texto" data-accion="editar">Editar</button>
          ${ingrediente.stock !== 0 ? '<button class="boton boton--pequeno boton--texto boton--peligro" data-accion="vaciar">Vaciar</button>' : ''}
          <button class="boton boton--pequeno boton--texto boton--peligro" data-accion="eliminar">Borrar</button>
        </td>
      `;
      fila.dataset.id = ingrediente.id;
      fila.dataset.nombre = ingrediente.nombre;
      fila.dataset.stock = ingrediente.stock === null ? '' : ingrediente.stock;
      cuerpoTabla.appendChild(fila);
    });
  }

  /* ---------- Alta ---------- */

  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const stockValor = document.getElementById('stockIngrediente').value.trim();
    const resultado = await enviar({
      accion: 'crear',
      nombre: document.getElementById('nombreIngrediente').value.trim(),
      stock:  stockValor === '' ? null : Number(stockValor),
    });

    if (resultado?.error) {
      mostrarError(errorIngrediente, resultado.error);
      return;
    }

    mostrarError(errorIngrediente, '');
    formulario.reset();
    document.getElementById('nombreIngrediente').focus();
    cargar();
  });

  /* ---------- Acciones de cada fila ---------- */

  cuerpoTabla.addEventListener('click', async (evento) => {
    const boton = evento.target.closest('button[data-accion]');
    if (!boton) return;

    const fila = boton.closest('tr');

    if (boton.dataset.accion === 'eliminar') {
      if (!confirm(`¿Borrar "${fila.dataset.nombre}"?`)) return;

      const resultado = await enviar({ accion: 'eliminar', id: Number(fila.dataset.id) });
      if (resultado?.error) { alert(resultado.error); return; }
      cargar();
      return;
    }

    if (boton.dataset.accion === 'vaciar') {
      if (!confirm(`¿Poner "${fila.dataset.nombre}" a 0 unidades?`)) return;

      const resultado = await enviar({ accion: 'editar', id: Number(fila.dataset.id), nombre: fila.dataset.nombre, stock: 0 });
      if (resultado?.error) { alert(resultado.error); return; }
      cargar();
      return;
    }

    if (boton.dataset.accion === 'editar') {
      document.getElementById('editarIngredienteId').value = fila.dataset.id;
      document.getElementById('editarIngredienteNombre').value = fila.dataset.nombre;
      document.getElementById('editarIngredienteStock').value = fila.dataset.stock;
      mostrarError(errorEditar, '');

      if (typeof dialogoEditar.showModal === 'function') {
        dialogoEditar.showModal();
      } else {
        dialogoEditar.setAttribute('open', '');
      }
    }
  });

  function cerrarEdicion() {
    dialogoEditar.close();
  }

  document.getElementById('botonCerrarEditarIngrediente').addEventListener('click', cerrarEdicion);
  document.getElementById('botonCancelarEditarIngrediente').addEventListener('click', cerrarEdicion);
  dialogoEditar.addEventListener('click', (evento) => {
    if (evento.target === dialogoEditar) cerrarEdicion();
  });

  formularioEditar.addEventListener('submit', async (evento) => {
    evento.preventDefault();

    const boton = document.getElementById('botonGuardarEditarIngrediente');
    boton.disabled = true;

    const stockValor = document.getElementById('editarIngredienteStock').value.trim();
    const resultado = await enviar({
      accion: 'editar',
      id:     Number(document.getElementById('editarIngredienteId').value),
      nombre: document.getElementById('editarIngredienteNombre').value.trim(),
      stock:  stockValor === '' ? null : Number(stockValor),
    });

    boton.disabled = false;

    if (resultado?.error) {
      mostrarError(errorEditar, resultado.error);
      return;
    }

    cerrarEdicion();
    cargar();
  });

  /* ---------- Vaciar todo el stock de golpe ---------- */

  document.getElementById('botonVaciarTodo').addEventListener('click', async () => {
    const filas = [...cuerpoTabla.querySelectorAll('tr')];
    if (!filas.length) return;
    if (!confirm(`¿Poner a 0 el stock de los ${filas.length} ingredientes? Los productos que los usen quedarán agotados hasta que repongas.`)) return;

    const boton = document.getElementById('botonVaciarTodo');
    boton.disabled = true;
    boton.textContent = 'Vaciando…';

    await Promise.all(filas.map((fila) => enviar({
      accion: 'editar',
      id: Number(fila.dataset.id),
      nombre: fila.dataset.nombre,
      stock: 0,
    })));

    boton.disabled = false;
    boton.textContent = 'Vaciar todo el stock';
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
