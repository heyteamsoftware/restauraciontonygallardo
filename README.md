# Cafetería del instituto — pedidos por QR

Aplicación web para que el alumnado pida en la cafetería desde el móvil
escaneando un código QR, y para que la cafetería gestione esos pedidos en
tiempo real desde un panel.

- **Alumno:** escanea el QR → elige productos → deja nombre, DNI y correo →
  recibe un código de recogida.
- **Cafetería:** ve los pedidos entrar en directo, los pasa de *pendiente* a
  *en curso* y a *completado*. Al completarlos se avisa al alumno por correo.
- **Catálogo:** los productos se añaden, editan, ocultan o borran desde el panel.

## Requisitos

- PHP **8.1 o superior**
- MySQL 5.7 / MariaDB
- Un proveedor de correo por API (opcional pero recomendado, ver más abajo)

Está pensado para hosting compartido tipo InfinityFree: no necesita Composer,
ni Node, ni ninguna dependencia externa.

## Instalación

1. **Subir los archivos** por FTP a la carpeta `restauracionTonyGallardo/`.

2. **Crear la base de datos** desde el panel del hosting (MySQL Databases) y
   ejecutar `sql/esquema.sql` en phpMyAdmin. Eso crea las tablas y deja el
   catálogo inicial con los siete productos.

3. **Configurar**: copiar `includes/config.ejemplo.php` a `includes/config.php`
   y rellenar los datos de la base de datos.

4. **Contraseña del panel**: abrir en el navegador
   `https://tu-web/admin/generar_hash.php?clave=LA_QUE_QUIERAS`,
   copiar el hash resultante en `config.php` (`admin` → `hash_pass`) y
   **borrar `admin/generar_hash.php` del servidor**.

5. **Comprobar** que `https://tu-web/` muestra el formulario y que
   `https://tu-web/admin/` pide usuario y contraseña.

## Aviso por correo

InfinityFree **bloquea la función `mail()` de PHP** y las conexiones SMTP
salientes, así que el correo se envía llamando por HTTPS a la API de un
proveedor externo. Hay dos ya implementados, ambos con plan gratuito:

| Proveedor | Gratis        | Dónde se saca la clave |
|-----------|---------------|------------------------|
| Brevo     | 300 correos/día | brevo.com → SMTP & API → API Keys |
| Resend    | 100 correos/día | resend.com → API Keys |

En `config.php`, dentro de `email`, poner `proveedor` a `'brevo'` o `'resend'`
y pegar la `api_key`. Con `'ninguno'` la aplicación funciona igual, pero no
manda correos (el panel avisa cuando un envío falla).

## Estructura

```
index.php              Página del alumno (la que abre el QR)
admin/
  index.php            Login del panel
  panel.php            Pedidos en tiempo real
  productos.php        Gestión del catálogo
  salir.php            Cierra la sesión
  generar_hash.php     Utilidad de instalación — BORRAR tras usarla
api/
  crear_pedido.php     Recibe el pedido del alumno
  listar_pedidos.php   Alimenta el panel (se consulta cada 5 s)
  cambiar_estado.php   Cambia el estado y dispara el aviso por correo
  productos.php        Alta, edición, activación y borrado de productos
includes/
  arranque.php         Configuración, conexión a la BD y utilidades
  auth.php             Sesión del administrador
  email.php            Envío de avisos
  config.php           Contraseñas — NO se sube al repositorio
assets/css | assets/js Estilos y JavaScript
sql/esquema.sql        Tablas y catálogo inicial
```

## Decisiones técnicas

- **"Tiempo real" por consulta periódica.** El hosting gratuito no permite
  WebSockets, así que el panel pregunta al servidor cada 5 segundos. Para
  ahorrar tráfico, el servidor devuelve una *firma* del estado; si no ha
  cambiado nada, la respuesta viene vacía y no se repinta la pantalla.
- **Los precios se calculan en el servidor**, nunca se aceptan los que envía
  el navegador.
- **Las líneas guardan copia del nombre y el precio** del producto, para que
  cambiar el catálogo no altere los pedidos ya hechos.
- **Un producto que ya aparece en algún pedido no se borra**, se desactiva.
- **El DNI se valida con su letra de control** (también acepta NIE).

## El código QR

El QR solo tiene que apuntar a la dirección pública, por ejemplo
`https://paneltony.gt.tc/restauracionTonyGallardo/`. Se puede generar con
cualquier herramienta gratuita e imprimirlo para las mesas y la barra.

## Pendiente

- Ajustar los precios reales en el panel de productos.
- Configurar el proveedor de correo.
- Decidir el horario de pedidos en `config.php` (`app` → `horario_pedidos`).
