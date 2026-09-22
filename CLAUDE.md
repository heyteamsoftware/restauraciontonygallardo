Para publicar en el servidor: python subir_ftp.py

## Servidor de producción

- La app está en https://myappsserver.duckdns.org/Tony_Restauracion/
- Base de datos: MySQL/MariaDB, host "localhost" (solo accesible desde el propio
  servidor, no hay acceso remoto al puerto 3306), base de datos
  "tony_restauracion", usuario "tony_restauracion". Credenciales completas y PIN
  de acceso al panel: solo en includes/config.php del servidor (fuera de git).
- includes/config.php del servidor ya tiene esos datos configurados.
  **NUNCA se sube ni se sobrescribe** (subir_ftp.py ya lo protege por diseño;
  no lo toques tampoco a mano).
- Si cambia sql/esquema.sql, avisar con las sentencias SQL nuevas exactas para
  ejecutarlas en phpMyAdmin (https://myappsserver.duckdns.org/phpmyadmin):
  no hay acceso remoto a la base de datos para aplicarlas directamente.
