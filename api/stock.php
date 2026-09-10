<?php
/**
 * GET api/stock.php
 * Endpoint público y ligero (sin sesión de admin) que da el stock actual
 * de los productos visibles, para que la página de pedido (y la venta en
 * mostrador) puedan refrescarlo sin recargar y así reflejar lo que va
 * quedando cuando hay varias personas pidiendo a la vez.
 *
 * Respuesta: { ok: true, stock: { "3": 5, "7": null, ... } }
 * (null = sin límite)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/arranque.php';

$stock = [];
foreach (catalogoConStock() as $producto) {
    $stock[$producto['id']] = $producto['stock'];
}

json(['ok' => true, 'stock' => $stock]);
