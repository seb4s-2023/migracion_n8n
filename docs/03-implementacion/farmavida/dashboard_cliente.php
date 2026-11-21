<?php
require 'config.php';
requerirCliente();

$stmt = $conexion->prepare(
    "SELECT nombre, ciudad, telefono, correo, direccion, eps, fecha_registro FROM clientes WHERE id = ?"
);
$stmt->bind_param('i', $_SESSION['cliente_id']);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Historial de compras de este cliente
$stmtPedidos = $conexion->prepare(
    "SELECT pr.nombre, pe.precio, pe.fecha
     FROM pedidos pe
     JOIN productos pr ON pr.id = pe.producto_id
     WHERE pe.cliente_id = ?
     ORDER BY pe.fecha DESC"
);
$stmtPedidos->bind_param('i', $_SESSION['cliente_id']);
$stmtPedidos->execute();
$pedidos = $stmtPedidos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPedidos->close();

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi cuenta - FarmaVida</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <div class="nav-container">
    <div class="logo">Farma<span>Vida</span></div>
    <nav class="nav-links">
      <a href="index.php" data-en="Back to site">Volver al sitio</a>
      <a href="login.php?salir=1" data-en="Log out">Salir</a>
      <button id="idioma-toggle" type="button" class="btn-idioma">
        <span class="globo">🌐</span><span id="idioma-texto">EN</span>
      </button>
    </nav>
  </div>
</header>

<div class="reporte-header">
  <div class="section-inner">
    <h1 data-en="👤 My account">👤 Mi cuenta</h1>
    <p><span data-en="Hi,">Hola,</span> <?= htmlspecialchars($cliente['nombre']) ?></p>
  </div>
</div>

<section>
  <div class="section-inner">
    <div class="panel" style="max-width:520px;">
      <h3 data-en="My information">Mis datos</h3>
      <p><strong data-en="City:">Ciudad:</strong> <?= htmlspecialchars($cliente['ciudad']) ?></p>
      <p><strong data-en="Phone:">Teléfono:</strong> <?= htmlspecialchars($cliente['telefono']) ?></p>
      <p><strong data-en="Email:">Correo:</strong> <?= htmlspecialchars($cliente['correo']) ?></p>
      <p><strong data-en="Address:">Dirección:</strong> <?= htmlspecialchars($cliente['direccion']) ?></p>
      <p><strong data-en="Health insurance (EPS):">EPS:</strong> <?= htmlspecialchars($cliente['eps']) ?></p>
      <p><strong data-en="Customer since:">Cliente desde:</strong> <?= htmlspecialchars($cliente['fecha_registro']) ?></p>
    </div>

    <div class="panel" style="max-width:520px; margin-top:24px;">
      <h3 data-en="🛒 My purchases">🛒 Mis compras</h3>
      <?php if (empty($pedidos)): ?>
        <p style="color:var(--texto-suave); font-size:0.9rem;" data-en="You haven't purchased any products yet.">Todavía no has comprado ningún producto.</p>
      <?php else: ?>
        <table class="tabla-conv">
          <thead><tr><th data-en="Product">Producto</th><th data-en="Price">Precio</th><th data-en="Date">Fecha</th></tr></thead>
          <tbody>
            <?php foreach ($pedidos as $ped): ?>
              <tr>
                <td><?= htmlspecialchars($ped['nombre']) ?></td>
                <td>$<?= number_format($ped['precio'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars(date('d/m/Y', strtotime($ped['fecha']))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <div class="panel" style="max-width:520px; margin-top:24px;">
      <h3 data-en="💬 Need help?">💬 ¿Necesitas ayuda?</h3>
      <p style="color:var(--texto-suave); margin-bottom:14px;" data-en="Use the customer support chat on the main page.">
        Usa el chat de atención al cliente en la página principal.
      </p>
      <a href="index.php" class="btn btn-primary" data-en="Go to chat">Ir al chat</a>
    </div>
  </div>
</section>

<footer>
  <p><strong>FarmaVida</strong> · <span data-en="My account">Mi cuenta</span></p>
</footer>

<script src="idioma.js"></script>
</body>
</html>