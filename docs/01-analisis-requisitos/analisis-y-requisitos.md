# 1. Análisis y requisitos

**Proyecto:** FarmaVida: aplicativo web empresarial para una farmacia online en Bucaramanga
**Asignatura:** Desarrollo de Aplicaciones Empresariales · Tecnología en Desarrollo de Sistemas Informáticos
**Corte:** Tercer corte · **Fecha:** noviembre de 2026
**Integrante:** Sebastian

---

## 1.1 Contexto y problema

FarmaVida vende medicamentos y productos de cuidado personal con domicilio en Bucaramanga y su área metropolitana. En los cortes anteriores el aplicativo ya permitía registrar clientes, vender productos, atender un chatbot y automatizar tareas con **n8n en la nube**.

En este corte el plan gratuito de n8n en la nube dejó de estar disponible, y la guía exige que el sistema no dependa de plataformas de pago. Además, la farmacia tiene un proceso manual que consume tiempo: **el cliente envía la fórmula médica y un empleado revisa a mano qué medicamentos hay en inventario**.

## 1.2 Objetivos

**General:** integrar en el aplicativo un motor de automatización propio en código y un módulo de visión por computador, usando solo herramientas gratuitas.

**Específicos**

1. Reimplementar los flujos de n8n como servicios del propio aplicativo.
2. Conectar el aplicativo con un modelo de lenguaje gratuito para las tareas de IA.
3. Resolver con visión por computador la lectura de fórmulas médicas fotografiadas.
4. Documentar arquitectura, pruebas y límites del sistema.

## 1.3 Alcance

**Incluye:** catálogo, carrito y compra; registro e inicio de sesión de clientes y administradores; chatbot con respaldo de IA; panel de productos; reporte para el administrador; automatizaciones por correo y Telegram; lectura de fórmulas desde el chat.

**No incluye:** pasarela de pagos, validación legal de la fórmula (la hace el químico farmacéutico), diagnóstico ni recomendación de dosis.

## 1.4 Usuarios y roles

| Rol | Qué puede hacer |
|---|---|
| Visitante | Ver catálogo, usar el chat, subir una foto de fórmula |
| Cliente (con sesión) | Todo lo anterior, más carrito, compra, historial y "Mi cuenta" |
| Administrador | Gestionar productos (incluida la descripción generada con IA), ver el reporte de conversaciones y clientes |
| Sistema (cron) | Enviar el resumen diario de ventas al administrador |

## 1.5 Requisitos funcionales

| ID | Requisito | Prioridad |
|---|---|---|
| RF-01 | El cliente puede registrarse e iniciar sesión | Alta |
| RF-02 | El cliente puede agregar productos al carrito y confirmar la compra | Alta |
| RF-03 | Al confirmar una compra se envía un correo de confirmación (español/inglés) | Alta |
| RF-04 | Se alerta al administrador por Telegram cuando un producto queda con stock bajo o se agota | Media |
| RF-05 | Se alerta al administrador cuando una conversación recibe calificación de 1 a 3 | Media |
| RF-06 | Cada día a las 8 p.m. se envía el resumen de ventas al administrador | Media |
| RF-07 | El chatbot responde con reglas locales y, si no sabe, consulta a la IA con el catálogo | Alta |
| RF-08 | El administrador puede generar con IA la descripción de un producto | Baja |
| RF-09 | El cliente puede subir una foto de su fórmula desde el chat | Alta |
| RF-10 | La IA extrae los medicamentos de la fórmula con un nivel de confianza | Alta |
| RF-11 | El sistema indica por cada medicamento si está disponible, agotado, en otra concentración o no existe | Alta |
| RF-12 | Si la lectura es dudosa, se pide al cliente verificar antes de agregar al carrito | Alta |
| RF-13 | Cada ejecución de un flujo queda registrada (fecha, entrada, resultado, intentos, duración) | Alta |
| RF-14 | Cada foto de fórmula queda registrada con quién la subió y cuándo | Alta |
| RF-15 | Si la lectura de una fórmula necesita atención humana (agotado, no existe, otra concentración o lectura dudosa), se avisa al administrador por Telegram | Alta |
| RF-16 | El administrador puede ver las fórmulas subidas, abrir cada foto y revisar la auditoría de las acciones de la IA | Media |
| RF-17 | Las fotos de fórmulas se borran automáticamente después de un plazo configurable | Media |

## 1.6 Requisitos no funcionales

