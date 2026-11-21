<?php
/* ===========================================================
   FARMAVIDA - automatizacion.php
   Motor de automatización propio (reemplaza a n8n).
   - ejecutarFlujo():     reintentos + manejo de errores + log en MySQL
   - despuesDeResponder(): ejecuta tareas lentas DESPUÉS de contestarle
                           al navegador, para que el cliente no espere
   Requiere que config.php ya se haya cargado ($conexion).
   =========================================================== */
require_once __DIR__ . '/env.php';

/* ---- Guarda una fila en log_automatizaciones ---- */
function logFlujo($flujo, $estado, $intentos, array $entrada, $resultado, $ms) {
    global $conexion;
    try {
        $in  = mb_substr(json_encode($entrada, JSON_UNESCAPED_UNICODE), 0, 2000);
        $out = mb_substr((string)$resultado, 0, 2000);
        $stmt = $conexion->prepare(
            "INSERT INTO log_automatizaciones (flujo, estado, intentos, entrada, resultado, duracion_ms)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssissi', $flujo, $estado, $intentos, $in, $out, $ms);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log("FarmaVida: no se pudo registrar el flujo $flujo: " . $e->getMessage());
    }
}

/* ---- Ejecuta un flujo con reintentos. Devuelve true/false y NUNCA rompe la web.
        $entradaLog: datos que quedan en el log (sin información sensible). ---- */
function ejecutarFlujo($flujo, array $entradaLog, callable $accion, $maxIntentos = 3) {
    $inicio = microtime(true);
    $ultimoError = '';
    for ($i = 1; $i <= $maxIntentos; $i++) {
        try {
            $resultado = $accion();
            logFlujo($flujo, 'ok', $i, $entradaLog, $resultado ?? 'ok', (int)((microtime(true) - $inicio) * 1000));
            return true;
        } catch (Throwable $e) {
            $ultimoError = $e->getMessage();
            if ($i < $maxIntentos) sleep($i);            // espera 1 s, luego 2 s...
        }
    }
    logFlujo($flujo, 'error', $maxIntentos, $entradaLog, $ultimoError, (int)((microtime(true) - $inicio) * 1000));
    return false;
}

/* ---- Deja la tarea para DESPUÉS de enviar la respuesta al navegador ----
   Úsala ANTES del echo final de la respuesta. */
function despuesDeResponder(callable $tarea) {
    if (!isset($GLOBALS['__tareas_diferidas'])) {
        $GLOBALS['__tareas_diferidas'] = [];
        ob_start();                                       // retiene la respuesta
        register_shutdown_function(function () {
            $largo = ob_get_length();
            if ($largo !== false && !headers_sent()) {
                header('Content-Length: ' . $largo);
                header('Connection: close');
            }
            while (ob_get_level() > 0) ob_end_flush();    // envía la respuesta
            flush();
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

            ignore_user_abort(true);
            set_time_limit(90);
            foreach ($GLOBALS['__tareas_diferidas'] as $t) {
                try { $t(); } catch (Throwable $e) { error_log('FarmaVida tarea diferida: ' . $e->getMessage()); }
            }
        });
    }
    $GLOBALS['__tareas_diferidas'][] = $tarea;
}

/* ---- Oculta parte del correo para guardarlo en el log: ju***@gmail.com ---- */
function enmascararCorreo($correo) {
    $p = explode('@', (string)$correo);
    if (count($p) !== 2) return '***';
    return mb_substr($p[0], 0, 2) . '***@' . $p[1];
}
