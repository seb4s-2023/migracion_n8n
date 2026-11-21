<?php
/* ===========================================================
   FARMAVIDA - enviar_resumen.php
   Calcula el resumen de ventas del día y lo envía al administrador
   por Telegram (flujo 4, ya sin n8n).

   Se ejecuta con tarea programada, NO desde el navegador:
     Windows (Programador de tareas, 8 p.m.):
        C:\xampp\php\php.exe C:\xampp\htdocs\farmavida\enviar_resumen.php
     Linux (cron, 8 p.m. hora Colombia):
        0 20 * * * TZ=America/Bogota /usr/bin/php /ruta/farmavida/enviar_resumen.php
   =========================================================== */

// Solo por línea de comandos: si alguien lo abre desde la web, se bloquea
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso no permitido.');
}

require __DIR__ . '/config.php';
require __DIR__ . '/automatizacion.php';
require __DIR__ . '/servicios/alertas.php';

// "Hoy" debe ser el día de Colombia, tanto en PHP como en MySQL
date_default_timezone_set('America/Bogota');
$conexion->query("SET time_zone = '-05:00'");

/* ---- Totales del día ----
   Cada fila de pedidos es UNA unidad vendida.
   Una "compra" = filas del mismo cliente en el mismo instante. */
$res = $conexion->query(
    "SELECT COUNT(*) AS unidades,
            COALESCE(SUM(precio), 0) AS total,
            COUNT(DISTINCT cliente_id, fecha) AS compras
     FROM pedidos
     WHERE DATE(fecha) = CURDATE()"
);
$tot = $res->fetch_assoc();

/* ---- Producto más vendido del día ---- */
$masVendido  = null;
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

/* ---- Envío por Telegram (con reintentos y log) ---- */
$ok = flujoResumenDiario([
    'fecha'            => date('d/m/Y'),
    'compras'          => (int)$tot['compras'],
    'unidades'         => (int)$tot['unidades'],
    'total'            => (int)$tot['total'],
    'total_formateado' => number_format((float)$tot['total'], 0, ',', '.'),
    'mas_vendido'      => $masVendido,
    'unidades_top'     => $unidadesTop,
]);

if ($ok) {
    echo "Resumen enviado por Telegram (" . date('c') . ")\n";
} else {
    $fila = $conexion->query("SELECT resultado FROM log_automatizaciones ORDER BY id DESC LIMIT 1")->fetch_assoc();
    echo "FALLÓ. Motivo: " . ($fila['resultado'] ?? '(sin detalle en el log)') . "\n";
    exit(1);
}
