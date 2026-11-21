<?php
/* ===========================================================
   FARMAVIDA - productos_admin.php
   Panel de administración de productos (CRUD).
   Solo accesible para administradores logueados.

   Toda la lógica de datos (crear/editar/eliminar/listar) vive
   en api.php (acciones producto_crear, producto_editar,
   producto_eliminar, producto_listar). Esta página solo pinta
   la tabla y el formulario, y habla con api.php vía fetch()
   desde productos_admin.js.
   =========================================================== */
require 'config.php';
requerirAdmin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de productos - FarmaVida</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>

<header>
  <div class="nav-container">
    <div class="logo">Farma<span>Vida</span></div>
    <nav class="nav-links">
      <span style="font-size:0.85rem; color:var(--texto-suave);"><span data-en="Hi,">Hola,</span> <?= htmlspecialchars($_SESSION['admin_nombre']) ?></span>
      <a href="reporte.php" data-en="Report">Reporte</a>
      <a href="index.php" data-en="← Back to site">← Volver al sitio</a>
      <a href="login.php?salir=1" data-en="Log out">Salir</a>
      <button id="idioma-toggle" type="button" class="btn-idioma">
        <span class="globo">🌐</span><span id="idioma-texto">EN</span>
      </button>
    </nav>
  </div>
</header>

<div class="reporte-header">
  <div class="section-inner">
    <h1 data-en="📦 Product management">📦 Gestión de productos</h1>
    <p data-en="Create, edit and delete products in FarmaVida's catalog.">Crea, edita y elimina los productos del catálogo de FarmaVida.</p>
  </div>
</div>

