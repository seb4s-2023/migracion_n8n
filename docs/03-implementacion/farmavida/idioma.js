/* ===========================================================
   FARMAVIDA - idioma.js
   Traductor simple español <-> inglés para textos FIJOS de la
   interfaz (menús, botones, títulos, etc).

   CÓMO FUNCIONA:
   1) A cada elemento que quieras traducir le pones en el HTML
      un atributo data-en="Texto en inglés", ejemplo:

        <a href="#productos" data-en="Products">Productos</a>

      (el texto en español se queda como está, normal, dentro
      de la etiqueta - no hay que tocarlo)

   2) Este script guarda el texto original en español la primera
      vez que carga la página (data-es), y cuando el usuario
      hace clic en el botón, cambia todos los textos que tengan
      data-en al inglés, o los regresa al español guardado.

   3) El idioma elegido se guarda con localStorage, así que si el
      usuario recarga la página o navega a otra, se mantiene su
      elección.
   =========================================================== */

(function () {

  function aplicarIdioma(idioma) {
    document.querySelectorAll('[data-en]').forEach(function (el) {
      // Guardamos el texto original en español la primera vez
      if (!el.dataset.es) {
        el.dataset.es = el.textContent;
      }

      if (idioma === 'en') {
        el.textContent = el.dataset.en;
      } else {
        el.textContent = el.dataset.es;
      }
    });

    // También traducimos placeholders de inputs si tienen data-en-placeholder
    document.querySelectorAll('[data-en-placeholder]').forEach(function (el) {
      if (!el.dataset.esPlaceholder) {
        el.dataset.esPlaceholder = el.getAttribute('placeholder') || '';
      }
      el.setAttribute(
        'placeholder',
        idioma === 'en' ? el.dataset.enPlaceholder : el.dataset.esPlaceholder
      );
    });

    document.documentElement.lang = idioma;
    localStorage.setItem('farmavida_idioma', idioma);
    document.cookie = 'farmavida_idioma=' + idioma + ';path=/;max-age=31536000';
    actualizarBoton(idioma);
  }

  function actualizarBoton(idioma) {
    const texto = document.getElementById('idioma-texto');
    if (!texto) return;
    texto.textContent = idioma === 'en' ? 'ES' : 'EN';
  }

  function alternarIdioma() {
    const actual = localStorage.getItem('farmavida_idioma') || 'es';
    const nuevo = actual === 'es' ? 'en' : 'es';
    aplicarIdioma(nuevo);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const boton = document.getElementById('idioma-toggle');
    if (boton) {
      boton.addEventListener('click', alternarIdioma);
    }
    const guardado = localStorage.getItem('farmavida_idioma') || 'es';
    aplicarIdioma(guardado);
  });

})();