<?php
/* ===========================================================
   FARMAVIDA - servicios/medicamentos.php
   Paso 1 del módulo de fórmulas/fotos: dado el NOMBRE de un
   medicamento (venga de lo que escribe el cliente o, más adelante,
   de lo que lea la IA en una foto), responde si lo tenemos.

   NO usa IA ni imágenes: solo compara texto contra la tabla
   productos. Así se puede probar sin gastar cuota de Gemini y los
   errores de la visión se separan de los errores de búsqueda.

   Estados posibles por cada medicamento consultado:
     disponible            -> existe (con esa concentración, si se indicó) y hay stock
     agotado               -> existe pero cantidad_disponible = 0
     concentracion_diferente -> existe el medicamento, pero no con esa concentración
     no_encontrado         -> no está en el catálogo activo

   Funciones principales:
     medBuscarEnCatalogo($consulta, $catalogo)   (pura, sin base de datos)
     medBuscarVarios($conexion, $nombres)        (carga el catálogo y busca varios)
   =========================================================== */

/* ---- Marcas / nombres alternos -> principio activo tal como está en el catálogo.
        Amplía esta lista con los productos reales de la farmacia. ---- */
function medSinonimos() {
    return [
        '/\b(paracetamol|acetaminophen|dolex|tylenol)\b/' => 'acetaminofen',
        '/\b(advil|motrin|brufen)\b/'                     => 'ibuprofeno',
        '/\b(acido ascorbico|vit\.? ?c)\b/'               => 'vitamina c',
        '/\b(aleve)\b/'                                   => 'naproxeno',
        '/\b(voltaren)\b/'                                => 'diclofenaco',
        '/\b(claritin)\b/'                                => 'loratadina',
    ];
}

/* Palabras que no identifican el medicamento (presentación, empaque, relleno). */
function medPalabrasVacias() {
    return ['de','del','la','el','en','y','con','para','por','x','caja','cajas','frasco','tubo',
            'tableta','tabletas','tab','capsula','capsulas','comprimido','comprimidos','sobre','sobres',
            'efervescente','efervescentes','mg','g','ml','mcg','ug','ui','und','unidades','unidad','cada','horas','dias'];
}

/* ---- Minúsculas, sin tildes, sinónimos aplicados, solo letras/números ---- */
function medNormalizar($texto) {
    $t = mb_strtolower((string)$texto, 'UTF-8');
    $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    foreach (medSinonimos() as $patron => $canonico) {
        $t = preg_replace($patron, $canonico, $t);
    }
    $t = preg_replace('/(\d)([a-z])/', '$1 $2', $t);                 // "500mg" -> "500 mg"
    $t = preg_replace('/[^a-z0-9.,]+/', ' ', $t);                    // quita símbolos
    $t = preg_replace('/(?<!\d)[.,]|[.,](?!\d)/', ' ', $t);          // deja . y , solo entre dígitos
    return trim(preg_replace('/\s+/', ' ', $t));
}

/* ---- Concentraciones: [['valor' => 500.0, 'unidad' => 'mg'|'ml'|'ui'|null], ...]
        g y mcg se pasan a mg para poder comparar "1 g" con "1000 mg".
        Un número suelto ("paracetamol 500") se guarda con unidad null. ---- */
function medConcentraciones($normalizado) {
    $out = [];
    $sinUnidad = $normalizado;
    if (preg_match_all('/(\d+(?:[.,]\d+)?) ?(mg|g|mcg|ug|ml|ui)\b/', $normalizado, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $v = (float)str_replace(',', '.', $x[1]);
            $u = $x[2];
            if ($u === 'g')                     { $v *= 1000; $u = 'mg'; }
            elseif ($u === 'mcg' || $u === 'ug') { $v /= 1000; $u = 'mg'; }
            $out[] = ['valor' => round($v, 3), 'unidad' => $u];
            $sinUnidad = str_replace($x[0], ' ', $sinUnidad);
        }
    }
    if (!$out && preg_match_all('/\b(\d+(?:[.,]\d+)?)\b/', $sinUnidad, $m2)) {
        foreach ($m2[1] as $n) {
            $out[] = ['valor' => round((float)str_replace(',', '.', $n), 3), 'unidad' => null];
        }
    }
    return $out;
}

/* ---- Palabras que identifican al medicamento (sin números ni relleno) ---- */
function medTokens($normalizado) {
    $vacias = medPalabrasVacias();
    $tokens = [];
    foreach (explode(' ', $normalizado) as $p) {
        if ($p === '' || is_numeric(str_replace(',', '.', $p)) || in_array($p, $vacias, true)) continue;
        $tokens[$p] = true;
    }
    return array_keys($tokens);
}

/* ---- ¿Dos palabras son "la misma"? Tolera 1-2 letras de error (típico de OCR/IA). ---- */
function medPalabraIgual($a, $b) {
    if ($a === $b) return true;
    $la = strlen($a); $lb = strlen($b);
    if ($la < 5 || $lb < 5) return false;                 // palabras cortas ("c", "d"): exactas
    $tolerancia = min($la, $lb) >= 9 ? 2 : 1;
    return levenshtein($a, $b) <= $tolerancia;
}

