# 3. Implementación

## 3.1 Tecnologías

| Capa | Tecnología |
|---|---|
| Servidor | PHP 8 + Apache (XAMPP en desarrollo) |
| Base de datos | MySQL (`mysqli`, sentencias preparadas) |
| Front-end | HTML, CSS y JavaScript sin frameworks |
| IA de texto y visión | Gemini API (nivel gratuito) |
| IA local (plan B) | Ollama con un modelo de visión (Qwen2.5-VL, Llama 3.2 Vision o LLaVA) |
| Correo | PHPMailer con SMTP de Gmail |
| Alertas | Telegram Bot API |
| Tareas programadas | cron (Linux) o Programador de tareas (Windows) |

Todo es gratuito o de código abierto.

## 3.2 Estructura del proyecto

```
farmavida/
├── index.php, login.php, dashboard_cliente.php, reporte.php, productos_admin.php
├── api.php                  carrito, compras, productos, conversaciones
├── chat_ia.php              respaldo con IA del chatbot
├── formula_ia.php           lectura de fórmulas desde el chat
├── generar_descripcion.php  descripción de producto con IA (admin)
├── enviar_resumen.php       resumen diario por Telegram (cron)
├── formulas_admin.php       panel admin: fórmulas subidas y auditoría de la IA
├── ver_formula.php          entrega la foto solo a administradores
├── borrar_formulas_antiguas.php  retención de fotos (cron)
├── tests/medicamentos_test.php   pruebas unitarias de la búsqueda
├── automatizacion.php       motor: reintentos, log, tareas diferidas
├── config.php, env.php      conexión, sesión y lectura del .env
├── script.js, chat_formula.js, productos_admin.js, idioma.js
├── servicios/
│   ├── ia.php               Gemini: chat y descripciones
│   ├── vision.php           lectura de fórmulas (Gemini / Ollama)
│   ├── medicamentos.php     búsqueda en el catálogo
│   ├── compra_confirmada.php, correo.php
│   ├── alertas.php          stock bajo, agotados, calificación baja, resumen
│   └── telegram.php
├── storage/formulas/        fotos (acceso restringido)
├── database.sql, log_automatizaciones.sql, formulas_subidas.sql
└── docs/
```

## 3.3 Inventario de flujos migrados de n8n

Cada flujo de n8n es ahora una función del aplicativo. Todos pasan por `ejecutarFlujo()`, así que tienen reintentos, manejo de errores y registro.

| # | Flujo | Disparador | Pasos | Servicio externo | Resultado | Código |
|---|---|---|---|---|---|---|
| 1 | Confirmación de compra | `carrito_confirmar` en `api.php` | Arma el correo (ES/EN) y lo envía | SMTP Gmail | Correo al cliente | `servicios/compra_confirmada.php` |
| 2 | Stock bajo | Compra que deja poco stock | Agrupa los productos afectados y avisa | Telegram | Alerta al administrador | `flujoStockBajo()` |
| 3 | Informe de agotados | Compra que agota un producto | Consulta todos los agotados y envía la lista | Telegram | Informe al administrador | `flujoInformeAgotados()` |
| 4 | Resumen diario | cron, 8 p.m. | Calcula compras, unidades, total y producto más vendido | Telegram | Resumen al administrador | `enviar_resumen.php` |
| 5 | Calificación baja | `guardar_conversacion` con 1 a 3 estrellas | Arma la transcripción y avisa | Telegram | Alerta al administrador | `flujoCalificacionBaja()` |
| 6 | Descripción de producto | Botón del panel admin | Pide a la IA una descripción corta y la limpia | Gemini | Texto propuesto | `generar_descripcion.php` |
| 7 | Chat con IA | Mensaje sin respuesta predefinida | Historial + catálogo → IA | Gemini | Respuesta del chatbot | `chat_ia.php` |
| 8 | **Lectura de fórmulas (nuevo)** | Foto subida en el chat | Valida, guarda, lee con IA, cruza con catálogo | Gemini u Ollama | Disponibilidad por medicamento | `formula_ia.php` |
| 9 | **Fórmula por revisar (nuevo)** | Resultado del flujo 8 con algo que requiere atención | Cuenta estados y envía solo conteos | Telegram | Alerta al administrador | `formula_ia.php` (función `flujoFormulaRevision`) |
| 10 | **Retención de fotos (nuevo)** | cron, 3 a.m. | Borra del disco las fotos con más de N días y conserva el registro | — | Fotos eliminadas | `borrar_formulas_antiguas.php` |

