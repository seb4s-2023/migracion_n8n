<?php
/* FARMAVIDA - listar_modelos.php
   Muestra los modelos de Gemini que TU clave puede usar para generar texto.
   Solo por línea de comandos:
     C:\xampp\php\php.exe listar_modelos.php
   BÓRRALO antes de desplegar. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Acceso no permitido.'); }

require __DIR__ . '/env.php';

$clave = env('GEMINI_API_KEY');
if (!$clave) { exit("Falta GEMINI_API_KEY en el .env\n"); }

$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?pageSize=100');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['x-goog-api-key: ' . $clave],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_SSL_VERIFYPEER => env('CURL_SSL_VERIFY', '1') !== '0',
]);
$resp   = curl_exec($ch);
$codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($resp === false) { exit("Sin conexión: $err\n"); }
$datos = json_decode($resp, true);
if ($codigo !== 200) { exit("HTTP $codigo: " . ($datos['error']['message'] ?? $resp) . "\n"); }

echo "Modelos que sirven para generar texto con tu clave:\n\n";
foreach ($datos['models'] ?? [] as $m) {
    if (in_array('generateContent', $m['supportedGenerationMethods'] ?? [], true)) {
        echo '  ', str_replace('models/', '', $m['name']), "\n";
    }
}
echo "\nCopia 2 o 3 nombres al .env, por ejemplo:\n";
echo "  GEMINI_MODEL=nombre_principal\n";
echo "  GEMINI_MODEL_RESPALDO=nombre_2,nombre_3\n";
