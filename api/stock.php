<?php
/**
 * GET api/stock.php
 * Endpoint público y ligero (sin sesión de admin) que da el stock actual
 * de los productos visibles, para que la página de pedido (y la venta en
 * mostrador) puedan refrescarlo sin recargar y así reflejar lo que va
 * quedando cuando hay varias personas pidiendo a la vez.
 *
 * Respuesta: {
 *   ok: true,
 *   stock: { "3": 5, "7": null, ... },              (null = sin límite)
 *   ingredientes: { "3": [{ingrediente_id, cantidad, stock_ingrediente}], ... }
 * }
 *
 * "ingredientes" es la receta de cada producto (vacía si no usa ninguno):
 * el navegador la necesita para poder recalcular, sin recargar, cuánto
 * queda de un producto en cuanto se elige otro que comparte el mismo
 * ingrediente (p. ej. el mismo pan).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/arranque.php';

$stock = [];
$ingredientes = [];
foreach (catalogoConStock() as $producto) {
    $stock[$producto['id']] = $producto['stock'];
    $ingredientes[$producto['id']] = $producto['ingredientes'];
}

json(['ok' => true, 'stock' => $stock, 'ingredientes' => $ingredientes]);
