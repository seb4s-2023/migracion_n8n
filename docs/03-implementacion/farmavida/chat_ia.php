<?php
/* ===========================================================
   FARMAVIDA - chat_ia.php
   Respaldo con IA del chatbot (flujo 3). Ya NO usa n8n:
   llama directamente a Gemini mediante servicios/ia.php,
   con reintentos y registro en log_automatizaciones.
   =========================================================== */
require 'config.php';
require 'automatizacion.php';
require 'servicios/ia.php';
header('Content-Type: application/json; charset=utf-8');

$respaldo = 'Ahora mismo no puedo responder eso. ¿Quieres que te comunique con un asesor en horario de atención?';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['respuesta' => $respaldo]);
    exit;
}

$entrada  = json_decode(file_get_contents('php://input'), true) ?: [];
$pregunta = trim($entrada['mensaje'] ?? '');

if ($pregunta === '' || mb_strlen($pregunta) > 500) {
    echo json_encode(['respuesta' => 'Escríbeme tu pregunta (máximo 500 caracteres).']);
    exit;
}

/* ---- Límite anti-abuso (la cuota gratuita de Gemini es limitada) ---- */
$ahora = time();
$_SESSION['ia_ultima'] = $_SESSION['ia_ultima'] ?? 0;
$_SESSION['ia_total']  = $_SESSION['ia_total']  ?? 0;

if ($ahora - $_SESSION['ia_ultima'] < 3 || $_SESSION['ia_total'] >= 30) {
    echo json_encode(['respuesta' => 'Estás escribiendo muy rápido o alcanzaste el límite de consultas. Intenta de nuevo en un momento.']);
    exit;
}
$_SESSION['ia_ultima'] = $ahora;
$_SESSION['ia_total']++;
session_write_close();   // no bloquear otras peticiones mientras esperamos a la IA

/* ---- Historial corto (últimos 6 mensajes) ---- */
$historial = [];
foreach (array_slice($entrada['historial'] ?? [], -6) as $m) {
    $tipo  = ($m['tipo'] ?? '') === 'bot' ? 'bot' : 'user';
    $texto = mb_substr(trim($m['texto'] ?? ''), 0, 300);
    if ($texto !== '') $historial[] = ['tipo' => $tipo, 'texto' => $texto];
}

/* ---- Catálogo: solo productos activos, sin datos de clientes ---- */
$catalogo = [];
$res = $conexion->query(
    "SELECT nombre, descripcion, categoria, precio, cantidad_disponible
     FROM productos WHERE estado = 'activo'"
);
while ($p = $res->fetch_assoc()) {
    $catalogo[] = [
        'nombre'      => $p['nombre'],
        'descripcion' => $p['descripcion'],
        'categoria'   => $p['categoria'],
        'precio_cop'  => (int)$p['precio'],
        'disponible'  => (int)$p['cantidad_disponible'] > 0,
    ];
}

/* ---- Flujo con reintentos + log. Si falla, se usa el mensaje de respaldo (plan B) ---- */
$respuesta = null;
ejecutarFlujo(
    'chat_ia',
    ['pregunta' => mb_substr($pregunta, 0, 200)],
    function () use ($pregunta, $historial, $catalogo, &$respuesta) {
        $respuesta = iaChat($pregunta, $historial, $catalogo);
        return $respuesta;          // lo que se guarda como "resultado" en el log
    },
    2                                // 2 intentos: el cliente está esperando
);

echo json_encode(['respuesta' => $respuesta ?: $respaldo]);