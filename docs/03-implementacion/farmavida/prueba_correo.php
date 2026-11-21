<?php
/* FARMAVIDA - prueba_correo.php
   Envía un correo de ejemplo a TU propio Gmail (SMTP_USER) para probar la
   automatización 1 sin hacer una compra. Solo por línea de comandos:
     C:\xampp\php\php.exe prueba_correo.php          (español)
     C:\xampp\php\php.exe prueba_correo.php en       (inglés)
   BÓRRALO antes de desplegar. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Acceso no permitido.'); }

require __DIR__ . '/config.php';
require __DIR__ . '/automatizacion.php';
require __DIR__ . '/servicios/compra_confirmada.php';

$ok = flujoCompraConfirmada([
    'nombre' => 'Cliente de Prueba',
    'correo' => env('SMTP_USER'),
    'items'  => [
        ['nombre' => 'Acetaminofén 500mg', 'cantidad' => 2, 'precio' => 8900,  'subtotal' => 17800],
        ['nombre' => 'Vitamina C 1g',      'cantidad' => 1, 'precio' => 12500, 'subtotal' => 12500],
    ],
    'total'  => 30300,
    'idioma' => ($argv[1] ?? 'es') === 'en' ? 'en' : 'es',
    'fecha'  => date('d/m/Y H:i'),
]);

if ($ok) {
    echo "OK: revisa tu bandeja (y spam).\n";
} else {
    $fila = $conexion->query("SELECT resultado FROM log_automatizaciones ORDER BY id DESC LIMIT 1")->fetch_assoc();
    echo "FALLÓ. Motivo: " . ($fila['resultado'] ?? '(sin detalle en el log)') . "\n";
}