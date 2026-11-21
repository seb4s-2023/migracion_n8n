<?php
/* FARMAVIDA - prueba_telegram.php
   Envía los 4 mensajes de ejemplo a tu Telegram para probar los flujos
   sin hacer compras. Solo por línea de comandos:
     C:\xampp\php\php.exe prueba_telegram.php
   BÓRRALO antes de desplegar. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Acceso no permitido.'); }

require __DIR__ . '/config.php';
require __DIR__ . '/automatizacion.php';
require __DIR__ . '/servicios/alertas.php';

function mostrar($nombre, $ok, $conexion) {
    if ($ok) { echo "OK    $nombre\n"; return; }
    $f = $conexion->query("SELECT resultado FROM log_automatizaciones ORDER BY id DESC LIMIT 1")->fetch_assoc();
    echo "FALLÓ $nombre -> " . ($f['resultado'] ?? '(sin detalle)') . "\n";
}

mostrar('stock_bajo', flujoStockBajo([
    ['producto' => 'Ibuprofeno 400mg', 'stock' => 3],
    ['producto' => 'Termómetro digital', 'stock' => 0],
]), $conexion);

mostrar('informe_agotados', flujoInformeAgotados([
    'total' => 1, 'productos' => ['Termómetro digital'], 'fecha' => date('d/m/Y H:i'),
]), $conexion);

mostrar('calificacion_baja', flujoCalificacionBaja([
    'conversacion_id' => 999, 'calificacion' => 2, 'fecha' => date('Y-m-d H:i:s'), 'total_mensajes' => 2,
    'transcripcion' => "Cliente: ¿tienen jarabe para la tos?\nBot: No estoy seguro de haber entendido eso.\n",
]), $conexion);

mostrar('resumen_diario', flujoResumenDiario([
    'fecha' => date('d/m/Y'), 'compras' => 3, 'unidades' => 7, 'total' => 85400,
    'total_formateado' => '85.400', 'mas_vendido' => 'Acetaminofén 500mg', 'unidades_top' => 4,
]), $conexion);
