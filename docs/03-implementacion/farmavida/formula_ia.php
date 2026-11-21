<?php
/* ===========================================================
   FARMAVIDA - formula_ia.php
   Flujo 7: el cliente sube la foto de su fórmula en el chat; la IA lee
   los medicamentos (servicios/vision.php) y se consulta el catálogo
   (servicios/medicamentos.php). NO guarda nada en el carrito: solo
   informa; el cliente decide qué agregar.
   POST multipart: imagen (archivo), autorizo=1
   =========================================================== */
/* ---- Siempre responder JSON, aunque algo explote (tabla faltante, archivo faltante, etc.) ----
   Con APP_DEBUG=1 en el .env, la respuesta incluye el motivo real del error. */
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
set_exception_handler(function ($e) {
    error_log('FarmaVida formula_ia: ' . $e->getMessage() . ' en ' . basename($e->getFile()) . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    $debug = function_exists('env') && env('APP_DEBUG', '0') === '1';
    echo json_encode([
        'error'   => true,
        'mensaje' => 'Error interno al procesar la fórmula.' . ($debug ? ' [' . $e->getMessage() . ']' : ''),
    ], JSON_UNESCAPED_UNICODE);
});

require 'config.php';
require 'automatizacion.php';
require 'servicios/vision.php';
require 'servicios/medicamentos.php';
require_once __DIR__ . '/servicios/telegram.php';

/* ---- Flujo 9: avisa al administrador por Telegram cuando una fórmula necesita revisión.
        Solo lleva conteos, nunca nombres de medicamentos (datos de salud). ---- */
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
header('Content-Type: application/json; charset=utf-8');

$en = (($_COOKIE['farmavida_idioma'] ?? 'es') === 'en');
function t($es, $en_) { global $en; return $en ? $en_ : $es; }
function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}
function fallo($msg, $codigo = 200) { responder(['error' => true, 'mensaje' => $msg], $codigo); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fallo(t('Método no permitido', 'Method not allowed'), 405);

/* ---- Autorización de tratamiento de datos (Ley 1581 de 2012) ---- */
if (($_POST['autorizo'] ?? '') !== '1') {
    fallo(t('Debes autorizar el tratamiento de tu fórmula para continuar.', 'You must authorize processing of your prescription to continue.'), 400);
}

/* ---- Límite anti-abuso (la cuota gratuita es limitada) ---- */
$ahora = time();
$_SESSION['formula_ultima'] = $_SESSION['formula_ultima'] ?? 0;
$_SESSION['formula_total']  = $_SESSION['formula_total']  ?? 0;
if ($ahora - $_SESSION['formula_ultima'] < 10 || $_SESSION['formula_total'] >= 8) {
    fallo(t('Espera unos segundos antes de subir otra foto o alcanzaste el límite de la sesión.', 'Please wait a few seconds before uploading another photo, or you reached the session limit.'));
}
$_SESSION['formula_ultima'] = $ahora;
$_SESSION['formula_total']++;
$clienteId = $_SESSION['cliente_id'] ?? null;
session_write_close();   // no bloquear otras peticiones mientras la IA lee la imagen

/* ---- Validación del archivo ---- */
$f = $_FILES['imagen'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
    fallo(t('No se recibió la imagen. Intenta de nuevo.', 'The image was not received. Please try again.'), 400);
}
if ($f['size'] > 4 * 1024 * 1024) {
    fallo(t('La imagen es muy pesada (máximo 4 MB).', 'The image is too large (4 MB max).'), 413);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
if (!$ext || @getimagesize($f['tmp_name']) === false) {
    fallo(t('Solo se aceptan fotos JPG, PNG o WEBP.', 'Only JPG, PNG or WEBP photos are accepted.'), 415);
}
$binario = file_get_contents($f['tmp_name']);

/* ---- Guardado con control de acceso: fuera del alcance público + registro de quién/cuándo ---- */
$dir = rtrim(env('FORMULAS_DIR', __DIR__ . '/storage/formulas'), '/\\');
if (!is_dir($dir)) @mkdir($dir, 0750, true);
$archivo = bin2hex(random_bytes(16)) . '.' . $ext;
if (@file_put_contents($dir . '/' . $archivo, $binario) === false) {
    fallo(t('No se pudo procesar la imagen en este momento.', 'The image could not be processed right now.'), 500);
}
$kb = (int)round(strlen($binario) / 1024);

$stmt = $conexion->prepare("INSERT INTO formulas_subidas (cliente_id, archivo, mime, tamano_kb) VALUES (?, ?, ?, ?)");
$stmt->bind_param('issi', $clienteId, $archivo, $mime, $kb);
$stmt->execute();
$formulaId = $stmt->insert_id;
$stmt->close();

function cerrarRegistro($conexion, $id, $estado, $proveedor, $leidos, $disp) {
    $s = $conexion->prepare("UPDATE formulas_subidas SET estado = ?, proveedor = ?, meds_leidos = ?, meds_disponibles = ? WHERE id = ?");
    $s->bind_param('ssiii', $estado, $proveedor, $leidos, $disp, $id);
    $s->execute();
    $s->close();
}

/* ---- Lectura con IA (reintentos + log). Si falla: plan B para el cliente ---- */
$lectura = null;
$ok = ejecutarFlujo(
    'formula_ia',
    ['formula_id' => $formulaId, 'mime' => $mime, 'kb' => $kb, 'cliente' => $clienteId ? 'logueado' : 'anonimo'],
    function () use ($binario, $mime, &$lectura) {
        $lectura = iaLeerFormula($binario, $mime);
        // El log NO guarda nombres de medicamentos (datos de salud): solo conteos
        return $lectura['proveedor'] . ': ' . count($lectura['medicamentos']) . ' medicamentos, confianza ' . $lectura['confianza_global'];
    },
    2
);

if (!$ok || !$lectura) {
    cerrarRegistro($conexion, $formulaId, 'fallo_ia', null, null, null);
    fallo(t('Ahora mismo no pude leer la foto. Puedes escribirme el nombre del medicamento en el chat o intentarlo de nuevo en unos minutos.',
            'I could not read the photo right now. You can type the medication name in the chat or try again in a few minutes.'));
}

if (!$lectura['es_formula'] || !$lectura['legible'] || !$lectura['medicamentos']) {
    cerrarRegistro($conexion, $formulaId, 'ilegible', $lectura['proveedor'], 0, 0);
    responder(['error' => false, 'legible' => false,
        'mensaje' => t('No logré leer medicamentos en la foto. Intenta con buena luz, toda la fórmula dentro del encuadre y sin reflejos.',
                       'I could not read any medications in the photo. Try with good light, the whole prescription in frame and no glare.')]);
}

/* ---- Cruce con el catálogo (paso 1) ---- */
$nombres = [];
foreach ($lectura['medicamentos'] as $m) {
    $nombres[] = trim($m['nombre'] . ' ' . $m['concentracion']);
}
$busquedas = medBuscarVarios($conexion, $nombres);

$items = [];
$disponibles = 0;
$hayDudas = $lectura['confianza_global'] < 0.6;
foreach ($lectura['medicamentos'] as $i => $m) {
    $b = $busquedas[$i] ?? ['estado' => 'no_encontrado', 'producto' => null, 'alternativas' => []];
    if ($b['estado'] === 'disponible') $disponibles++;
    $dudoso = $m['confianza'] < 0.6;
    if ($dudoso) $hayDudas = true;
    $items[] = [
        'leido'         => $m['nombre'],
        'concentracion' => $m['concentracion'],
        'confianza'     => $m['confianza'],
        'dudoso'        => $dudoso,
        'estado'        => $b['estado'],
        'producto'      => $b['producto'],
        'alternativas'  => $b['alternativas'],
    ];
}

cerrarRegistro($conexion, $formulaId, 'leida', $lectura['proveedor'], count($items), $disponibles);

/* ---- Disparador: la visión pone en marcha un proceso de negocio ----
   Si algo requiere atención humana (no hay stock, no existe, otra concentración o lectura dudosa),
   se avisa al administrador por Telegram DESPUÉS de responderle al cliente. */
$conteo = ['disponible' => 0, 'agotado' => 0, 'concentracion_diferente' => 0, 'no_encontrado' => 0];
foreach ($items as $it) { $conteo[$it['estado']] = ($conteo[$it['estado']] ?? 0) + 1; }
if ($hayDudas || $conteo['agotado'] || $conteo['concentracion_diferente'] || $conteo['no_encontrado']) {
    $datosAviso = ['formula_id' => $formulaId, 'total' => count($items), 'conteo' => $conteo,
                   'dudosa' => $hayDudas, 'cliente' => $clienteId ? 'cliente con sesión' : 'visitante sin sesión'];
    despuesDeResponder(function () use ($datosAviso) { flujoFormulaRevision($datosAviso); });
}
logFlujo('formula_ia_resultado', 'ok', 1, ['formula_id' => $formulaId], "leidos=" . count($items) . " disponibles=$disponibles revision=" . ($hayDudas ? 'si' : 'no'), 0);

responder([
    'error'            => false,
    'legible'          => true,
    'confianza_global' => $lectura['confianza_global'],
    'requiere_revision'=> $hayDudas,      // si es true, el front pide al cliente confirmar lo leído
    'items'            => $items,
]);