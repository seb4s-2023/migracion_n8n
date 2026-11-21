# 6. Resumen de funcionalidades y cambios del tercer corte

**Proyecto:** FarmaVida · **Asignatura:** Desarrollo de Aplicaciones Empresariales
**Corte:** tercero · **Fecha:** noviembre de 2026 · **Integrante:** Sebastian

Este documento resume **qué se agregó y qué se cambió** en el proyecto durante el tercer corte, y en qué estado de verificación está cada cosa.

**Leyenda de estado:** ✅ verificado por el equipo · ⬜ implementado, pendiente de verificar · 🔜 pendiente de implementar

---

## 1. Resumen ejecutivo

En el tercer corte el aplicativo dejó de depender de n8n en la nube y ganó un **módulo de visión por computador**: el cliente fotografía su fórmula médica desde el chat y el sistema indica, medicamento por medicamento, si lo tiene disponible. Si la lectura requiere atención humana, el sistema avisa automáticamente al administrador.

| Componente de la guía | Qué se hizo |
|---|---|
| 1. Automatización propia en código (Ruta A) | Todos los flujos corren como servicios PHP con reintentos, manejo de errores y registro en MySQL |
| IA con herramientas gratuitas | Gemini (nivel gratuito) con modelos de respaldo; Ollama local como plan B de visión |
| 2. IA multimodal | Lectura de fórmulas médicas fotografiadas, con JSON, nivel de confianza y confirmación del usuario |
| Integración con el motor | El resultado de la visión dispara un aviso al administrador por Telegram |
| Seguridad y datos personales | Roles, aviso y autorización previa, fotos con acceso restringido, auditoría y retención |

## 2. Punto de partida

Antes de esta etapa, el aplicativo ya contaba con:

- Catálogo, carrito, compra, registro e inicio de sesión de clientes y administradores.
- Chatbot con respuestas predefinidas y respaldo con IA.
- Motor de automatización propio (`automatizacion.php`) con reintentos y registro.
- Flujos migrados de n8n: confirmación de compra por correo, alertas de stock bajo y agotados, resumen diario por Telegram, alerta de calificación baja, descripción de productos con IA y chat con IA.
- Un primer paso del módulo de fórmulas: `servicios/medicamentos.php`, que busca nombres de medicamentos en el catálogo sin usar IA.

## 3. Funcionalidades agregadas

### 3.1 Lectura de fórmulas desde el chat

**Qué hace:** el cliente toca el botón 📎, acepta un aviso de privacidad, elige una foto de su fórmula y recibe una tarjeta con cada medicamento y su estado.

| Estado | Significado | Acción disponible |
|---|---|---|
| ✅ Disponible | Está en el catálogo con esa concentración y hay stock | Botón "Agregar al carrito" |
| ⏳ Agotado | Se maneja, pero no hay unidades | Informa |
| ⚠️ Concentración diferente | Existe, pero no con esa concentración | Muestra y permite agregar las alternativas con stock |
| ❌ No encontrado | No está en el catálogo | Sugiere hablar con un asesor |

**Detalles de funcionamiento**

- Aviso de privacidad y autorización explícita antes de subir la foto (Ley 1581 de 2012).
- La foto se reduce a 1600 px y se convierte a JPEG en el navegador, lo que además elimina el EXIF y la ubicación.
- La IA devuelve un JSON con un nivel de confianza por medicamento y global; el sistema valida y limpia ese JSON.
- Si la confianza es baja, la tarjeta pide al cliente verificar los nombres antes de agregar al carrito. Nada se guarda en el carrito sin que el cliente lo decida.
- La IA solo extrae nombre y concentración; **no** lee paciente, documento, diagnóstico, dosis ni médico.
- El texto escrito dentro de la imagen se trata como dato, no como instrucción.
- Reconoce marcas y nombres alternos (por ejemplo Advil → ibuprofeno, paracetamol → acetaminofén) y tolera pequeños errores de lectura.
- Compara concentraciones entre unidades (1 g = 1000 mg).

**Estado:** lectura, cruce con catálogo y agregado al carrito ✅ (probado con fórmula de ejemplo: paracetamol 1 g → otra concentración, Advil 400 mg → disponible como ibuprofeno, loratadina → no está en el catálogo).

### 3.2 Plan B del módulo de visión

| Falla | Respuesta |
|---|---|
| El modelo principal de Gemini no responde o no tiene cuota | Prueba los modelos de respaldo configurados |
| Gemini falla del todo | Usa **Ollama local** si está configurado (`OLLAMA_URL`) |
| Ninguno responde | Mensaje claro al cliente: puede escribir el medicamento o intentar más tarde |
| Se quiere que la foto nunca salga del servidor | `VISION_SOLO_LOCAL=1` |

