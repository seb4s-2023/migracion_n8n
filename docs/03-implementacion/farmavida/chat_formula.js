/* ===========================================================
   FARMAVIDA - chat_formula.js
   Botón 📎 del chatbot: sube la foto de una fórmula, muestra qué
   medicamentos hay en el catálogo y permite agregarlos al carrito.
   Va DESPUÉS de script.js (usa chatBody, chatInput, agregarMensaje,
   enviarJSON y actualizarBadgeCarrito, que ya existen allí).
   Las tarjetas de resultado NO se guardan en el historial del chat
   (contienen datos de salud); solo se guarda un aviso genérico.
   =========================================================== */
(function () {
  const btnAdjuntar = document.getElementById('chat-adjuntar');
  const inputArchivo = document.getElementById('chat-archivo');
  if (!btnAdjuntar || !inputArchivo) return;

  const en = () => (localStorage.getItem('farmavida_idioma') || 'es') === 'en';
  const T = (es, ing) => (en() ? ing : es);
  let autorizado = false;

  function tarjeta() {                       // mensaje del bot que no se guarda en sesionChat
    const div = document.createElement('div');
    div.className = 'msg bot msg-formula';
    chatBody.appendChild(div);
    chatBody.scrollTop = chatBody.scrollHeight;
    return div;
  }

  /* ---- 1) Aviso de privacidad antes de subir (Ley 1581 de 2012) ---- */
  btnAdjuntar.addEventListener('click', () => {
    if (autorizado) { inputArchivo.click(); return; }
    const c = tarjeta();
    const p = document.createElement('p');
    p.textContent = T(
      'Tu fórmula contiene datos de salud. La usaré solo para ver qué medicamentos tenemos. La foto se procesa con un servicio de IA y se guarda de forma restringida. Si prefieres, tapa tu nombre y documento antes de tomarla. Al continuar autorizas este tratamiento (Ley 1581 de 2012).',
      'Your prescription contains health data. I will only use it to check which medications we have. The photo is processed by an AI service and stored with restricted access. If you prefer, cover your name and ID before taking it. By continuing you authorize this processing (Law 1581 of 2012, Colombia).');
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'btn-formula';
    b.textContent = T('Acepto y elegir foto', 'I agree, choose photo');
    b.addEventListener('click', () => { autorizado = true; b.disabled = true; inputArchivo.click(); });
    c.append(p, b);
  });

  /* ---- 2) Reducir la foto del celular (y quitar EXIF/GPS) antes de enviarla ---- */
  async function reducir(file, maxLado = 1600) {
    try {
      const bmp = await createImageBitmap(file, { imageOrientation: 'from-image' });
      const k = Math.min(1, maxLado / Math.max(bmp.width, bmp.height));
      const canvas = document.createElement('canvas');
      canvas.width = Math.round(bmp.width * k);
      canvas.height = Math.round(bmp.height * k);
      canvas.getContext('2d').drawImage(bmp, 0, 0, canvas.width, canvas.height);
      return await new Promise(res => canvas.toBlob(b => res(b || file), 'image/jpeg', 0.85));
    } catch (e) {
      return file;                          // si el navegador no puede, se envía tal cual
    }
  }

  inputArchivo.addEventListener('change', async () => {
    const file = inputArchivo.files[0];
    inputArchivo.value = '';
    if (!file) return;

    agregarMensaje(T('📎 Foto de fórmula enviada', '📎 Prescription photo sent'), 'user');
    mostrarEscribiendo();
    chatInput.disabled = btnAdjuntar.disabled = true;

    let datos;
    try {
      const blob = await reducir(file);
      const fd = new FormData();
      fd.append('imagen', blob, 'formula.jpg');
      fd.append('autorizo', '1');
      const r = await fetch('formula_ia.php', { method: 'POST', body: fd });
      const texto = await r.text();
      try {
        datos = JSON.parse(texto);
      } catch (e) {
        console.error('Respuesta no válida de formula_ia.php (HTTP ' + r.status + '):', texto);
        datos = { error: true, mensaje: T('El servidor respondió con un error (HTTP ' + r.status + '). Revisa la consola (F12) o el log de PHP.',
                                          'The server returned an error (HTTP ' + r.status + '). Check the console (F12) or the PHP log.') };
      }
    } catch (e) {
      console.error(e);
      datos = { error: true, mensaje: T('No pude conectar con el servidor. Intenta de nuevo.', 'Could not reach the server. Please try again.') };
    }

    quitarEscribiendo();
    chatInput.disabled = btnAdjuntar.disabled = false;

    if (datos.error || datos.legible === false) {
      agregarMensaje(datos.mensaje || T('No pude leer la fórmula.', 'I could not read the prescription.'), 'bot');
      return;
    }
    agregarMensaje(T('Revisé tu fórmula. Te muestro lo que encontré 👇', 'I checked your prescription. Here is what I found 👇'), 'bot');
    pintarResultado(datos);
  });

  /* ---- 3) Resultado: una fila por medicamento ---- */
  function botonAgregar(prod, texto) {
    const b = document.createElement('button');
    b.type = 'button'; b.className = 'btn-formula';
    b.textContent = texto;
    b.addEventListener('click', async () => {
      if (!window.CLIENTE_LOGUEADO) { window.location.href = 'login.php'; return; }
      b.disabled = true;
      try {
        const r = await enviarJSON('api.php', { accion: 'carrito_agregar', producto_id: prod.id });
        if (r.error) { b.disabled = false; b.textContent = r.mensaje; return; }
        b.textContent = T('✔ Agregado', '✔ Added');
        if (typeof actualizarBadgeCarrito === 'function') actualizarBadgeCarrito(r.totalItems);
      } catch (e) { b.disabled = false; }
    });
    return b;
  }

  const precio = n => '$' + Number(n).toLocaleString('es-CO', { maximumFractionDigits: 0 });

  function pintarResultado(d) {
    const c = tarjeta();
    if (d.requiere_revision) {
      const aviso = document.createElement('p');
      aviso.className = 'formula-aviso';
      aviso.textContent = T('⚠️ La foto no se leyó con total claridad. Verifica que cada nombre coincida con tu fórmula antes de agregar.',
                            '⚠️ The photo was not fully clear. Please check each name matches your prescription before adding.');
      c.appendChild(aviso);
    }
    d.items.forEach(it => {
      const fila = document.createElement('div');
      fila.className = 'formula-fila';
      const titulo = document.createElement('strong');
      titulo.textContent = (it.leido + ' ' + (it.concentracion || '')).trim() + (it.dudoso ? ' ⚠️' : '');
      fila.appendChild(titulo);

      const det = document.createElement('div');
      if (it.estado === 'disponible') {
        det.textContent = T('✅ Disponible: ', '✅ In stock: ') + it.producto.nombre + ' · ' + precio(it.producto.precio);
        fila.append(det, botonAgregar(it.producto, T('Agregar al carrito', 'Add to cart')));
      } else if (it.estado === 'agotado') {
        det.textContent = T('⏳ Lo manejamos, pero está agotado por ahora.', '⏳ We carry it, but it is out of stock for now.');
        fila.appendChild(det);
      } else if (it.estado === 'concentracion_diferente') {
        det.textContent = it.alternativas.length
          ? T('Tenemos el medicamento, pero no en esa concentración. Disponible:', 'We carry it, but not in that strength. Available:')
          : T('Tenemos el medicamento, pero no en esa concentración y no hay stock.', 'We carry it, but not in that strength and it is out of stock.');
        fila.appendChild(det);
        it.alternativas.forEach(a => fila.appendChild(botonAgregar(a, a.nombre + ' · ' + precio(a.precio))));
      } else {
        det.textContent = T('❌ No lo tenemos en el catálogo. Un asesor puede ayudarte a conseguirlo.', '❌ Not in our catalog. An agent can help you find it.');
        fila.appendChild(det);
      }
      c.appendChild(fila);
    });

    const nota = document.createElement('p');
    nota.className = 'formula-nota';
    nota.textContent = T('Esto no reemplaza la validación del químico farmacéutico: los medicamentos con fórmula se verifican antes de entregarse.',
                         'This does not replace the pharmacist’s validation: prescription drugs are verified before delivery.');
    c.appendChild(nota);
    chatBody.scrollTop = chatBody.scrollHeight;
    agregarMensaje(T('[Resultado de fórmula mostrado]', '[Prescription result shown]'), 'bot');   // lo único que queda en el historial
  }
})();