<section style="padding-top:30px;">
  <div class="section-inner">

    <div id="prod-mensaje" class="compra-mensaje" style="display:none;"></div>

    <!-- ===================== FORMULARIO CREAR/EDITAR ===================== -->
    <div class="panel" style="margin-bottom:28px;">
      <h3 id="form-titulo" data-en="➕ New product">➕ Nuevo producto</h3>

      <form id="form-producto">
        <input type="hidden" id="prod-id" value="">

        <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
          <div class="form-bloque">
            <div class="campo">
              <label for="prod-nombre" data-en="Name">Nombre</label>
              <input type="text" id="prod-nombre" required placeholder="Ej: Acetaminofén 500mg" data-en-placeholder="E.g: Acetaminophen 500mg">
            </div>
            <div class="campo">
              <label for="prod-descripcion" data-en="Description">Descripción</label>
              <input type="text" id="prod-descripcion" required maxlength="255" placeholder="Ej: Caja x 20 tabletas, para dolor y fiebre." data-en-placeholder="E.g: Box of 20 tablets, for pain and fever.">
              <button type="button" class="btn-reset" id="btn-generar-ia" style="margin-top:8px;" data-en="✨ Generate with AI">✨ Generar con IA</button>
              <small id="ia-estado" style="display:block; margin-top:6px; color:var(--texto-suave);"></small>
            </div>
            <div class="campo">
              <label for="prod-categoria" data-en="Category">Categoría</label>
              <input type="text" id="prod-categoria" required placeholder="Ej: Analgésicos y antiinflamatorios" data-en-placeholder="E.g: Pain relievers and anti-inflammatories">
            </div>
          </div>

          <div class="form-bloque">
            <div class="campo">
              <label for="prod-precio" data-en="Price (COP)">Precio (COP)</label>
              <input type="number" id="prod-precio" required min="0" step="1" placeholder="Ej: 8900" data-en-placeholder="E.g: 8900">
            </div>
            <div class="campo">
              <label for="prod-cantidad" data-en="Available quantity">Cantidad disponible</label>
              <input type="number" id="prod-cantidad" required min="0" step="1" placeholder="Ej: 50" data-en-placeholder="E.g: 50">
            </div>
            <div class="campo">
              <label for="prod-estado" data-en="Status">Estado</label>
              <select id="prod-estado">
                <option value="activo" data-en="Active (visible in catalog)">Activo (visible en el catálogo)</option>
                <option value="inactivo" data-en="Inactive (hidden from catalog)">Inactivo (oculto del catálogo)</option>
              </select>
            </div>
          </div>

          <div class="form-bloque">
            <div class="campo">
              <label for="prod-icono" data-en="Icon (emoji, fallback if no image)">Icono (emoji, respaldo si no hay imagen)</label>
              <input type="text" id="prod-icono" placeholder="Ej: 💊" maxlength="4" value="💊">
            </div>
            <div class="campo">
              <label for="prod-imagen" data-en="Image (path or URL, optional)">Imagen (ruta o URL, opcional)</label>
              <input type="text" id="prod-imagen" placeholder="Ej: img/productos/acetaminofen.jpg" data-en-placeholder="E.g: img/products/acetaminophen.jpg">
            </div>
          </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:10px;">
          <button type="submit" class="btn btn-primary" id="btn-guardar-producto" data-en="Save product">Guardar producto</button>
          <button type="button" class="btn-reset" id="btn-cancelar-edicion" style="display:none;" data-en="Cancel edit">Cancelar edición</button>
        </div>
      </form>
    </div>

    <!-- ===================== TABLA DE PRODUCTOS ===================== -->
    <div class="panel">
      <h3 data-en="Full catalog">Catálogo completo</h3>
      <p style="font-size:0.82rem; color:var(--texto-suave); margin-bottom:14px;" data-en="'Inactive' products are not shown on the main page, but are kept so they don't affect the history of purchases already made.">
        Los productos "inactivos" no se muestran en la página principal, pero se conservan
        para no afectar el historial de compras ya realizadas.
      </p>

      <table class="tabla-conv" id="tabla-productos">
        <thead>
          <tr>
            <th>ID</th>
            <th data-en="Name">Nombre</th>
            <th data-en="Category">Categoría</th>
            <th data-en="Price">Precio</th>
            <th data-en="Stock">Stock</th>
            <th data-en="Status">Estado</th>
            <th data-en="Actions">Acciones</th>
          </tr>
        </thead>
        <tbody id="tabla-productos-body">
          <!-- se llena con JS -->
        </tbody>
      </table>
      <div class="vacio" id="productos-vacio" style="display:none;" data-en="No products registered yet.">
        Todavía no hay productos registrados.
      </div>
    </div>

  </div>
</section>

<footer>
  <p><strong>FarmaVida</strong> · <span data-en="Product administration panel">Panel de administración de productos</span></p>
</footer>

<script src="productos_admin.js"></script>
<script src="idioma.js"></script>

<script>
/* ---- Botón "Generar con IA": propone una descripción a partir del nombre y la categoría ---- */
(function () {
  var btn      = document.getElementById('btn-generar-ia');
  var estado   = document.getElementById('ia-estado');
  var nombre   = document.getElementById('prod-nombre');
  var catego   = document.getElementById('prod-categoria');
  var descrip  = document.getElementById('prod-descripcion');

  btn.addEventListener('click', async function () {
    if (nombre.value.trim() === '' || catego.value.trim() === '') {
      estado.textContent = 'Escribe primero el nombre y la categoría.';
      return;
    }

    btn.disabled = true;
    estado.textContent = 'Generando descripción...';

    try {
      var resp = await fetch('generar_descripcion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nombre: nombre.value.trim(),
          categoria: catego.value.trim()
        })
      });
      var datos = await resp.json();

      if (datos.error) {
        estado.textContent = datos.mensaje;
      } else {
        descrip.value = datos.descripcion;
        estado.textContent = 'Descripción generada. Revísala antes de guardar el producto.';
      }
    } catch (e) {
      estado.textContent = 'No se pudo conectar. Intenta de nuevo o escríbela a mano.';
    } finally {
      btn.disabled = false;
    }
  });
})();
</script>
</body>
</html>