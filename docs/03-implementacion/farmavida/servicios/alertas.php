<?php
/* ===========================================================
   FARMAVIDA - servicios/alertas.php
   Los 4 flujos de Telegram que antes vivían en n8n:
     flujoStockBajo()          alerta cuando quedan <= 5 unidades
     flujoInformeAgotados()    lista de productos agotados
     flujoCalificacionBaja()   chat calificado de 1 a 3
     flujoResumenDiario()      resumen de ventas del día (8 p.m.)

   Todos pasan por ejecutarFlujo(): reintentos + log en log_automatizaciones.
   Devuelven true/false y nunca rompen la web.
   Requiere config.php y automatizacion.php ya cargados.
   =========================================================== */
require_once __DIR__ . '/telegram.php';

/* ---- 2) Stock bajo. $alertas = [['producto' => 'X', 'stock' => 3], ...] ---- */
function flujoStockBajo(array $alertas) {
    if (empty($alertas)) return true;

    $lineas = [];
    foreach ($alertas as $a) {
        $lineas[] = '• ' . tg($a['producto']) . ': <b>' . (int)$a['stock'] . '</b> unidad(es)';
    }
    $mensaje = "⚠️ <b>Stock bajo en FarmaVida</b>\n\n" . implode("\n", $lineas) . "\n\nConviene reponer inventario.";

    return ejecutarFlujo('stock_bajo', ['productos' => $alertas], function () use ($mensaje) {
        return enviarTelegram($mensaje);
    });
}

/* ---- 3) Informe de agotados. $datos = ['total' => n, 'productos' => [...], 'fecha' => '...'] ---- */
function flujoInformeAgotados(array $datos) {
    $lista = array_map(function ($n) { return '• ' . tg($n); }, $datos['productos'] ?? []);
    $mensaje = "🚫 <b>Productos agotados</b> (" . (int)($datos['total'] ?? 0) . ")\n"
             . tg($datos['fecha'] ?? '') . "\n\n"
             . (empty($lista) ? 'Ninguno.' : implode("\n", $lista));

    // En el log solo guardamos cantidad y fecha (la lista ya está en la tabla productos)
    return ejecutarFlujo('informe_agotados',
        ['total' => (int)($datos['total'] ?? 0), 'fecha' => $datos['fecha'] ?? ''],
        function () use ($mensaje) { return enviarTelegram($mensaje); });
}

/* ---- 5) Calificación baja. La transcripción NO se guarda en el log (puede tener datos personales). ---- */
function flujoCalificacionBaja(array $datos) {
    $mensaje = "⭐ <b>Calificación baja: " . (int)$datos['calificacion'] . "/5</b>\n"
             . "Conversación #" . (int)$datos['conversacion_id']
             . " · " . tg($datos['fecha']) . " · " . (int)$datos['total_mensajes'] . " mensajes\n\n"
             . "<pre>" . tg($datos['transcripcion']) . "</pre>";

    return ejecutarFlujo('calificacion_baja',
        ['conversacion_id' => (int)$datos['conversacion_id'], 'calificacion' => (int)$datos['calificacion']],
        function () use ($mensaje) { return enviarTelegram($mensaje); });
}

/* ---- 4) Resumen diario ---- */
function flujoResumenDiario(array $d) {
    if ((int)$d['compras'] === 0) {
        $mensaje = "📊 <b>Resumen de ventas — " . tg($d['fecha']) . "</b>\n\nHoy no hubo ventas.";
    } else {
        $mensaje = "📊 <b>Resumen de ventas — " . tg($d['fecha']) . "</b>\n\n"
                 . "🛒 Compras: <b>" . (int)$d['compras'] . "</b>\n"
                 . "📦 Unidades vendidas: <b>" . (int)$d['unidades'] . "</b>\n"
                 . "💰 Total: <b>$" . tg($d['total_formateado']) . "</b>\n"
                 . "🏆 Más vendido: <b>" . tg($d['mas_vendido'] ?? '—') . "</b> (" . (int)$d['unidades_top'] . " u.)";
    }

    return ejecutarFlujo('resumen_diario', $d, function () use ($mensaje) {
        return enviarTelegram($mensaje);
    });
}
