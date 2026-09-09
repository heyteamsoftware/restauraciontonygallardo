/* =============================================================
   Indicador de conexión reutilizable (punto de color + texto).
   Combina dos señales:
     - navigator.onLine: si el dispositivo tiene red en absoluto.
     - un "ping" al servidor: si de verdad se puede hablar con él
       (una red wifi puede estar "conectada" y no dar internet, o el
       servidor puede estar caído aunque haya red).
   Uso:
     ConexionIndicador(elemento, { intervalo: 20000, urlPing: 'api/ping.php' });
   ============================================================= */
'use strict';

window.ConexionIndicador = function (elemento, opciones = {}) {
  if (!elemento) return null;

  const intervalo = opciones.intervalo || 20000;
  const urlPing   = opciones.urlPing || 'api/ping.php';
  const tiempoLimite = opciones.tiempoLimite || 6000;

  function pintar(estado, texto) {
    elemento.className = 'indicador-conexion indicador-conexion--' + estado;
    elemento.innerHTML = '<span class="indicador-conexion__punto" aria-hidden="true"></span><span>' + texto + '</span>';
  }

  async function comprobar() {
    if (!navigator.onLine) {
      pintar('error', 'Sin red');
      return false;
    }

    try {
      const controlador = new AbortController();
      const temporizador = setTimeout(() => controlador.abort(), tiempoLimite);
      const respuesta = await fetch(urlPing, { cache: 'no-store', signal: controlador.signal });
      clearTimeout(temporizador);

      if (!respuesta.ok) throw new Error('http-' + respuesta.status);

      const datos = await respuesta.json().catch(() => ({}));

      if (datos && datos.bd === false) {
        pintar('aviso', 'Servidor sin base de datos');
        return false;
      }

      pintar('ok', opciones.textoConectado || 'Conectado');
      return true;

    } catch {
      pintar('error', 'Sin conexión con el servidor');
      return false;
    }
  }

  window.addEventListener('online', comprobar);
  window.addEventListener('offline', () => pintar('error', 'Sin red'));

  comprobar();
  const id = setInterval(comprobar, intervalo);

  return {
    comprobar,
    marcarOk:   (texto) => pintar('ok', texto || opciones.textoConectado || 'Conectado'),
    marcarError: (texto) => pintar('error', texto || 'Sin conexión con el servidor'),
    detener: () => clearInterval(id),
  };
};
