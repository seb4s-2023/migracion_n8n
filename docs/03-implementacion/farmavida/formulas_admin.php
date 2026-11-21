<?php
/* ===========================================================
   FARMAVIDA - formulas_admin.php
   Panel del administrador: fórmulas subidas (quién, cuándo, resultado),
   acceso a la foto y auditoría de las acciones de la IA.
   =========================================================== */
require 'config.php';
requerirAdmin();

$formulas = $conexion->query(
    "SELECT f.id, f.fecha, f.estado, f.proveedor, f.meds_leidos, f.meds_disponibles, f.tamano_kb, f.archivo,
            c.nombre AS cliente
     FROM formulas_subidas f
     LEFT JOIN clientes c ON c.id = f.cliente_id
     ORDER BY f.id DESC LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);

$auditoria = $conexion->query(
    "SELECT fecha, flujo, estado, intentos, duracion_ms, resultado
     FROM log_automatizaciones
     WHERE flujo IN ('chat_ia','generar_descripcion','formula_ia','formula_ia_resultado','formula_revision','ver_formula','borrar_formulas')
     ORDER BY id DESC LIMIT 60"
)->fetch_all(MYSQLI_ASSOC);

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fórmulas y auditoría de IA - FarmaVida</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <div class="nav-container">
    <div class="logo">Farma<span>Vida</span></div>
    <nav class="nav-links">
      <a href="reporte.php">Reporte</a>
      <a href="login.php?salir=1">Salir</a>
    </nav>
  </div>
</header>

<div class="reporte-header">
  <div class="section-inner">
    <h1>📋 Fórmulas y auditoría de IA</h1>
    <p>Datos de salud: acceso solo para administradores. Cada vez que abres una foto queda registrado.</p>
  </div>
</div>

<section>
  <div class="section-inner">
    <div class="panel">
      <h3>Fórmulas subidas (últimas 100)</h3>
      <?php if (!$formulas): ?>
        <p style="color:var(--texto-suave);">Todavía no se ha subido ninguna fórmula.</p>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="tabla-conv">
          <thead><tr><th>#</th><th>Fecha</th><th>Quién</th><th>Estado</th><th>Leídos</th><th>Disponibles</th><th>IA</th><th>KB</th><th>Foto</th></tr></thead>
          <tbody>
          <?php foreach ($formulas as $f): ?>
            <tr>
              <td><?= (int)$f['id'] ?></td>
              <td><?= h($f['fecha']) ?></td>
              <td><?= h($f['cliente'] ?? 'Visitante') ?></td>
              <td><?= h($f['estado']) ?></td>
              <td><?= h($f['meds_leidos'] ?? '-') ?></td>
              <td><?= h($f['meds_disponibles'] ?? '-') ?></td>
              <td><?= h($f['proveedor'] ?? '-') ?></td>
              <td><?= (int)$f['tamano_kb'] ?></td>
              <td><?= $f['archivo'] === 'BORRADO' ? 'Borrada' : '<a href="ver_formula.php?id=' . (int)$f['id'] . '" target="_blank" rel="noopener">Ver</a>' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="panel" style="margin-top:24px;">
      <h3>Auditoría de las acciones de la IA (últimas 60)</h3>
      <div style="overflow-x:auto;">
        <table class="tabla-conv">
          <thead><tr><th>Fecha</th><th>Flujo</th><th>Estado</th><th>Intentos</th><th>ms</th><th>Resultado</th></tr></thead>
          <tbody>
          <?php foreach ($auditoria as $a): ?>
            <tr>
              <td><?= h($a['fecha']) ?></td>
              <td><?= h($a['flujo']) ?></td>
              <td><?= $a['estado'] === 'ok' ? '✅ ok' : '❌ error' ?></td>
              <td><?= (int)$a['intentos'] ?></td>
              <td><?= h($a['duracion_ms']) ?></td>
              <td><?= h(mb_substr((string)$a['resultado'], 0, 90)) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>

</body>
</html>
