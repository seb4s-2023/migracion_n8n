/* ===========================================================
   FARMAVIDA - productos_admin.js
   Maneja el panel de administración de productos (productos_admin.php):
   - Carga y pinta la tabla con TODOS los productos (activos e inactivos)
   - Crear producto nuevo
   - Editar producto existente (rellena el formulario)
   - Eliminar producto (soft delete -> pasa a "inactivo")

   Todo habla con api.php usando el campo "accion":
     producto_listar, producto_crear, producto_editar, producto_eliminar

   ---------------------------------------------------------
   IDIOMA:
   Esta tabla se dibuja con JavaScript DESPUÉS de que la página
   ya cargó, así que idioma.js no la puede traducir directamente
   (no existe todavía cuando idioma.js corre). Por eso aquí
   consultamos el idioma guardado en localStorage (lo guarda
   idioma.js) y armamos los textos ("Activo"/"Active", los
   títulos de los botones, etc.) en el idioma correcto cada vez
   que se dibuja la tabla. También volvemos a dibujar la tabla
   cuando el usuario le da clic al botón 🌐 de cambiar idioma.
   =========================================================== */

async function enviarJSON(url, datos) {
  const respuesta = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(datos)
  });
  return respuesta.json();
}

function idiomaActual() {
  return localStorage.getItem('farmavida_idioma') || 'es';
}

const mensajeBox = document.getElementById('prod-mensaje');
const form = document.getElementById('form-producto');
const formTitulo = document.getElementById('form-titulo');
const btnGuardar = document.getElementById('btn-guardar-producto');
const btnCancelar = document.getElementById('btn-cancelar-edicion');

const campoId = document.getElementById('prod-id');
const campoNombre = document.getElementById('prod-nombre');
const campoDescripcion = document.getElementById('prod-descripcion');
const campoCategoria = document.getElementById('prod-categoria');
const campoPrecio = document.getElementById('prod-precio');
const campoCantidad = document.getElementById('prod-cantidad');
const campoEstado = document.getElementById('prod-estado');
const campoIcono = document.getElementById('prod-icono');
const campoImagen = document.getElementById('prod-imagen');

const tablaBody = document.getElementById('tabla-productos-body');
const tablaVacio = document.getElementById('productos-vacio');
const tabla = document.getElementById('tabla-productos');

function mostrarMensaje(texto, esError) {
  if (!mensajeBox) return;
  mensajeBox.textContent = texto;
  mensajeBox.className = 'compra-mensaje ' + (esError ? 'error' : 'ok');
  mensajeBox.style.display = 'block';
  mensajeBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
  setTimeout(() => { mensajeBox.style.display = 'none'; }, 4000);
}

function formatearPrecio(numero) {
  if (Number(numero) <= 0) {
    return idiomaActual() === 'en' ? 'Prescription price' : 'Según fórmula';
  }
  return '$' + Number(numero).toLocaleString('es-CO', { maximumFractionDigits: 0 });
}

/* ===========================================================
   Cargar y pintar la tabla de productos
   =========================================================== */
async function cargarProductos() {
  let data;
  try {
    data = await (await fetch('api.php?accion=producto_listar')).json();
  } catch (err) {
    mostrarMensaje(idiomaActual() === 'en' ? 'Could not connect to the server.' : 'No se pudo conectar con el servidor.', true);
    console.error(err);
    return;
  }

  if (data.error) {
    mostrarMensaje(data.mensaje || (idiomaActual() === 'en' ? 'Could not load the products.' : 'No se pudieron cargar los productos.'), true);
    return;
  }

  const productos = data.productos || [];
  const en = idiomaActual() === 'en';
  tablaBody.innerHTML = '';

  if (productos.length === 0) {
    tabla.style.display = 'none';
    tablaVacio.style.display = 'block';
    return;
  }

  tabla.style.display = '';
  tablaVacio.style.display = 'none';

  productos.forEach(p => {
    const tr = document.createElement('tr');
    const estadoTexto = p.estado === 'activo'
      ? (en ? '🟢 Active' : '🟢 Activo')
      : (en ? '⚪ Inactive' : '⚪ Inactivo');
    const tituloEditar = en ? 'Edit' : 'Editar';
    const tituloEliminar = en ? 'Delete / deactivate' : 'Eliminar / desactivar';

    tr.innerHTML = `
      <td>${p.id}</td>
      <td>${escaparHtml(p.nombre)}</td>
      <td>${escaparHtml(p.categoria)}</td>
      <td>${formatearPrecio(p.precio)}</td>
      <td>${p.cantidad_disponible}</td>
      <td>${estadoTexto}</td>
      <td style="white-space:nowrap;">
        <button type="button" class="btn-editar" data-id="${p.id}" title="${tituloEditar}">✏️</button>
        <button type="button" class="btn-eliminar" data-id="${p.id}" title="${tituloEliminar}">🗑️</button>
      </td>
    `;
    tablaBody.appendChild(tr);

    tr.querySelector('.btn-editar').addEventListener('click', () => cargarEnFormulario(p));
    tr.querySelector('.btn-eliminar').addEventListener('click', () => eliminarProducto(p.id, p.nombre));
  });
}

