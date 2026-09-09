<?php
/**
 * Catálogo de iconos disponibles para los productos (assets/img/productos/).
 * Se usa tanto para pintar el selector en el panel como para validar que
 * el icono que llega desde el navegador es uno de verdad y no cualquier
 * cadena arbitraria.
 */

declare(strict_types=1);

/** @return array<int, array{archivo: string, etiqueta: string}> */
function iconosProductosDisponibles(): array
{
    return [
        ['archivo' => '01-bocata-embutido.png',     'etiqueta' => 'Bocata de embutido'],
        ['archivo' => '02-bocata-pollo.png',         'etiqueta' => 'Bocata de pollo'],
        ['archivo' => '03-bocata-lomo.png',          'etiqueta' => 'Bocata de lomo'],
        ['archivo' => '04-croasant-mixto.png',       'etiqueta' => 'Croasant mixto'],
        ['archivo' => '05-croasant-vegetal.png',     'etiqueta' => 'Croasant vegetal'],
        ['archivo' => '06-sandwich-mixto.png',       'etiqueta' => 'Sandwich mixto'],
        ['archivo' => '07-sandwich-vegetal.png',     'etiqueta' => 'Sandwich vegetal'],
        ['archivo' => '08-hamburguesa.png',          'etiqueta' => 'Hamburguesa'],
        ['archivo' => '09-hamburguesa-bacon.png',    'etiqueta' => 'Hamburguesa con bacon'],
        ['archivo' => '10-bocata-atun.png',          'etiqueta' => 'Bocata de atún'],
        ['archivo' => '11-hot-dog.png',              'etiqueta' => 'Hot dog'],
        ['archivo' => '12-frankfurt.png',            'etiqueta' => 'Frankfurt'],
        ['archivo' => '13-bocata-jamon.png',         'etiqueta' => 'Bocata de jamón'],
        ['archivo' => '14-bocata-vegetal.png',       'etiqueta' => 'Bocata vegetal'],
        ['archivo' => '15-bocata-tortilla.png',      'etiqueta' => 'Bocata de tortilla'],
        ['archivo' => '16-bocata-salmon.png',        'etiqueta' => 'Bocata de salmón'],
        ['archivo' => '17-bocata-queso.png',         'etiqueta' => 'Bocata de queso'],
        ['archivo' => '18-sandwich-club.png',        'etiqueta' => 'Sándwich club'],
        ['archivo' => '19-sandwich-pollo.png',       'etiqueta' => 'Sándwich de pollo'],
        ['archivo' => '20-sandwich-atun.png',        'etiqueta' => 'Sándwich de atún'],
        ['archivo' => '21-bagel-salmon.png',         'etiqueta' => 'Bagel de salmón'],
        ['archivo' => '22-bagel-mixto.png',          'etiqueta' => 'Bagel mixto'],
        ['archivo' => '23-bagel-vegetal.png',        'etiqueta' => 'Bagel vegetal'],
        ['archivo' => '24-muffin.png',               'etiqueta' => 'Muffin'],
        ['archivo' => '25-croasant-natural.png',     'etiqueta' => 'Croasant natural'],
        ['archivo' => '26-napolitana-chocolate.png', 'etiqueta' => 'Napolitana de chocolate'],
        ['archivo' => '27-napolitana-crema.png',     'etiqueta' => 'Napolitana de crema'],
        ['archivo' => '28-donut-chocolate.png',      'etiqueta' => 'Donut de chocolate'],
        ['archivo' => '29-donut-fresa.png',          'etiqueta' => 'Donut de fresa'],
        ['archivo' => '30-donut-colores.png',        'etiqueta' => 'Donut de colores'],
        ['archivo' => '31-magdalena.png',            'etiqueta' => 'Magdalena'],
        ['archivo' => '32-galleta-americana.png',    'etiqueta' => 'Galleta americana'],
        ['archivo' => '33-brownie.png',              'etiqueta' => 'Brownie'],
        ['archivo' => '34-tarta-queso.png',          'etiqueta' => 'Tarta de queso'],
        ['archivo' => '35-tarta-chocolate.png',      'etiqueta' => 'Tarta de chocolate'],
        ['archivo' => '36-tarta-zanahoria.png',      'etiqueta' => 'Tarta de zanahoria'],
        ['archivo' => '37-yogur-granola.png',        'etiqueta' => 'Yogur con granola'],
        ['archivo' => '38-bowl-fruta.png',           'etiqueta' => 'Bowl de fruta'],
        ['archivo' => '39-ensalada-mixta.png',       'etiqueta' => 'Ensalada mixta'],
        ['archivo' => '40-ensalada-cesar.png',       'etiqueta' => 'Ensalada césar'],
        ['archivo' => '41-zumo-naranja.png',         'etiqueta' => 'Zumo de naranja natural'],
        ['archivo' => '42-zumo-frutas.png',          'etiqueta' => 'Zumo de frutas'],
        ['archivo' => '43-batido-fresa.png',         'etiqueta' => 'Batido de fresa'],
        ['archivo' => '44-batido-chocolate.png',     'etiqueta' => 'Batido de chocolate'],
        ['archivo' => '45-cafe-solo.png',            'etiqueta' => 'Café solo'],
        ['archivo' => '46-cafe-leche.png',           'etiqueta' => 'Café con leche'],
        ['archivo' => '47-cortado.png',              'etiqueta' => 'Cortado'],
        ['archivo' => '48-cafe-americano.png',       'etiqueta' => 'Café americano'],
        ['archivo' => '49-te.png',                   'etiqueta' => 'Té'],
        ['archivo' => '50-infusiones.png',           'etiqueta' => 'Infusiones'],
    ];
}

/**
 * Versión del juego de iconos: la fecha del archivo modificado más
 * recientemente. Se añade a la URL de las imágenes (?v=...) para que el
 * navegador no siga mostrando una versión antigua cacheada cuando se
 * regeneran los recortes.
 */
function versionIconos(): int
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }

    $version = 0;
    foreach (glob(__DIR__ . '/../assets/img/productos/*.png') ?: [] as $ruta) {
        $fecha = filemtime($ruta);
        if ($fecha > $version) {
            $version = $fecha;
        }
    }
    return $version;
}

/** ¿Es este nombre de archivo uno de los iconos válidos? */
function iconoProductoValido(?string $archivo): bool
{
    if ($archivo === null || $archivo === '') {
        return true; // sin icono es válido
    }
    foreach (iconosProductosDisponibles() as $icono) {
        if ($icono['archivo'] === $archivo) {
            return true;
        }
    }
    return false;
}
