<?php
/* FARMAVIDA - prueba_medicamentos.php
   Prueba el buscador de medicamentos SIN base de datos ni IA, con un
   catálogo de ejemplo en memoria. Solo por línea de comandos:
     C:\xampp\php\php.exe prueba_medicamentos.php
   BÓRRALO antes de desplegar. */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Acceso no permitido.'); }

require __DIR__ . '/servicios/medicamentos.php';

$catalogo = [
    ['id' => 1, 'nombre' => 'Acetaminofén 500mg',        'precio' => 8900,  'cantidad_disponible' => 50],
    ['id' => 2, 'nombre' => 'Ibuprofeno 400mg',          'precio' => 9500,  'cantidad_disponible' => 50],
    ['id' => 3, 'nombre' => 'Vitamina C 1g',             'precio' => 12500, 'cantidad_disponible' => 40],
    ['id' => 4, 'nombre' => 'Multivitamínico adultos',   'precio' => 34900, 'cantidad_disponible' => 40],
    ['id' => 5, 'nombre' => 'Loratadina 10mg',           'precio' => 6000,  'cantidad_disponible' => 0],
    ['id' => 6, 'nombre' => 'Ibuprofeno 800mg',          'precio' => 14000, 'cantidad_disponible' => 12],
];

// [consulta, estado esperado, id esperado del producto (o null)]
$casos = [
    ['Acetaminofén 500mg',            'disponible',              1],
    ['paracetamol 500 mg',            'disponible',              1],
    ['Dolex 500',                     'disponible',              1],
    ['acetaminofen 500mg tabletas para el dolor', 'disponible',  1],
    ['Acetaminofen',                  'disponible',              1],   // sin concentración
    ['Acetaminofén 1000mg',           'concentracion_diferente', null],
    ['Ibuprofeno 800 mg',             'disponible',              6],
    ['ibuprofeno 600mg',              'concentracion_diferente', null],
    ['Ibuprofeno 4OOmg',              'concentracion_diferente', null], // "O" en vez de 0: falla de forma segura (no afirma que hay)
    ['Acetaminofen 5OO mg',           'concentracion_diferente', null],
    ['Vit C 1000mg',                  'disponible',              3],
    ['vitamina d 1000 ui',            'no_encontrado',           null], // "d" ≠ "c"
    ['Loratadina 10mg',               'agotado',                 5],
    ['Amoxicilina 500mg',             'no_encontrado',           null],
    ['Acetaminofen 500mg',            'disponible',              1],
    ['Acetaminofin 500mg',            'disponible',              1],   // error de ortografía / OCR
    ['multivitaminico',               'disponible',              4],
    ['',                              'no_encontrado',           null],
];

$ok = 0;
foreach ($casos as [$consulta, $estadoEsp, $idEsp]) {
    $r  = medBuscarEnCatalogo($consulta, $catalogo);
    $id = $r['producto']['id'] ?? null;
    $bien = ($r['estado'] === $estadoEsp && $id === $idEsp);
    if ($bien) $ok++;
    printf("%s %-46s -> %-24s %s\n", $bien ? 'OK   ' : 'FALLA', '"' . $consulta . '"', $r['estado'],
        $id ? '(' . $r['producto']['nombre'] . ')' : ($r['alternativas'] ? '[hay: ' . implode(', ', array_column($r['alternativas'], 'nombre')) . ']' : ''));
}
printf("\n%d de %d casos correctos\n", $ok, count($casos));
exit($ok === count($casos) ? 0 : 1);