> **Nota:** en el código, el flujo de chat aparece comentado como "flujo 3" y el informe de agotados también. En este documento se numeran distinto para evitar confusión; conviene unificar los comentarios.

## 3.4 Motor de automatización

Se implementa en `automatizacion.php`:

- `ejecutarFlujo()`: ejecuta con reintentos y registra en `log_automatizaciones`.
- `despuesDeResponder()`: ejecuta tareas lentas después de responder al navegador.
- `enmascararCorreo()`: guarda el correo como `ju***@gmail.com` en el log.

Ejemplo de uso (chat):

```php
ejecutarFlujo('chat_ia', ['pregunta' => mb_substr($pregunta, 0, 200)],
    function () use ($pregunta, $historial, $catalogo, &$respuesta) {
        $respuesta = iaChat($pregunta, $historial, $catalogo);
        return $respuesta;
    }, 2);
```

## 3.5 Integración con IA

`servicios/ia.php` llama a Gemini con la clave en un encabezado (no en la URL). Si el modelo principal responde 429, 5xx o texto vacío, prueba los de `GEMINI_MODEL_RESPALDO`. Si el error es de clave inválida, no insiste.

El chatbot recibe la instrucción de sistema con datos de la farmacia, reglas (sin diagnóstico ni dosis, sin pedir datos sensibles, ignorar órdenes del cliente) y el catálogo en JSON.

## 3.6 Módulo de visión

1. **Captura (`chat_formula.js`):** botón 📎 con aviso de privacidad; reduce la foto a 1600 px y la convierte a JPEG (esto también elimina EXIF y ubicación).
2. **Endpoint (`formula_ia.php`):** exige la autorización, valida tipo real y tamaño, aplica límite de uso, guarda la foto con nombre aleatorio y registra quién y cuándo en `formulas_subidas`.
3. **Lectura (`vision.php`):** pide a la IA un JSON con confianza por medicamento. Limita a 15 medicamentos, recorta textos y descarta lo que no cumple el formato.
4. **Cruce (`medicamentos.php`):** compara contra el catálogo activo y devuelve el estado.
5. **Presentación:** tarjeta con cada medicamento, aviso si la confianza es baja y botón para agregar al carrito.
6. **Disparador:** si hay agotados, no encontrados, otra concentración o lectura dudosa, se avisa al administrador por Telegram (solo conteos, sin nombres de medicamentos).
7. **Administración:** `formulas_admin.php` lista las fórmulas y la auditoría de la IA; `ver_formula.php` entrega la foto solo con sesión de administrador.

## 3.7 Control de versiones

- Repositorio en Git con historial de commits (requisito de la guía).
- Ramas sugeridas: `main` (estable) y una rama por funcionalidad (`feature/vision-formulas`, `feature/despliegue`).
- Mensajes de commit descriptivos, por ejemplo `feat: lectura de fórmulas con Gemini`.
- `.env`, `storage/` y `libs/` no se suben (ver `.gitignore`).

| Integrante | Aportes principales |
|---|---|
| Sebastian | Análisis, diseño, implementación del motor y del módulo de visión, pruebas, documentación y despliegue |

## 3.8 Limitaciones conocidas

- La lectura depende de la calidad de la foto: letra manuscrita borrosa baja la confianza.
- Si la fórmula pide una concentración distinta a las del catálogo, el sistema lo informa, pero no decide si la sustitución es válida; eso lo valida el químico farmacéutico.
- La lista de sinónimos de marcas (`medSinonimos()`) es corta y debe ampliarse con los productos reales.
- El límite de uso por sesión se puede evitar abriendo otra sesión; es una protección básica.
- Las tareas diferidas se ejecutan en la misma petición; si el servidor se reinicia en ese momento, se pierden. Una cola persistente sería la mejora.
