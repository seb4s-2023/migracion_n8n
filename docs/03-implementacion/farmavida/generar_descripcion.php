<?php
/* ===========================================================
   FARMAVIDA - generar_descripcion.php
   Flujo 6: recibe nombre y categoría de un producto y devuelve la
   descripción que propone Gemini. Solo para administradores.
   Ya NO usa n8n: llama a Gemini con servicios/ia.php, con
   reintentos y registro en log_automatizaciones.
   =========================================================== */
require 'config.php';
require 'automatizacion.php';
require 'servicios/ia.php';
header('Content-Type: application/json; charset=utf-8');

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos);
    exit;
}

if (!adminLogueado()) {
    responder(['error' => true, 'mensaje' => 'No autorizado'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder(['error' => true, 'mensaje' => 'Método no permitido'], 405);
}

// Liberamos la sesión para no bloquear otras peticiones mientras esperamos a la IA
session_write_close();

$entrada   = json_decode(file_get_contents('php://input'), true) ?: [];
$nombre    = trim($entrada['nombre'] ?? '');
$categoria = trim($entrada['categoria'] ?? '');

if ($nombre === '' || $categoria === '') {
    responder(['error' => true, 'mensaje' => 'Escribe primero el nombre y la categoría del producto.']);
}
if (mb_strlen($nombre) > 150 || mb_strlen($categoria) > 100) {
    responder(['error' => true, 'mensaje' => 'El nombre o la categoría son demasiado largos.']);
}

$descripcion = '';
$ok = ejecutarFlujo(
    'generar_descripcion',
    ['nombre' => $nombre, 'categoria' => $categoria],
    function () use ($nombre, $categoria, &$descripcion) {
        $descripcion = iaDescripcion($nombre, $categoria);
        if ($descripcion === '') throw new RuntimeException('Descripción vacía');
        return $descripcion;
    },
    2
);

if (!$ok) {
    responder(['error' => true, 'mensaje' => 'No se pudo generar la descripción. Intenta de nuevo o escríbela a mano.']);
}

responder(['error' => false, 'descripcion' => $descripcion]);