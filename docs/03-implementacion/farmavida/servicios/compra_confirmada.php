<?php
/* ===========================================================
   FARMAVIDA - servicios/compra_confirmada.php
   AUTOMATIZACIÓN 1: correo de confirmación de compra.
   Reemplaza el webhook 'compra-confirmada' de n8n.

   Datos de entrada ($d):
     nombre, correo, items[ {nombre, cantidad, precio, subtotal} ],
     total, idioma ('es'|'en'), fecha
   =========================================================== */
require_once __DIR__ . '/correo.php';

function pesos($valor) {
    return '$' . number_format((float)$valor, 0, ',', '.');
}

/* Arma asunto, HTML y texto plano del correo (en español o inglés). */
function construirCorreoCompra(array $d) {
    $en = (($d['idioma'] ?? 'es') === 'en');
    $h  = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    $T = $en
        ? ['asunto' => 'Your FarmaVida purchase is confirmed', 'hola' => 'Hi', 'intro' => 'Thank you for your purchase! Here is your order summary:',
           'prod' => 'Product', 'cant' => 'Qty', 'precio' => 'Price', 'sub' => 'Subtotal', 'total' => 'Total',
           'fecha' => 'Date', 'cierre' => 'If you have any questions, reply through our chat on the website.']
        : ['asunto' => 'Tu compra en FarmaVida fue confirmada', 'hola' => 'Hola', 'intro' => '¡Gracias por tu compra! Este es el resumen de tu pedido:',
           'prod' => 'Producto', 'cant' => 'Cant.', 'precio' => 'Precio', 'sub' => 'Subtotal', 'total' => 'Total',
           'fecha' => 'Fecha', 'cierre' => 'Si tienes dudas, escríbenos por el chat de la página.'];

    $filas = '';
    $texto = "{$T['hola']} {$d['nombre']},\n{$T['intro']}\n\n";
    foreach ($d['items'] as $it) {
        $filas .= '<tr>'
            . '<td style="padding:8px;border-bottom:1px solid #e5e5e5;">' . $h($it['nombre']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #e5e5e5;text-align:center;">' . (int)$it['cantidad'] . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #e5e5e5;text-align:right;">' . pesos($it['precio']) . '</td>'
            . '<td style="padding:8px;border-bottom:1px solid #e5e5e5;text-align:right;">' . pesos($it['subtotal']) . '</td>'
            . '</tr>';
        $texto .= "- {$it['nombre']} x{$it['cantidad']}  " . pesos($it['subtotal']) . "\n";
    }
    $texto .= "\n{$T['total']}: " . pesos($d['total']) . "\n{$T['fecha']}: {$d['fecha']}\n\n{$T['cierre']}\nFarmaVida";

    $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;color:#222;">'
        . '<h2 style="color:#1b7f5c;">Farma<span style="color:#222;">Vida</span></h2>'
        . '<p>' . $T['hola'] . ' <strong>' . $h($d['nombre']) . '</strong>,</p>'
        . '<p>' . $T['intro'] . '</p>'
        . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
        . '<tr style="background:#f2f7f5;"><th style="padding:8px;text-align:left;">' . $T['prod'] . '</th>'
        . '<th style="padding:8px;">' . $T['cant'] . '</th>'
        . '<th style="padding:8px;text-align:right;">' . $T['precio'] . '</th>'
        . '<th style="padding:8px;text-align:right;">' . $T['sub'] . '</th></tr>'
        . $filas
        . '<tr><td colspan="3" style="padding:10px;text-align:right;"><strong>' . $T['total'] . '</strong></td>'
        . '<td style="padding:10px;text-align:right;"><strong>' . pesos($d['total']) . '</strong></td></tr>'
        . '</table>'
        . '<p style="color:#666;font-size:13px;">' . $T['fecha'] . ': ' . $h($d['fecha']) . '</p>'
        . '<p>' . $T['cierre'] . '</p>'
        . '</div>';

    return [$T['asunto'], $html, $texto];
}

/* Ejecuta el flujo completo con reintentos y log. Devuelve true/false. */
function flujoCompraConfirmada(array $d) {
    $entradaLog = [
        'cliente_correo' => enmascararCorreo($d['correo'] ?? ''),
        'productos'      => count($d['items'] ?? []),
        'total'          => $d['total'] ?? 0,
        'idioma'         => $d['idioma'] ?? 'es',
    ];
    return ejecutarFlujo('compra_confirmada', $entradaLog, function () use ($d) {
        [$asunto, $html, $texto] = construirCorreoCompra($d);
        enviarCorreo($d['correo'], $d['nombre'], $asunto, $html, $texto);
        return 'Correo enviado a ' . enmascararCorreo($d['correo']);
    });
}