| ID | Requisito | Cómo se verifica |
|---|---|---|
| RNF-01 | Ninguna herramienta puede exigir pago | Revisión de dependencias |
| RNF-02 | Las claves van en variables de entorno, nunca en el código | `.env` fuera de Git |
| RNF-03 | Si la IA falla o agota la cuota, el sistema sigue funcionando | Pruebas de plan B |
| RNF-04 | Los flujos tienen reintentos y manejo de errores y no rompen la web | `ejecutarFlujo()` |
| RNF-05 | Las tareas lentas (correo, Telegram) no hacen esperar al cliente | `despuesDeResponder()` |
| RNF-06 | Las fotos de fórmulas no son accesibles por URL directa | Carpeta protegida o fuera de la web |
| RNF-07 | Los textos del cliente no se tratan como instrucciones para la IA | Prompts con regla anti-inyección |
| RNF-08 | Cumplimiento de la Ley 1581 de 2012 | Ver 1.7 |
| RNF-09 | Disponible en español e inglés | `idioma.js` |
| RNF-10 | Accesible por internet (desplegado) | Ver documento 5 |

## 1.7 Datos personales (Ley 1581 de 2012)

| Dato | Dónde se guarda | Tipo | Finalidad |
|---|---|---|---|
| Nombre, teléfono, correo, dirección, ciudad | `clientes` | Personal | Atender el pedido y el domicilio |
| Documento de identidad | `clientes` | Personal | Identificar al cliente |
| Condición de salud, EPS | `clientes` | **Sensible** | Atención farmacéutica |
| Foto de la fórmula médica | `storage/formulas/` y `formulas_subidas` | **Sensible** | Verificar disponibilidad de medicamentos |
| Conversaciones del chat | `conversaciones`, `mensajes` | Personal | Mejorar el servicio |

**Medidas aplicadas**

- **Autorización:** antes de subir una fórmula, el chat muestra un aviso y exige aceptar el tratamiento.
- **Minimización:** la IA tiene instrucción de no extraer nombre del paciente, documento, diagnóstico, dosis ni médico.
- **Logs sin datos de salud:** el registro de flujos guarda conteos, no nombres de medicamentos. El correo del cliente se enmascara.
- **El historial del chat** guarda solo un aviso genérico, no el resultado de la fórmula.
- **Contraseñas** con `password_hash`.

- **Acceso a las fotos:** solo un administrador con sesión, mediante `ver_formula.php`; cada consulta queda registrada.
- **Retención:** `borrar_formulas_antiguas.php` elimina las fotos con más de 30 días (configurable).

**Pendiente por decidir:** si se usa el nivel gratuito de Gemini con fotos de fórmulas (hay que verificar si Google usa esos datos para mejorar sus productos) o solo el modelo local (`VISION_SOLO_LOCAL=1`).

## 1.8 Casos de uso principales

**CU-01 · Consultar disponibilidad con la fórmula**

1. El cliente abre el chat y toca 📎.
2. Acepta el aviso de privacidad y elige una foto.
3. El sistema reduce la imagen, la lee con IA y consulta el catálogo.
4. El chat muestra cada medicamento con su estado y botones para agregar al carrito.
5. _Alterno:_ si no se puede leer, el chat sugiere escribir el medicamento o repetir la foto con mejor luz.
6. _Alterno:_ si la IA no responde, se usa el modelo local; si tampoco, se muestra un mensaje claro.

**CU-02 · Comprar productos**

1. El cliente inicia sesión, agrega productos y confirma la compra.
2. El sistema descuenta stock y registra los pedidos.
3. Después de responder, envía el correo de confirmación y las alertas de stock.

## 1.9 Riesgos

| Riesgo | Impacto | Mitigación |
|---|---|---|
| La cuota gratuita de Gemini se agota | El chat y la visión dejan de responder | Modelos de respaldo, mensaje de plan B y Ollama local |
| La IA lee mal una fórmula | Se ofrece un producto equivocado | Confianza por renglón, confirmación del cliente, validación del químico |
| Foto con datos sensibles enviada a un tercero | Incumplimiento de la Ley 1581 | Aviso y autorización, minimización, opción de modelo local |
| Fotos expuestas por URL | Fuga de datos de salud | Carpeta protegida o fuera de la parte pública |
| Cambios en nombres de modelos de Gemini | Fallas repentinas | Modelos configurables por `.env` y lista de respaldo |
