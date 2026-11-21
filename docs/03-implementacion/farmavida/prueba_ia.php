<?php
/* FARMAVIDA - prueba_ia.php
   Prueba los flujos de IA (Gemini) sin abrir el navegador. Solo por línea de comandos:
     C:\xampp\php\php.exe prueba_ia.php
   BÓRRALO antes de desplegar. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Acceso no permitido.'); }

require __DIR__ . '/config.php';
require __DIR__ . '/automatizacion.php';
require __DIR__ . '/servicios/ia.php';

echo "--- Descripción ---\n";
try {
    echo iaDescripcion('Loratadina 10mg', 'Antialérgicos'), "\n";
} catch (Throwable $e) {
    echo "FALLÓ: ", $e->getMessage(), "\n";
}

echo "\n--- Chat ---\n";
try {
    $catalogo = [['nombre' => 'Vitamina C 1g', 'descripcion' => 'Tubo x 10 tabletas efervescentes.',
                  'categoria' => 'Vitaminas y suplementos', 'precio_cop' => 12500, 'disponible' => true]];
    echo iaChat('¿Cuánto vale la vitamina C y la tienen disponible?', [], $catalogo), "\n";
} catch (Throwable $e) {
    echo "FALLÓ: ", $e->getMessage(), "\n";
}