**Estado:** Gemini ✅; respaldo con Ollama ⬜ (falta instalarlo y probarlo).

### 3.3 Disparador sobre el motor de automatización

Cuando la lectura de una fórmula tiene medicamentos agotados, no encontrados, con otra concentración, o la lectura fue dudosa, el sistema envía un aviso por Telegram al administrador **después** de responderle al cliente (no lo hace esperar). Usa `ejecutarFlujo()`, así que tiene reintentos y queda en el registro.

El mensaje lleva **solo conteos**, nunca nombres de medicamentos, porque son datos de salud y Telegram es un tercero.

**Estado:** ⬜

### 3.4 Panel del administrador: fórmulas y auditoría de la IA

- **`reporte.php`:** nueva sección "📋 Fórmulas subidas al chat" con totales (subidas, leídas, ilegibles, fallos de IA) y las últimas 5, más un enlace "Fórmulas" en el menú.
- **`formulas_admin.php`:** las últimas 100 fórmulas (quién, cuándo, estado, proveedor de IA, tamaño) y la **auditoría de las acciones de la IA** (chat, descripciones, lectura de fórmulas, consultas de fotos).
- **`ver_formula.php`:** entrega la foto solo a un administrador con sesión; cada consulta queda registrada.

**Estado:** ⬜

### 3.5 Protección y retención de las fotos

- Se guardan con nombre aleatorio de 32 caracteres, fuera del alcance público (archivo `.htaccess` o carpeta externa con `FORMULAS_DIR`).
- Se valida el tipo real del archivo y un máximo de 4 MB.
- Límite de uso: 8 fotos por sesión y 10 segundos entre cada una.
- Cada subida queda en la tabla `formulas_subidas` (quién, cuándo, estado, resultado).
- **`borrar_formulas_antiguas.php`:** borra del disco las fotos con más de 30 días (configurable con `FORMULAS_DIAS_RETENCION`) y conserva el registro.

**Estado:** subida y registro ✅; panel de acceso, borrado y límite de uso ⬜.

### 3.6 Pruebas

- **`tests/medicamentos_test.php`:** 19 pruebas unitarias de la búsqueda en catálogo (marcas, tildes, concentraciones, errores de letra, agotados, no encontrados). Se ejecuta con `php tests/medicamentos_test.php`.
- **`prueba_formula.php`:** lee una carpeta de fotos reales, imprime lo leído y genera `resultados_formulas.csv` para medir el porcentaje de aciertos (mínimo 20 imágenes).
- **`formulas_ejemplo/`:** tres fórmulas de ejemplo con datos ficticios para depurar (impresa, simulando foto de celular, y con marcas y otras dosis).

**Estado:** ⬜ las pruebas unitarias; 🔜 las 20 fotos reales con su medición.

### 3.7 Correcciones y mejoras de seguridad

- **Aviso de privacidad del registro:** decía que los datos sensibles "se almacenan cifrados", pero en la base de datos están en texto plano (solo la contraseña usa hash). Se corrigió a "acceso restringido" para que el texto sea veraz.
- **`formula_ia.php` siempre responde JSON**, aunque algo falle; con `APP_DEBUG=1` incluye el motivo real del error. Esto resolvió los errores de tabla y archivos faltantes durante las pruebas.
- Se quitaron los comentarios obsoletos que aún mencionaban n8n en `script.js`.

## 4. Archivos del proyecto

### Nuevos

| Archivo | Propósito |
|---|---|
| `servicios/vision.php` | Lectura de fórmulas con Gemini u Ollama; valida el JSON |
| `formula_ia.php` | Endpoint que recibe la foto, la guarda, la lee, consulta el catálogo y dispara el aviso |
| `chat_formula.js` / `chat_formula.css` | Botón 📎, aviso de privacidad, reducción de la foto y tarjeta de resultados |
| `formulas_admin.php` | Fórmulas subidas y auditoría de la IA |
| `ver_formula.php` | Entrega de fotos solo a administradores |
| `borrar_formulas_antiguas.php` | Retención de fotos (cron) |
| `formulas_subidas.sql` | Tabla de registro de fórmulas |
| `storage/formulas/.htaccess` | Bloquea el acceso directo a las fotos |
| `tests/medicamentos_test.php` | Pruebas unitarias |
| `prueba_formula.php` | Medición con fotos reales (solo desarrollo) |
| `formulas_ejemplo/` | Fórmulas de ejemplo con datos ficticios |
| `docs/` y `README.md` | Documentación por fases, resumen y guía de instalación |

### Modificados

| Archivo | Cambio |
|---|---|
| `servicios/ia.php` | `llamarGemini()` acepta `$timeout` y `$json` (valores por defecto iguales a los anteriores) |
| `index.php` | Botón 📎, enlaces a `chat_formula.css` y `chat_formula.js`, aviso de privacidad corregido |
| `script.js` | La respuesta sobre fórmulas menciona el botón 📎; comentarios de n8n eliminados |
| `reporte.php` | Enlace "Fórmulas" en el menú y sección de resumen |

