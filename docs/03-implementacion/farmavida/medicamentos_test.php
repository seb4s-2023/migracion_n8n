<?php
/* FARMAVIDA - tests/medicamentos_test.php
   Pruebas unitarias de servicios/medicamentos.php (no usan base de datos ni IA).
   Ejecutar:   php tests/medicamentos_test.php
   Sale con código 1 si alguna prueba falla (sirve para integración continua). */
require __DIR__ . '/../servicios/medicamentos.php';

$catalogo = [
    ['id' => 1, 'nombre' => 'Acetaminofén 500mg',   'precio' => 8900,  'cantidad_disponible' => 50],
    ['id' => 2, 'nombre' => 'Ibuprofeno 400mg',     'precio' => 9500,  'cantidad_disponible' => 50],
    ['id' => 3, 'nombre' => 'Vitamina C 1g',        'precio' => 12500, 'cantidad_disponible' => 40],
    ['id' => 4, 'nombre' => 'Termómetro digital',   'precio' => 24900, 'cantidad_disponible' => 15],
    ['id' => 5, 'nombre' => 'Naproxeno 500mg',      'precio' => 11000, 'cantidad_disponible' => 0],
];

$total = 0; $fallos = 0;
function probar($descripcion, $esperado, $obtenido) {
    global $total, $fallos;
    $total++;
    if ($esperado === $obtenido) { echo "  PASA   $descripcion\n"; return; }
    $fallos++;
    echo "  FALLA  $descripcion  (esperado: " . var_export($esperado, true) . ", obtenido: " . var_export($obtenido, true) . ")\n";
}
function estado($consulta) { global $catalogo; return medBuscarEnCatalogo($consulta, $catalogo); }

echo "Búsqueda en catálogo\n";
probar('U-01 Advil 400 mg -> disponible (ibuprofeno)', 'disponible', estado('Advil 400 mg')['estado']);
probar('U-01b ...y es el producto 2',                  2,            estado('Advil 400 mg')['producto']['id']);
probar('U-02 Paracetamol 1 g -> otra concentración',   'concentracion_diferente', estado('Paracetamol 1 g')['estado']);
probar('U-03 Loratadina 10 mg -> no encontrado',       'no_encontrado', estado('Loratadina 10 mg')['estado']);
probar('U-04 Paracetamol 500 mg -> disponible',        'disponible', estado('Paracetamol 500 mg')['estado']);
probar('U-05 acetaminofen 500mg (sin tilde)',          'disponible', estado('acetaminofen 500mg')['estado']);
probar('U-06 Acetaminofén sin concentración',          'disponible', estado('Acetaminofén')['estado']);
probar('U-07 Vitamina C 1000 mg (1 g = 1000 mg)',      'disponible', estado('Vitamina C 1000 mg')['estado']);
probar('U-08 Ibuprofen 400 mg (una letra de error)',   'disponible', estado('Ibuprofen 400 mg')['estado']);
probar('U-09 texto vacío -> no encontrado',            'no_encontrado', estado('')['estado']);
probar('U-09b solo números -> no encontrado',          'no_encontrado', estado('12345')['estado']);
probar('U-10 Naproxeno 500 mg (stock 0) -> agotado',   'agotado', estado('Naproxeno 500 mg')['estado']);
probar('U-10b Aleve 500 mg (marca) -> agotado',        'agotado', estado('Aleve 500 mg')['estado']);
probar('U-11 Ibuprofeno 800 mg -> otra concentración', 'concentracion_diferente', estado('Ibuprofeno 800 mg')['estado']);
probar('U-11b ...y ofrece 1 alternativa con stock',    1, count(estado('Ibuprofeno 800 mg')['alternativas']));
probar('U-12 Omeprazol 20 mg -> no encontrado',        'no_encontrado', estado('Omeprazol 20 mg')['estado']);

echo "\nNormalización y concentraciones\n";
probar('N-01 quita tildes y pasa a minúsculas', 'acetaminofen 500 mg', medNormalizar('ACETAMINOFÉN 500mg'));
probar('N-02 1 g se convierte a 1000 mg', [['valor' => 1000.0, 'unidad' => 'mg']], medConcentraciones(medNormalizar('Vitamina C 1g')));
probar('N-03 un número suelto no tiene unidad', [['valor' => 500.0, 'unidad' => null]], medConcentraciones(medNormalizar('Paracetamol 500')));

echo "\nResultado: " . ($total - $fallos) . " de $total pruebas pasaron.\n";
exit($fallos > 0 ? 1 : 0);
