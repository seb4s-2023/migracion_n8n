<?php
/* FARMAVIDA - borrar_formulas_antiguas.php
   Retención de datos de salud: borra del disco las fotos de fórmulas con más de
   N días (FORMULAS_DIAS_RETENCION, por defecto 30) y deja el registro en la tabla
   (quién y cuándo) marcando el archivo como 'BORRADO'.
   Solo por línea de comandos. Programa con cron, todos los días 3 a.m.:
     0 3 * * * /usr/bin/php /ruta/farmavida/borrar_formulas_antiguas.php
   Windows: C:\xampp\php\php.exe C:\xampp\htdocs\farmavida\borrar_formulas_antiguas.php */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Acceso no permitido.'); }

require __DIR__ . '/config.php';
require __DIR__ . '/automatizacion.php';

$inicio = microtime(true);
$dias   = max(1, (int)env('FORMULAS_DIAS_RETENCION', 30));
$dir    = rtrim(env('FORMULAS_DIR', __DIR__ . '/storage/formulas'), '/\\');

$res = $conexion->query(
    "SELECT id, archivo FROM formulas_subidas
     WHERE archivo <> 'BORRADO' AND fecha < NOW() - INTERVAL $dias DAY"
);
$borradas = 0;
$upd = $conexion->prepare("UPDATE formulas_subidas SET archivo = 'BORRADO' WHERE id = ?");
while ($f = $res->fetch_assoc()) {
    $ruta = $dir . '/' . basename($f['archivo']);
    if (is_file($ruta) && !@unlink($ruta)) continue;      // si no se pudo borrar, se reintenta mañana
    $upd->bind_param('i', $f['id']);
    $upd->execute();
    $borradas++;
}
$upd->close();

logFlujo('borrar_formulas', 'ok', 1, ['dias' => $dias], "borradas=$borradas", (int)((microtime(true) - $inicio) * 1000));
echo "Fotos borradas (más de $dias días): $borradas\n";
