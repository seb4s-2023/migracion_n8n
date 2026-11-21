<?php
/* FARMAVIDA - env.php
   Lee el archivo .env y ofrece env('CLAVE', 'valor_por_defecto'). */

function cargarEnv($ruta = null) {
    $ruta = $ruta ?? __DIR__ . '/.env';
    if (!is_readable($ruta)) return;
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) continue;
        [$k, $v] = explode('=', $linea, 2);
        $k = trim($k);
        $v = trim($v, " \t\"'");
        if (getenv($k) === false) {      // las variables reales del servidor mandan sobre el .env
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

function env($clave, $defecto = null) {
    $v = getenv($clave);
    return ($v === false || $v === '') ? $defecto : $v;
}

cargarEnv();
