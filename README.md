# FarmaVida

Aplicativo web empresarial para una farmacia online en Bucaramanga. Permite vender productos, atender clientes con un chatbot y **consultar la disponibilidad de los medicamentos de una fórmula médica a partir de una foto**.

**Asignatura:** Desarrollo de Aplicaciones Empresariales · **Corte:** tercero
**Integrante:** Sebastian
**Aplicativo desplegado:** _[URL pendiente]_ · **Video:** _[enlace pendiente]_

## Qué incluye este corte

1. **Automatización propia en código.** Los flujos que antes vivían en n8n ahora son servicios PHP con reintentos, manejo de errores y registro de cada ejecución en MySQL.
2. **IA con herramientas gratuitas.** Gemini (nivel gratuito) para el chat y las descripciones, con modelos de respaldo.
3. **Visión por computador.** El cliente sube una foto de su fórmula desde el chat; la IA extrae los medicamentos con un nivel de confianza y el sistema indica cuáles hay en inventario.

## Documentación

| Fase | Documento |
|---|---|
| 1. Análisis y requisitos | [docs/01-analisis-requisitos/analisis-y-requisitos.md](docs/01-analisis-requisitos/analisis-y-requisitos.md) |
| 2. Diseño | [docs/02-diseno/diseno-del-sistema.md](docs/02-diseno/diseno-del-sistema.md) |
| 3. Implementación | [docs/03-implementacion/implementacion.md](docs/03-implementacion/implementacion.md) |
| 4. Pruebas | [docs/04-pruebas/plan-y-resultados-de-pruebas.md](docs/04-pruebas/plan-y-resultados-de-pruebas.md) |
| 5. Despliegue y mantenimiento | [docs/05-despliegue-y-mantenimiento/despliegue-y-mantenimiento.md](docs/05-despliegue-y-mantenimiento/despliegue-y-mantenimiento.md) |
| Resumen de funcionalidades y cambios | [docs/06-resumen-de-cambios/resumen-de-funcionalidades-y-cambios.md](docs/06-resumen-de-cambios/resumen-de-funcionalidades-y-cambios.md) |

## Instalación local (XAMPP)

**Requisitos:** PHP 8, MySQL/MariaDB, extensiones `mysqli`, `curl`, `mbstring`, `fileinfo`.

1. Copia el proyecto a `C:\xampp\htdocs\farmavida\`.
2. Instala **PHPMailer** en `libs/PHPMailer/src/` (`Exception.php`, `PHPMailer.php`, `SMTP.php`).
3. En phpMyAdmin importa, en este orden: `database.sql`, `log_automatizaciones.sql`, `formulas_subidas.sql`.
4. Copia `.env.example` a `.env` y completa los valores (ver más abajo).
5. Crea el primer administrador abriendo `http://localhost/farmavida/crear_admin.php` y **borra ese archivo después**.
6. Abre `http://localhost/farmavida/`.

## Variables de entorno

Todas están documentadas en [`.env.example`](.env.example). Las claves nunca van en el código ni en Git.

| Variable | Para qué sirve |
|---|---|
| `DB_*` | Conexión a MySQL |
| `GEMINI_API_KEY`, `GEMINI_MODEL`, `GEMINI_MODEL_RESPALDO` | IA principal y modelos de respaldo |
| `OLLAMA_URL`, `OLLAMA_VISION_MODEL`, `VISION_SOLO_LOCAL` | IA local (plan B y privacidad) |
| `SMTP_*` | Correo de confirmación de compra |
| `TELEGRAM_*` | Alertas al administrador |
| `FORMULAS_DIR` | Carpeta privada de las fotos de fórmulas |

## Tareas programadas

Resumen diario de ventas por Telegram, todos los días a las 8 p.m. (hora de Colombia):

```
# Linux (cron)
0 20 * * * TZ=America/Bogota /usr/bin/php /ruta/farmavida/enviar_resumen.php
0 3  * * * TZ=America/Bogota /usr/bin/php /ruta/farmavida/borrar_formulas_antiguas.php
# Windows (Programador de tareas)
C:\xampp\php\php.exe C:\xampp\htdocs\farmavida\enviar_resumen.php
```

## Pruebas rápidas por consola

```
php prueba_correo.php           # correo de confirmación
php prueba_ia.php               # chat y descripción con Gemini
php prueba_formula.php carpeta  # lectura de fórmulas con tus fotos
php tests/medicamentos_test.php # pruebas unitarias de la búsqueda en catálogo
```

> Estos scripts y `listar_modelos.php` son solo para desarrollo: **bórralos antes de desplegar**.

## Despliegue

Ver [docs/05-despliegue-y-mantenimiento/despliegue-y-mantenimiento.md](docs/05-despliegue-y-mantenimiento/despliegue-y-mantenimiento.md).

## Seguridad y datos personales

El sistema maneja datos sensibles (condición de salud, EPS y fotos de fórmulas). Medidas, riesgos y pendientes en [docs/01-analisis-requisitos/analisis-y-requisitos.md](docs/01-analisis-requisitos/analisis-y-requisitos.md#17-datos-personales-ley-1581-de-2012).

## Aviso

FarmaVida **no diagnostica, no receta y no indica dosis**. La lectura de la fórmula con IA es una ayuda para consultar disponibilidad y no reemplaza la validación del químico farmacéutico.