/* ¿Todas las palabras de $A aparecen (aprox.) en $B? */
function medContenidas(array $A, array $B) {
    if (!$A) return false;
    foreach ($A as $a) {
        $hallada = false;
        foreach ($B as $b) { if (medPalabraIgual($a, $b)) { $hallada = true; break; } }
        if (!$hallada) return false;
    }
    return true;
}

function medConcentracionCoincide(array $pedidas, array $delProducto) {
    // Devuelve 'si' | 'no' | 'desconocida' (el producto no trae concentración en el nombre)
    if (!$delProducto) return 'desconocida';
    foreach ($pedidas as $q) {
        foreach ($delProducto as $p) {
            if (abs($q['valor'] - $p['valor']) < 0.0005 && ($q['unidad'] === null || $q['unidad'] === $p['unidad'])) {
                return 'si';
            }
        }
    }
    return 'no';
}

function medFormatearProducto(array $p) {
    return [
        'id'         => (int)$p['id'],
        'nombre'     => $p['nombre'],
        'precio'     => (int)$p['precio'],
        'cantidad'   => (int)$p['cantidad_disponible'],
        'disponible' => (int)$p['cantidad_disponible'] > 0,
    ];
}

/* ===========================================================
   Búsqueda pura (no toca la base de datos: fácil de probar)
   $catalogo = filas con id, nombre, precio, cantidad_disponible
   =========================================================== */
function medBuscarEnCatalogo($consulta, array $catalogo) {
    $base = ['consulta' => $consulta, 'estado' => 'no_encontrado', 'producto' => null, 'alternativas' => []];

    $q       = medNormalizar($consulta);
    $qTokens = medTokens($q);
    $qConc   = medConcentraciones($q);
    if (!$qTokens) return $base;                            // nada reconocible como medicamento

    // 1) Productos cuyo NOMBRE corresponde al medicamento pedido
    $candidatos = [];
    foreach ($catalogo as $prod) {
        $p       = medNormalizar($prod['nombre']);
        $pTokens = medTokens($p);
        if (!$pTokens) continue;

        // "Lo pedido está dentro del producto" o "el producto está dentro de lo pedido"
        if (!(medContenidas($qTokens, $pTokens) || medContenidas($pTokens, $qTokens))) continue;

        $comunes = 0;
        foreach ($qTokens as $a) foreach ($pTokens as $b) { if (medPalabraIgual($a, $b)) { $comunes++; break; } }
        $dice = (2 * $comunes) / (count($qTokens) + count($pTokens));

        $candidatos[] = [
            'prod'  => $prod,
            'dice'  => $dice,
            'conc'  => $qConc ? medConcentracionCoincide($qConc, medConcentraciones($p)) : 'desconocida',
        ];
    }
    if (!$candidatos) return $base;

    usort($candidatos, function ($x, $y) {
        return $y['dice'] <=> $x['dice'] ?: ((int)$y['prod']['cantidad_disponible'] <=> (int)$x['prod']['cantidad_disponible']);
    });

    // 2) Con la concentración pedida (si la hubo)
    $coinciden = $qConc
        ? array_values(array_filter($candidatos, function ($c) { return $c['conc'] !== 'no'; }))
        : $candidatos;

    if ($coinciden) {
        // Primero uno que tenga stock; si no, el mejor aunque esté agotado
        $elegido = $coinciden[0];
        foreach ($coinciden as $c) {
            if ((int)$c['prod']['cantidad_disponible'] > 0) { $elegido = $c; break; }
        }
        $base['producto'] = medFormatearProducto($elegido['prod']);
        $base['estado']   = $base['producto']['disponible'] ? 'disponible' : 'agotado';
        return $base;
    }

    // 3) Existe el medicamento, pero no con esa concentración: ofrecemos las que sí hay
    $base['estado'] = 'concentracion_diferente';
    foreach ($candidatos as $c) {
        if ((int)$c['prod']['cantidad_disponible'] > 0) $base['alternativas'][] = medFormatearProducto($c['prod']);
    }
    return $base;
}

/* ===========================================================
   Busca varios medicamentos (los que haya leído la IA o escrito el cliente).
   Carga el catálogo UNA vez. Excluye el producto genérico
   "Medicamento formulado" (categoría "Medicamentos formulados").
   =========================================================== */
function medBuscarVarios($conexion, array $nombres) {
    $catalogo = [];
    $res = $conexion->query(
        "SELECT id, nombre, precio, cantidad_disponible
         FROM productos
         WHERE estado = 'activo' AND categoria <> 'Medicamentos formulados'"
    );
    while ($f = $res->fetch_assoc()) $catalogo[] = $f;

    $resultados = [];
    foreach (array_slice($nombres, 0, 15) as $nombre) {        // tope: una fórmula rara vez pasa de 15
        $nombre = trim(mb_substr((string)$nombre, 0, 150));
        if ($nombre === '') continue;
        $resultados[] = medBuscarEnCatalogo($nombre, $catalogo);
    }
    return $resultados;
}
