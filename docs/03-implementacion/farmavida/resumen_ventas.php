<?php
/* ===========================================================
   FARMAVIDA - resumen_ventas.php
   Devuelve en JSON el resumen de ventas del día de hoy.
   Lo consulta n8n (Schedule Trigger a las 8 p.m.) y lo envía
   por Telegram. Protegido con el header X-Secret.
   =========================================================== */
require 'config.php';
header('Content-Type: application/json; charset=utf-8');

// Debe ser el MISMO secreto que pongas en el nodo HTTP Request de n8n
$secreto = 'cambia_este_secreto_largo';

$recibido = $_SERVER['HTTP_X_SECRET'] ?? '';
if (!hash_equals($secreto, $recibido)) {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'No autorizado']);
    exit;
}

/* ---- Totales del día ----
   En la tabla pedidos cada fila es UNA unidad vendida.
   Una "compra" = todas las filas del mismo cliente en el mismo instante. */
$res = $conexion->query(
    "SELECT COUNT(*) AS unidades,
            COALESCE(SUM(precio), 0) AS total,
            COUNT(DISTINCT cliente_id, fecha) AS compras
     FROM pedidos
     WHERE DATE(fecha) = CURDATE()"
);
$tot = $res->fetch_assoc();

/* ---- Producto más vendido del día ---- */
$masVendido = null;
$unidadesTop = 0;
$res = $conexion->query(
    "SELECT pr.nombre, COUNT(*) AS unidades
     FROM pedidos pe
     JOIN productos pr ON pr.id = pe.producto_id
     WHERE DATE(pe.fecha) = CURDATE()
     GROUP BY pe.producto_id, pr.nombre
     ORDER BY unidades DESC, pr.nombre ASC
     LIMIT 1"
);
if ($fila = $res->fetch_assoc()) {
    $masVendido  = $fila['nombre'];
    $unidadesTop = (int)$fila['unidades'];
}

echo json_encode([
    'error'            => false,
    'fecha'            => date('d/m/Y'),
    'compras'          => (int)$tot['compras'],
    'unidades'         => (int)$tot['unidades'],
    'total'            => (int)$tot['total'],
    'total_formateado' => number_format((float)$tot['total'], 0, ',', '.'),
    'mas_vendido'      => $masVendido,
    'unidades_top'     => $unidadesTop,
]);