/* =============================================================
   Panel de Analítica: métricas, gráficos (Chart.js) y recomendaciones.
   ============================================================= */
(() => {
  'use strict';

  const cargando  = document.getElementById('analiticaCargando');
  const vacio     = document.getElementById('analiticaVacio');
  const contenido = document.getElementById('analiticaContenido');
  const filtros   = document.getElementById('filtrosRango');

  const COLORES_CATEGORIA = {
    estrella:             { color: '#c8952f', fondo: '#fbf1de' },
    flojo:                { color: '#b8621b', fondo: '#fbeade' },
    sin_ventas:            { color: '#a63428', fondo: '#fbe6e3' },
    categoria_dominante:   { color: '#1f3d2b', fondo: '#e3ebe5' },
    categoria_floja:       { color: '#7a7a70', fondo: '#ececea' },
    hora_pico:             { color: '#1f5d8c', fondo: '#e2edf5' },
    dia_top:               { color: '#2d7a45', fondo: '#e2f0e6' },
    ticket_medio:          { color: '#8a4fae', fondo: '#f0e7f6' },
    tendencia_sube:        { color: '#2d7a45', fondo: '#e2f0e6' },
    tendencia_baja:        { color: '#a63428', fondo: '#fbe6e3' },
    general:               { color: '#5f5f56', fondo: '#efece5' },
  };

  // Paleta para los gráficos (categórica, coherente con el resto del sitio).
  const PALETA_GRAFICOS = ['#1f3d2b', '#c8952f', '#1f5d8c', '#a63428', '#2d7a45', '#8a4fae', '#b8621b', '#7a7a70'];

  let graficos = {};
  let rangoActual = '30';

  const euros = (n) => Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';

  function escapar(texto) {
    const div = document.createElement('div');
    div.textContent = texto ?? '';
    return div.innerHTML;
  }

  /* ---------- Carga de datos ---------- */

  async function cargar(rango) {
    rangoActual = rango;
    filtros.querySelectorAll('.filtro-rango').forEach((b) => {
      b.setAttribute('aria-pressed', String(b.dataset.rango === rango));
    });

    cargando.hidden = false;
    contenido.hidden = true;
    vacio.hidden = true;

    try {
      const respuesta = await fetch(`../api/analitica.php?rango=${encodeURIComponent(rango)}`, {
        headers: { Accept: 'application/json' },
      });
      const datos = await respuesta.json();

      if (!datos.ok) throw new Error(datos.error || 'No se han podido cargar los datos.');

      cargando.hidden = true;

      if (datos.metricas.resumen.pedidos === 0) {
        vacio.hidden = false;
        return;
      }

      contenido.hidden = false;
      pintarResumen(datos.metricas);
      pintarRecomendaciones(datos.recomendaciones);
      pintarGraficos(datos.metricas);
      pintarTabla(datos.metricas.productos);

    } catch (error) {
      contenido.hidden = true;
      cargando.textContent = 'No se han podido cargar los datos. Comprueba tu conexión e inténtalo de nuevo.';
      cargando.hidden = false;
      console.error('Error al cargar la analítica:', error);
    }
  }

  /* ---------- Resumen ---------- */

  function pintarResumen(metricas) {
    document.getElementById('valorPedidos').textContent = metricas.resumen.pedidos;
    document.getElementById('valorImporte').textContent = euros(metricas.resumen.importe);
    document.getElementById('valorTicket').textContent  = euros(metricas.resumen.ticket_medio);
    document.getElementById('valorEstrella').textContent = metricas.productos[0]
      ? metricas.productos[0].nombre
      : '—';
  }

  /* ---------- Recomendaciones ---------- */

  function pintarRecomendaciones(lista) {
    const contenedor = document.getElementById('recomendaciones');
    contenedor.innerHTML = '';

    if (!lista.length) {
      contenedor.innerHTML = '<p class="recomendaciones__vacio">Sin recomendaciones para este rango todavía.</p>';
      return;
    }

    lista.forEach((rec) => {
      let clave = rec.categoria;
      if (clave === 'tendencia') {
        clave = rec.texto.includes('📈') ? 'tendencia_sube' : 'tendencia_baja';
      }
      const estilo = COLORES_CATEGORIA[clave] || COLORES_CATEGORIA.general;

      const tarjeta = document.createElement('article');
      tarjeta.className = 'recomendacion';
      tarjeta.style.setProperty('--color-recomendacion', estilo.color);
      tarjeta.style.setProperty('--fondo-recomendacion', estilo.fondo);
      tarjeta.innerHTML = `<p>${escapar(rec.texto)}</p>`;
      contenedor.appendChild(tarjeta);
    });
  }

  /* ---------- Tabla ---------- */

  function pintarTabla(productos) {
    const cuerpo = document.getElementById('tablaDetalle');
    cuerpo.innerHTML = productos.map((p) => `
      <tr>
        <td>${escapar(p.nombre)}</td>
        <td>${escapar(p.categoria)}</td>
        <td class="tabla__derecha">${p.unidades}</td>
        <td class="tabla__derecha">${p.pedidos}</td>
        <td class="tabla__derecha">${euros(p.importe)}</td>
      </tr>
    `).join('') || '<tr><td colspan="5">Sin datos en este rango.</td></tr>';
  }

  /* ---------- Gráficos ---------- */

  function destruirGraficos() {
    Object.values(graficos).forEach((g) => g && g.destroy());
    graficos = {};
  }

  function pintarGraficos(metricas) {
    destruirGraficos();

    // Evolución de ventas por día
    const ctxEvolucion = document.getElementById('graficoEvolucion');
    graficos.evolucion = new Chart(ctxEvolucion, {
      type: 'line',
      data: {
        labels: metricas.por_dia.map((d) => formatearFecha(d.dia)),
        datasets: [{
          label: 'Facturación (€)',
          data: metricas.por_dia.map((d) => d.importe),
          borderColor: '#1f3d2b',
          backgroundColor: 'rgba(31,61,43,.12)',
          fill: true,
          tension: 0.3,
          pointRadius: 3,
        }],
      },
      options: opcionesBase({ y: { beginAtZero: true } }),
    });

    // Productos más vendidos (top 8 por unidades)
    const topProductos = [...metricas.productos].sort((a, b) => b.unidades - a.unidades).slice(0, 8);
    graficos.productos = new Chart(document.getElementById('graficoProductos'), {
      type: 'bar',
      data: {
        labels: topProductos.map((p) => p.nombre),
        datasets: [{
          label: 'Unidades vendidas',
          data: topProductos.map((p) => p.unidades),
          backgroundColor: PALETA_GRAFICOS,
        }],
      },
      options: opcionesBase({ y: { beginAtZero: true } }, false),
    });

    // Por categoría
    graficos.categorias = new Chart(document.getElementById('graficoCategorias'), {
      type: 'doughnut',
      data: {
        labels: metricas.categorias.map((c) => c.nombre),
        datasets: [{
          data: metricas.categorias.map((c) => c.importe),
          backgroundColor: PALETA_GRAFICOS,
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12 } } },
      },
    });

    // Por hora del día
    graficos.horas = new Chart(document.getElementById('graficoHoras'), {
      type: 'bar',
      data: {
        labels: metricas.por_hora.map((_, h) => `${h}h`),
        datasets: [{
          label: 'Unidades',
          data: metricas.por_hora,
          backgroundColor: '#1f5d8c',
        }],
      },
      options: opcionesBase({ y: { beginAtZero: true } }),
    });

    // Por día de la semana
    graficos.diaSemana = new Chart(document.getElementById('graficoDiaSemana'), {
      type: 'bar',
      data: {
        labels: metricas.por_dia_semana.map((d) => d.dia),
        datasets: [{
          label: 'Facturación (€)',
          data: metricas.por_dia_semana.map((d) => d.importe),
          backgroundColor: '#2d7a45',
        }],
      },
      options: opcionesBase({ y: { beginAtZero: true } }),
    });
  }

  function opcionesBase(escalas, leyenda = false) {
    return {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: leyenda } },
      scales: escalas,
    };
  }

  function formatearFecha(iso) {
    const [, mes, dia] = iso.split('-');
    return `${dia}/${mes}`;
  }

  /* ---------- Filtros ---------- */

  filtros.addEventListener('click', (evento) => {
    const boton = evento.target.closest('.filtro-rango');
    if (!boton) return;
    cargar(boton.dataset.rango);
  });

  cargar(rangoActual);

  /* ---------- Indicador de conexión ---------- */
  if (window.ConexionIndicador) {
    ConexionIndicador(document.getElementById('indicadorConexion'), {
      urlPing: '../api/ping.php',
      intervalo: 20000,
    });
  }
})();