function escaparHtml(texto) {
  const div = document.createElement('div');
  div.textContent = texto ?? '';
  return div.innerHTML;
}

/* ===========================================================
   Rellenar el formulario para editar un producto existente
   =========================================================== */
function cargarEnFormulario(p) {
  campoId.value = p.id;
  campoNombre.value = p.nombre;
  campoDescripcion.value = p.descripcion;
  campoCategoria.value = p.categoria;
  campoPrecio.value = p.precio;
  campoCantidad.value = p.cantidad_disponible;
  campoEstado.value = p.estado;
  campoIcono.value = p.icono || '💊';
  campoImagen.value = p.imagen || '';

  const prefijo = idiomaActual() === 'en' ? '✏️ Editing: ' : '✏️ Editando: ';
  formTitulo.textContent = prefijo + p.nombre;
  btnGuardar.textContent = idiomaActual() === 'en' ? 'Save changes' : 'Guardar cambios';
  btnCancelar.style.display = 'inline-block';

  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ===========================================================
   Volver el formulario a modo "nuevo producto"
   =========================================================== */
function resetearFormulario() {
  form.reset();
  campoId.value = '';
  campoIcono.value = '💊';
  campoEstado.value = 'activo';
  const en = idiomaActual() === 'en';
  formTitulo.textContent = en ? '➕ New product' : '➕ Nuevo producto';
  btnGuardar.textContent = en ? 'Save product' : 'Guardar producto';
  btnCancelar.style.display = 'none';
}

if (btnCancelar) {
  btnCancelar.addEventListener('click', resetearFormulario);
}

/* ===========================================================
   Crear o editar (según si prod-id tiene valor)
   =========================================================== */
if (form) {
  form.addEventListener('submit', async function (e) {
    e.preventDefault();

    const id = campoId.value;
    const esEdicion = id !== '';

    const payload = {
      accion: esEdicion ? 'producto_editar' : 'producto_crear',
      nombre: campoNombre.value.trim(),
      descripcion: campoDescripcion.value.trim(),
      categoria: campoCategoria.value.trim(),
      precio: campoPrecio.value,
      cantidad_disponible: campoCantidad.value,
      estado: campoEstado.value,
      icono: campoIcono.value.trim() || '💊',
      imagen: campoImagen.value.trim()
    };

    if (esEdicion) {
      payload.id = id;
    }

    btnGuardar.disabled = true;
    const textoOriginal = btnGuardar.textContent;
    btnGuardar.textContent = idiomaActual() === 'en' ? 'Saving...' : 'Guardando...';

    try {
      const resultado = await enviarJSON('api.php', payload);
      mostrarMensaje(resultado.mensaje, resultado.error);

      if (!resultado.error) {
        resetearFormulario();
        cargarProductos();
      }
    } catch (err) {
      mostrarMensaje(idiomaActual() === 'en' ? 'Could not connect to the server.' : 'No se pudo conectar con el servidor.', true);
      console.error(err);
    } finally {
      btnGuardar.disabled = false;
      btnGuardar.textContent = textoOriginal;
    }
  });
}

/* ===========================================================
   Eliminar (soft delete -> estado inactivo)
   =========================================================== */
async function eliminarProducto(id, nombre) {
  const en = idiomaActual() === 'en';
  const confirmado = confirm(
    en
      ? `Delete "${nombre}"? It will become "inactive" and stop showing in the catalog (purchase history is kept).`
      : `¿Eliminar "${nombre}"? Pasará a "inactivo" y dejará de mostrarse en el catálogo (no se borra del historial de compras).`
  );
  if (!confirmado) return;

  try {
    const resultado = await enviarJSON('api.php', { accion: 'producto_eliminar', id });
    mostrarMensaje(resultado.mensaje, resultado.error);
    if (!resultado.error) {
      cargarProductos();
      // Si justo se estaba editando este producto, limpiamos el formulario
      if (campoId.value == id) resetearFormulario();
    }
  } catch (err) {
    mostrarMensaje(en ? 'Could not connect to the server.' : 'No se pudo conectar con el servidor.', true);
    console.error(err);
  }
}

/* ===========================================================
   Re-dibujar la tabla y el formulario cuando el usuario cambia
   de idioma con el botón 🌐 (el cambio de idioma.js ocurre
   primero; usamos setTimeout para leerlo ya actualizado).
   =========================================================== */
const botonIdioma = document.getElementById('idioma-toggle');
if (botonIdioma) {
  botonIdioma.addEventListener('click', () => {
    setTimeout(() => {
      cargarProductos();
      if (campoId.value === '') {
        resetearFormulario();
      }
    }, 0);
  });
}

/* ===========================================================
   Carga inicial
   =========================================================== */
cargarProductos();