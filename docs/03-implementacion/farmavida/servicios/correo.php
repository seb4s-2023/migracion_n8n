<?php
/* ===========================================================
   FARMAVIDA - servicios/correo.php
   Envío de correos con PHPMailer + SMTP de Gmail.
   Instala PHPMailer en libs/PHPMailer/src/ (ver instrucciones).
   =========================================================== */
use PHPMailer\PHPMailer\PHPMailer;

/* Lanza una excepción si algo falla (así ejecutarFlujo() reintenta). */
function enviarCorreo($paraCorreo, $paraNombre, $asunto, $html, $texto = '') {
    // PHPMailer se carga SOLO al enviar, así que si falta la librería
    // falla únicamente el correo (queda en el log) y la web sigue funcionando.
    $base = __DIR__ . '/../libs/PHPMailer/src/';
    if (!is_readable($base . 'PHPMailer.php')) {
        throw new RuntimeException('PHPMailer no está instalado en libs/PHPMailer/src/');
    }
    require_once $base . 'Exception.php';
    require_once $base . 'PHPMailer.php';
    require_once $base . 'SMTP.php';

    $usuario = env('SMTP_USER');
    $clave   = env('SMTP_PASS');
    if (!$usuario || !$clave) {
        throw new RuntimeException('Falta SMTP_USER o SMTP_PASS en el archivo .env');
    }
    if (!filter_var($paraCorreo, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Correo del destinatario inválido');
    }

    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = env('SMTP_HOST', 'smtp.gmail.com');
    $m->SMTPAuth   = true;
    $m->Username   = $usuario;
    $m->Password   = $clave;
    $m->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $m->Port       = (int)env('SMTP_PORT', 587);
    $m->Timeout    = 10;
    $m->CharSet    = 'UTF-8';

    $m->setFrom($usuario, env('SMTP_FROM_NAME', 'FarmaVida'));
    $m->addAddress($paraCorreo, $paraNombre);
    $m->isHTML(true);
    $m->Subject = $asunto;
    $m->Body    = $html;
    $m->AltBody = $texto !== '' ? $texto : strip_tags($html);

    $m->send();
    return true;
}