### Base de datos

Nueva tabla `formulas_subidas`: `cliente_id`, `archivo`, `mime`, `tamano_kb`, `proveedor`, `estado` (procesando, leida, ilegible, fallo_ia), `meds_leidos`, `meds_disponibles`, `fecha`.

## 5. Variables de entorno nuevas

| Variable | Uso |
|---|---|
| `OLLAMA_URL`, `OLLAMA_VISION_MODEL` | Modelo de visión local (plan B) |
| `VISION_SOLO_LOCAL` | `1` = no enviar fotos a Gemini |
| `FORMULAS_DIR` | Carpeta privada para las fotos |
| `FORMULAS_DIAS_RETENCION` | Días antes de borrar las fotos (30 por defecto) |
| `APP_URL` | Enlace al panel en los avisos de Telegram |
| `APP_DEBUG` | `1` muestra el motivo real de los errores (solo desarrollo) |

## 6. Tareas programadas

| Tarea | Cuándo |
|---|---|
| `enviar_resumen.php` (resumen diario por Telegram) | Todos los días, 8 p.m. |
| `borrar_formulas_antiguas.php` (retención de fotos) | Todos los días, 3 a.m. |

## 7. Cómo se usa

**Cliente**
1. Abre el chat y toca 📎.
2. Lee y acepta el aviso de privacidad.
3. Elige o toma la foto de su fórmula.
4. Revisa la tarjeta: disponible, agotado, otra concentración o no encontrado.
5. Agrega al carrito los que desee (requiere iniciar sesión).

**Administrador**
1. Recibe el aviso por Telegram si hay algo por revisar.
2. Entra al panel y ve el resumen de fórmulas en `reporte.php`.
3. Abre `formulas_admin.php` para ver el detalle, la foto y la auditoría de la IA.

## 8. Cumplimiento frente a la guía

| Requisito de la guía | Estado |
|---|---|
| Flujos funcionando sin n8n en la nube | ✅ |
| Manejo de errores, reintentos y log de cada ejecución | ✅ |
| Claves en variables de entorno | ✅ |
| Modelo de lenguaje gratuito | ✅ |
| Chatbot funcionando sobre el nuevo motor | ✅ |
| Captura de imagen desde el chat | ✅ |
| Resultado en JSON con confianza y confirmación si es baja | ✅ |
| El resultado de la visión dispara el proceso | ⬜ |
| Imágenes con control de acceso y registro de quién y cuándo | ⬜ |
| Auditoría de las acciones de la IA | ⬜ |
| Protección de datos personales (modelo local o anonimizar) | ⬜ (falta probar Ollama) |
| Plan B si el modelo falla | ⬜ |
| Pruebas unitarias | ⬜ |
| Pruebas con 20+ fotos reales y % de aciertos | 🔜 |
| Aplicativo, motor y visión desplegados en un servidor | 🔜 |
| Repositorio en Git con historial de commits | 🔜 |
| Video de demostración (máx. 8 min) | 🔜 |
| LangChain / LangGraph | 🔜 por confirmar con el docente |

## 9. Limitaciones conocidas

- La lectura depende de la calidad de la foto; la letra manuscrita borrosa baja la confianza.
- El sistema informa disponibilidad y concentración, pero **no decide** si una sustitución es válida: eso lo valida el químico farmacéutico. La tarjeta lo aclara al cliente.
- Si se usa Gemini, la foto sale a un servicio externo. Los términos de uso de datos de Google en el nivel gratuito deben revisarse; la alternativa es usar solo el modelo local.
- Los datos sensibles de `clientes` (documento, condición de salud) siguen guardados en texto plano; se evaluará cifrarlos.
- La lista de sinónimos de marcas es corta y debe ampliarse con los productos reales.
- El límite de uso por sesión es una protección básica.

## 10. Pendientes

1. Verificar el aviso por Telegram, el panel de administrador, el borrado de fotos y las pruebas unitarias, y pasar su estado a ✅.
2. Instalar y probar Ollama como plan B.
3. Reunir 20 o más fotos reales con celular y medir los aciertos.
4. Confirmar con el docente el tema de LangChain / LangGraph.
5. Decidir si se cifran los datos sensibles de `clientes`.
6. Desplegar en un servidor con HTTPS y programar los cron.
7. Eliminar archivos de desarrollo antes de entregar: `prueba_*.php`, `listar_modelos.php`, `crear_admin.php`, y quitar `APP_DEBUG=1`.
8. Grabar el video y completar los resultados en `docs/04-pruebas/plan-y-resultados-de-pruebas.md`.
