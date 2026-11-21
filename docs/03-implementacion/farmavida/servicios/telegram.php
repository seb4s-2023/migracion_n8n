<?php
/* ===========================================================
   FARMAVIDA - servicios/telegram.php
   Envío de mensajes al administrador por Telegram (reemplaza a n8n).

   Variables en el .env:
     TELEGRAM_BOT_TOKEN=token_del_bot        (lo da @BotFather)
     TELEGRAM_CHAT_ID=id_del_chat_del_admin
     CURL_SSL_VERIFY=0                        (solo XAMPP local si falla el SSL)

   Lanza excepción si falla, para que ejecutarFlujo() reintente y registre.
   =========================================================== */
require_once __DIR__ . '/../env.php';

function enviarTelegram($textoHtml) {
    $token = env('TELEGRAM_BOT_TOKEN');
    $chat  = env('TELEGRAM_CHAT_ID');
    if (!$token || !$chat) {
        throw new RuntimeException('Faltan TELEGRAM_BOT_TOKEN o TELEGRAM_CHAT_ID en el .env');
    }

    $ch = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'chat_id'                  => $chat,
            'text'                     => mb_substr($textoHtml, 0, 4000),   // límite de Telegram: 4096
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => 'true',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => env('CURL_SSL_VERIFY', '1') !== '0',
    ]);
    $resp   = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException('Sin conexión con Telegram: ' . $err);
    }
    $datos = json_decode($resp, true);
    if ($codigo !== 200 || empty($datos['ok'])) {
        throw new RuntimeException("Telegram HTTP $codigo: " . ($datos['description'] ?? mb_substr($resp, 0, 150)));
    }
    return 'enviado (message_id ' . ($datos['result']['message_id'] ?? '?') . ')';
}

/* Escapa texto de usuario/productos para el modo HTML de Telegram */
function tg($texto) {
    return htmlspecialchars((string)$texto, ENT_NOQUOTES, 'UTF-8');
}
