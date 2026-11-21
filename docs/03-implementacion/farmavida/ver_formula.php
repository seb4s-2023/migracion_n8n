<?php
/* ===========================================================
   FARMAVIDA - ver_formula.php?id=N
   Entrega la foto de una fórmula SOLO a un administrador con sesión.
   Es la única vía para ver las fotos (la carpeta no es pública), y
   cada consulta queda registrada en log_automatizaciones.
   =========================================================== */
require 'config.php';
require 'automatizacion.php';
requerirAdmin();

$id = (int)($_GET['id'] ?? 0);
$stmt = $conexion->prepare("SELECT archivo, mime FROM formulas_subidas WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$f = $stmt->get_result()->fetch_assoc();
$stmt->close();

$dir  = rtrim(env('FORMULAS_DIR', __DIR__ . '/storage/formulas'), '/\\');
$ruta = $f ? $dir . '/' . basename($f['archivo']) : '';

if (!$f || $f['archivo'] === 'BORRADO' || !is_file($ruta)) {
    http_response_code(404);
    exit('La imagen no existe o ya fue eliminada.');
}

logFlujo('ver_formula', 'ok', 1, ['formula_id' => $id, 'admin_id' => (int)$_SESSION['admin_id']], 'imagen consultada', 0);

header('Content-Type: ' . $f['mime']);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
