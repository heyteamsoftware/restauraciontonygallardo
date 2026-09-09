# Herramientas

## recortar_iconos.ps1

Trocea `pixelart.png` (la lámina de 10×5 iconos de cafetería) en los 50 PNG
individuales de `assets/img/productos/`.

No usa una rejilla fija, porque los iconos de la lámina no están alineados a
celdas exactas y algunos se tocan entre sí. En su lugar:

1. Detecta las 5 bandas horizontales (filas) mirando qué filas de píxeles
   tienen contenido.
2. Dentro de cada banda, separa los bloques de columnas con contenido. Donde
   dos iconos se tocan y salen como un bloque doble, lo parte por su punto
   más estrecho (el "valle" del perfil de píxeles).
3. En cada segmento etiqueta las **regiones conectadas** y descarta las
   pequeñas que tocan un borde de corte: así no se cuela ningún trozo del
   icono vecino. Las piezas sueltas propias del icono (la rodaja de naranja
   del zumo, las hojas del té) sí se conservan.
4. Recorta al contenido real y lo centra en un lienzo cuadrado común, igual
   para todos, respetando el tamaño relativo original de cada icono.

Uso (desde Windows, no necesita instalar nada):

```powershell
powershell.exe -ExecutionPolicy Bypass -File herramientas\recortar_iconos.ps1
```

Después hay que subir por FTP los PNG de `assets/img/productos/`. El nombre
de archivo no cambia, así que la caché del navegador se salta con el
parámetro `?v=` que añade `versionIconos()` (ver `includes/iconos_productos.php`).
