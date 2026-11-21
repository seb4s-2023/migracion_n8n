<?php
/* ===========================================================
   FARMAVIDA - servicios/formula_alerta.php
   Flujo 9: cuando la lectura de una fórmula necesita atención humana,
   avisa al administrador por Telegram. Es el "disparador" del módulo
   de visión sobre el motor de automatización.

   OJO: el mensaje lleva SOLO conteos, nunca nombres de medicamentos
   (son datos de salud y Telegram es un tercero). El administrador ve
   el detalle y la foto en formulas_admin.php (requiere sesión).
   =========================================================== */
require_once __DIR__ . '/telegram.php';

function flujoFormulaRevision(array $d) {
    $c = $d['conteo'];
    return ejecutarFlujo(
        'formula_revision',
        ['formula_id' => $d['formula_id'], 'total' => $d['total'], 'conteo' => $c, 'dudosa' => $d['dudosa']],
        function () use ($d, $c) {
            $txt  = "<b>📋 Fórmula por revisar #" . (int)$d['formula_id'] . "</b>\n";
            $txt .= "Origen: " . tg($d['cliente']) . "\n";
            $txt .= "Medicamentos leídos: " . (int)$d['total'] . "\n";
            $txt .= "✅ Disponibles: " . (int)$c['disponible'] . "\n";
            if ($c['agotado'])                 $txt .= "⏳ Agotados: " . (int)$c['agotado'] . "\n";
            if ($c['concentracion_diferente']) $txt .= "⚠️ Otra concentración: " . (int)$c['concentracion_diferente'] . "\n";
            if ($c['no_encontrado'])           $txt .= "❌ No están en el catálogo: " . (int)$c['no_encontrado'] . "\n";
            if ($d['dudosa'])                  $txt .= "🔎 La lectura de la foto fue dudosa.\n";
            $url = rtrim((string)env('APP_URL', ''), '/');
            if ($url !== '') $txt .= "\nDetalle: " . $url . "/formulas_admin.php";
            return enviarTelegram($txt);
        },
        2
    );
}
