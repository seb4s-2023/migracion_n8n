<?php
require 'config.php';

// Traemos el catálogo desde la base de datos (tabla productos)
$productos = [];
$resProd = $conexion->query(
    "SELECT id, nombre, descripcion, categoria, precio, icono, imagen, cantidad_disponible
     FROM productos WHERE estado = 'activo' ORDER BY id"
);
while ($p = $resProd->fetch_assoc()) {
    $productos[] = $p;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FarmaVida - Tu farmacia de confianza</title>
<link rel="stylesheet" href="styles.css">
<link rel="stylesheet" href="chat_formula.css">
</head>
<body>

<!-- ===================== HEADER ===================== -->
<header>
  <div class="nav-container">
    <div class="logo">Farma<span>Vida</span></div>
    <nav class="nav-links">
      <a href="#productos" data-en="Products">Productos</a>
      <a href="#registro" data-en="Sign up">Registrarme</a>
      <a href="#nosotros" data-en="About us">Nosotros</a>
      <?php if (isset($_SESSION['cliente_id'])): ?>
        <a href="dashboard_cliente.php" class="admin-link" data-en="My account">Mi cuenta</a>
        <a href="login.php?salir=1" data-en="Log out">Salir</a>
      <?php elseif (isset($_SESSION['admin_id'])): ?>
        <a href="productos_admin.php" class="admin-link" data-en="Products">Productos</a>
        <a href="reporte.php" class="admin-link" data-en="Admin panel">Panel administración</a>
        <a href="login.php?salir=1" data-en="Log out">Salir</a>
      <?php else: ?>
        <a href="login.php" data-en="Log in">Iniciar sesión</a>
      <?php endif; ?>
      <button id="idioma-toggle" type="button" class="btn-idioma">
        <span class="globo">🌐</span><span id="idioma-texto">EN</span>
      </button>
    </nav>
  </div>
</header>

<!-- ===================== HERO ===================== -->
<section class="hero">
  <div class="hero-inner">
    <div class="hero-text">
      <div class="eyebrow" data-en="Online pharmacy">Farmacia online</div>
      <h1 data-en="Your medicine and health products, without leaving home">Tus medicamentos y productos de salud, sin salir de casa</h1>
      <p data-en="Home delivery in Bucaramanga and its metro area, 24-hour chat support, and pharmacists validating every prescription.">Domicilios en Bucaramanga y área metropolitana, atención por chat las 24 horas y químicos farmacéuticos validando cada fórmula.</p>
      <a href="#registro" class="btn btn-primary" data-en="Sign up as a customer">Registrarme como cliente</a>
      <a href="#productos" class="btn btn-secondary" data-en="View products">Ver productos</a>
    </div>
    <div class="hero-badge">
      <div class="stat">15 min</div>
      <div class="stat-label" data-en="Average chat response time">Tiempo promedio de respuesta del chat</div>
      <div class="stat">+2.400</div>
      <div class="stat-label" data-en="Customers served this year">Clientes atendidos este año</div>
    </div>
  </div>
</section>

<!-- ===================== PRODUCTOS ===================== -->
<section id="productos">
  <div class="section-inner">
    <h2 class="section-title" data-en="Our products">Nuestros productos</h2>
    <p class="section-sub" data-en="Catalog from the database. If you have an account, you can buy directly here.">Catálogo desde la base de datos. Si tienes cuenta, puedes comprar directamente aquí.</p>

    <div id="compra-mensaje" class="compra-mensaje" style="display:none;"></div>

    <div class="productos-grid">
      <?php foreach ($productos as $p): ?>
        <div class="producto-card">
          <?php if (!empty($p['imagen'])): ?>
            <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>"
                 style="width:100%; height:120px; object-fit:cover; border-radius:10px; margin-bottom:14px;"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
          <?php endif; ?>
          <div class="producto-icon" <?= !empty($p['imagen']) ? "style=\"display:none;\"" : '' ?>><?= htmlspecialchars($p['icono']) ?></div>
          <div style="font-size:0.72rem; color:var(--verde-oscuro); font-weight:700; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:4px;">
            <?= htmlspecialchars($p['categoria']) ?>
          </div>
          <h3><?= htmlspecialchars($p['nombre']) ?></h3>
          <p><?= htmlspecialchars($p['descripcion']) ?></p>
          <div class="producto-precio">
            <?= $p['precio'] > 0 ? 'Desde $' . number_format($p['precio'], 0, ',', '.') : 'Precio según fórmula' ?>
          </div>
          <?php if ($p['precio'] > 0 && (int)$p['cantidad_disponible'] > 0): ?>
            <button type="button" class="btn btn-primary btn-agregar-carrito" data-id="<?= (int)$p['id'] ?>" style="width:100%; margin-top:12px;" data-en="🛒 Add to cart">
              🛒 Agregar al carrito
            </button>
          <?php elseif ($p['precio'] > 0): ?>
            <button type="button" class="btn btn-secondary" style="width:100%; margin-top:12px;" disabled data-en="Out of stock">
              Sin disponibilidad
            </button>
          <?php else: ?>
            <a href="#" onclick="document.getElementById('chat-toggle').click(); return false;" class="btn btn-secondary" style="width:100%; margin-top:12px; text-align:center;" data-en="Ask via chat">
              Consultar por chat
            </a>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== REGISTRO DE CLIENTE ===================== -->
<?php if (!isset($_SESSION['cliente_id'])): ?>
<section id="registro" style="background:#FAFDFC;">
  <div class="section-inner">
    <h2 class="section-title" data-en="Sign up as a customer">Regístrate como cliente</h2>
    <p class="section-sub">
      <span data-en="To serve you better we ask for some information. We group it by sensitivity level, in accordance with Colombian Law 1581 of 2012 on personal data protection.">Para atenderte mejor pedimos algunos datos. Los agrupamos según su nivel de sensibilidad,
      conforme a la Ley 1581 de 2012 de protección de datos personales en Colombia.</span>
      <span data-en="Already have an account?">¿Ya tienes cuenta?</span> <a href="login.php" style="color:var(--verde-oscuro); font-weight:600;" data-en="Log in here">Inicia sesión aquí</a>.
    </p>

    <div class="registro-wrap">
      <form id="form-registro">
        <div class="form-grid">

          <!-- DATOS PÚBLICOS -->
          <div class="form-bloque">
            <h4 data-en="① Public data">① Datos públicos</h4>
            <p class="desc" data-en="Information with no access restriction: it doesn't affect your privacy if others know it.">Información sin restricción de acceso: no afecta tu intimidad si es conocida por otros.</p>
            <div class="campo">
              <label for="nombre" data-en="Full name">Nombre completo</label>
              <input type="text" id="nombre" required placeholder="Ej: Sebastián Rojas" data-en-placeholder="E.g: Sebastián Rojas">
            </div>
            <div class="campo">
              <label for="ciudad" data-en="City">Ciudad</label>
              <input type="text" id="ciudad" required placeholder="Ej: Bucaramanga" data-en-placeholder="E.g: Bucaramanga">
            </div>
          </div>

          <!-- DATOS SEMIPRIVADOS -->
          <div class="form-bloque">
            <h4 data-en="② Semi-private data">② Datos semiprivados</h4>
            <p class="desc" data-en="Not public, but may be requested for commercial purposes such as contacting you.">No son públicos, pero pueden solicitarse para fines comerciales como contactarte.</p>
            <div class="campo">
              <label for="telefono" data-en="Phone">Teléfono</label>
              <input type="tel" id="telefono" required placeholder="Ej: 3001234567" data-en-placeholder="E.g: 3001234567">
            </div>
            <div class="campo">
              <label for="correo" data-en="Email">Correo electrónico</label>
              <input type="email" id="correo" required placeholder="Ej: correo@ejemplo.com" data-en-placeholder="E.g: email@example.com">
            </div>
            <div class="campo">
              <label for="password" data-en="Password">Contraseña</label>
              <input type="password" id="password" required minlength="6" placeholder="Mínimo 6 caracteres" data-en-placeholder="At least 6 characters">
            </div>
          </div>

          <!-- DATOS PRIVADOS -->
          <div class="form-bloque">
            <h4 data-en="③ Private data">③ Datos privados</h4>
            <p class="desc" data-en="Only relevant to the company and you. They require your express authorization.">Solo le interesan a la empresa y a ti. Requieren tu autorización expresa.</p>
            <div class="campo">
              <label for="direccion" data-en="Shipping address">Dirección de envío</label>
              <input type="text" id="direccion" required placeholder="Ej: Cra 27 # 45-12" data-en-placeholder="E.g: Cra 27 # 45-12">
            </div>
            <div class="campo">
              <label for="documento" data-en="ID number">Número de documento</label>
              <input type="text" id="documento" required placeholder="Ej: 1005678234" data-en-placeholder="E.g: 1005678234">
            </div>
          </div>

          <!-- DATOS SENSIBLES -->
          <div class="form-bloque sensible">
            <h4 data-en="④ Sensitive data">④ Datos sensibles</h4>
            <p class="desc" data-en="Health data. It has reinforced protection: it's never used to discriminate against you or sold to third parties.">Datos de salud. Tienen protección reforzada: nunca se usan para discriminarte ni se venden a terceros.</p>
            <div class="campo">
              <label for="condicion-salud" data-en="Any relevant health condition? (optional)">¿Alguna condición de salud relevante? (opcional)</label>
              <input type="text" id="condicion-salud" placeholder="Ej: hipertensión, alergias" data-en-placeholder="E.g: hypertension, allergies">
            </div>
            <div class="campo">
              <label for="eps" data-en="Health insurance provider (EPS)">EPS</label>
              <select id="eps" required>
                <option value="">Selecciona...</option>
                <option>Nueva EPS</option>
                <option>Sura</option>
                <option>Sanitas</option>
                <option>Compensar</option>
                <option>Otra</option>
              </select>
            </div>
          </div>
        </div>

        <div class="aviso-privacidad">
          🔒 <strong data-en="Privacy notice:">Aviso de privacidad:</strong>
          <span data-en=" your sensitive data (column ④) is only collected with your explicit authorization, is stored with restricted access, and is never shared with third parties without your consent, in accordance with Law 1581 of 2012 and Decree 1377 of 2013."> tus datos sensibles (columna ④) solo se recolectan con tu
          autorización explícita, se almacenan con acceso restringido y nunca se comparten con terceros sin tu consentimiento,
          conforme a la Ley 1581 de 2012 y el Decreto 1377 de 2013.</span>
        </div>

        <label class="checkbox-consent">
          <input type="checkbox" required>
          <span data-en="I authorize the processing of my personal data, including sensitive health data, in accordance with FarmaVida's privacy policy.">Autorizo el tratamiento de mis datos personales, incluidos los datos sensibles de salud, de acuerdo con la política de privacidad de FarmaVida.</span>
        </label>

        <button type="submit" class="btn btn-primary" data-en="Complete registration">Completar registro</button>

        <div id="registro-confirmacion" data-en="✅ Registration successful! Taking you to your account...">✅ ¡Registro exitoso! Te estamos llevando a tu cuenta...</div>
      </form>
    </div>
  </div>
</section>
<?php else: ?>
<section id="registro" style="background:#FAFDFC;">
  <div class="section-inner">
    <div class="panel" style="max-width:520px;">
      <h3 data-en="✅ You already have an active account">✅ Ya tienes una cuenta activa</h3>
      <p style="color:var(--texto-suave); margin:10px 0 16px;" data-en="You're logged in. Check your data and purchases in your personal panel.">
        Estás conectado. Revisa tus datos y tus compras en tu panel personal.
      </p>
      <a href="dashboard_cliente.php" class="btn btn-primary" data-en="Go to my account">Ir a mi cuenta</a>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===================== NOSOTROS ===================== -->
<section id="nosotros">
  <div class="section-inner">
    <h2 class="section-title" data-en="About FarmaVida">Sobre FarmaVida</h2>
    <p class="section-sub" data-en="We are a pharmacy with in-person service and home delivery in Bucaramanga, committed to protecting our customers' data and providing fast, human support backed by online chat technology.">
      Somos una farmacia con atención presencial y domicilios en Bucaramanga, comprometida con la
      protección de los datos de nuestros clientes y con una atención rápida y humana, apoyada en
      tecnología de chat en línea.
    </p>
  </div>
</section>

<footer>
  <p><strong>FarmaVida</strong> · <span data-en="Academic e-commerce project with data protection">Proyecto académico de e-commerce con protección de datos</span></p>
  <p data-en="Bucaramanga, Colombia">Bucaramanga, Colombia</p>
</footer>

<!-- ===================== CARRITO ===================== -->
<button id="carrito-toggle" title="Ver mi carrito">
  🛒
  <span id="carrito-badge" class="carrito-badge" style="display:none;">0</span>
</button>

<div id="carrito-panel">
  <div class="carrito-header">
    <div class="titulo" data-en="My cart">Mi carrito</div>
    <button id="carrito-close">✕</button>
  </div>

  <div class="carrito-body" id="carrito-body">
    <div class="carrito-vacio" data-en="Your cart is empty. Add products from the catalog.">Tu carrito está vacío. Agrega productos desde el catálogo.</div>
  </div>

  <div class="carrito-footer">
    <div class="carrito-total">
      <span data-en="Total">Total</span>
      <span id="carrito-total">$0</span>
    </div>
    <button type="button" id="btn-confirmar-compra" class="btn btn-primary" style="width:100%;" disabled data-en="Confirm purchase">
      Confirmar compra
    </button>
  </div>
</div>

<!-- ===================== CHATBOT ===================== -->
<button id="chat-toggle">💬</button>

<div id="chat-panel">
  <div class="chat-header">
    <div>
      <div class="titulo" data-en="FarmaVida Support">Atención FarmaVida</div>
      <div class="estado" data-en="● Online">● En línea</div>
    </div>
    <button id="chat-close">✕</button>
  </div>

  <div class="chat-body" id="chat-body">
    <div class="msg bot" data-en="Hi! Welcome to FarmaVida 👋 How can I help you?">¡Hola! Bienvenido a FarmaVida 👋 ¿En qué te puedo ayudar?</div>
  </div>

  <div class="chat-quick">
    <button type="button" data-en="Hours">Horarios</button>
    <button type="button" data-en="Shipping">Envíos</button>
    <button type="button" data-en="Prices">Precios</button>
    <button type="button" data-en="Talk to an agent">Hablar con un asesor</button>
  </div>

  <div class="chat-rating" id="chat-rating-box" style="display:none;">
    <p data-en="Before closing, how would you rate the service?">Antes de cerrar, ¿cómo calificarías la atención?</p>
    <div class="estrellas">
      <span data-valor="1">★</span>
      <span data-valor="2">★</span>
      <span data-valor="3">★</span>
      <span data-valor="4">★</span>
      <span data-valor="5">★</span>
    </div>
  </div>

  <form class="chat-input" id="chat-form">
    <button type="button" id="chat-adjuntar" title="Subir foto de fórmula" aria-label="Subir foto de fórmula">📎</button>
    <input type="file" id="chat-archivo" accept="image/*" hidden>
    <input type="text" id="chat-input" placeholder="Escribe tu mensaje..." autocomplete="off" data-en-placeholder="Type your message...">
    <button type="submit" data-en="Send">Enviar</button>
  </form>
</div>

<script>
  // El JS necesita saber si hay sesión de cliente para permitir comprar
  window.CLIENTE_LOGUEADO = <?= isset($_SESSION['cliente_id']) ? 'true' : 'false' ?>;
</script>
<script src="script.js"></script>
<script src="chat_formula.js"></script>
<script src="idioma.js"></script>
</body>
</html>