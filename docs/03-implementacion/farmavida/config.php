<?php
/* ===========================================================
   FARMAVIDA - config.php
   Conexión a MySQL + sesión + funciones de autenticación.
   (Las automatizaciones viven en automatizacion.php y servicios/.)
   =========================================================== */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/env.php';   // lee el archivo .env

$host       = env('DB_HOST', 'localhost');
$usuario    = env('DB_USER', 'root');
$contrasena = env('DB_PASS', '');
$basedatos  = env('DB_NAME', 'farmavida');

$conexion = new mysqli($host, $usuario, $contrasena, $basedatos);

if ($conexion->connect_error) {
    http_response_code(500);
    die(json_encode([
        'error' => true,
        'mensaje' => 'No se pudo conectar a la base de datos.'
    ]));
}

$conexion->set_charset('utf8mb4');

/* -----------------------------------------------------------
   Funciones de sesión
------------------------------------------------------------ */

function clienteLogueado() {
    return isset($_SESSION['cliente_id']);
}

function adminLogueado() {
    return isset($_SESSION['admin_id']);
}

// Usar al inicio de páginas exclusivas para clientes (ej. dashboard_cliente.php)
function requerirCliente() {
    if (!clienteLogueado()) {
        header('Location: login.php');
        exit;
    }
}

// Usar al inicio de páginas exclusivas para administradores (ej. reporte.php)
function requerirAdmin() {
    if (!adminLogueado()) {
        header('Location: login.php?tipo=admin');
        exit;
    }
}