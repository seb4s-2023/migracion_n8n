# 5. Despliegue y mantenimiento

> **Estado del despliegue: ⬜ pendiente.** La guía exige que aplicativo, motor y módulo de visión corran en un servidor accesible por internet. Hoy el sistema corre en XAMPP local. Completar este documento cuando se despliegue.

**URL del aplicativo:** _[pendiente]_
**Credenciales de prueba:** _[cliente: correo / contraseña] · [administrador: usuario / contraseña]_

## 5.1 Opciones de servidor (todas gratuitas o con nivel gratuito)

Verificar las condiciones vigentes de cada plan antes de decidir: los niveles gratuitos cambian.

| Opción | Ventajas | Cuidados |
|---|---|---|
| **Oracle Cloud Free Tier** (VPS) | Se parece a XAMPP; cron real; permite instalar Ollama | Instalación manual en Linux; cupo a veces limitado |
| **VPS propio** | Control total | Depende de lo que haya disponible |
| **Railway** | Despliegue con Docker, ofrece MySQL | El crédito gratuito se agota |
| **Render / Fly.io** | Sencillos con Docker | Necesitan una base MySQL externa; el disco puede no ser persistente |

**Elegida:** _[completar]_

## 5.2 Requisitos del servidor

- PHP 8.x con las extensiones `mysqli`, `curl`, `mbstring`, `fileinfo` y `openssl`.
- Apache (o Nginx con PHP-FPM) con HTTPS.
- MySQL 8 o MariaDB 10.
- cron.
- (Opcional) Ollama, si se usa el plan B de visión local. Un modelo de visión necesita memoria suficiente; en un servidor pequeño puede ser lento.

## 5.3 Pasos de despliegue

1. **Clonar el repositorio** en la carpeta pública del servidor.
2. **Instalar PHPMailer** en `libs/PHPMailer/src/` (Exception.php, PHPMailer.php y SMTP.php).
3. **Crear la base de datos** e importar, en este orden: `database.sql`, `log_automatizaciones.sql`, `formulas_subidas.sql`.
4. **Crear el `.env`** a partir de `.env.example` con los datos reales. Permisos restrictivos: `chmod 600 .env`.
5. **Carpeta de fotos fuera de la parte pública:**
   ```bash
   sudo mkdir -p /var/farmavida/formulas
   sudo chown www-data:www-data /var/farmavida/formulas
   sudo chmod 750 /var/farmavida/formulas
   ```
   y en el `.env`: `FORMULAS_DIR=/var/farmavida/formulas`.
6. **Crear el administrador:** abrir `crear_admin.php` una vez y **borrarlo** inmediatamente.
7. **Programar las tareas** (hora de Colombia):
   ```
   0 20 * * * TZ=America/Bogota /usr/bin/php /ruta/farmavida/enviar_resumen.php
   0 3  * * * TZ=America/Bogota /usr/bin/php /ruta/farmavida/borrar_formulas_antiguas.php
   ```
8. **Activar HTTPS** (por ejemplo con Let's Encrypt).
9. **Quitar `CURL_SSL_VERIFY=0`**: solo era necesario en XAMPP local.
10. **Limpiar el servidor:** borrar `prueba_ia.php`, `prueba_correo.php`, `prueba_formula.php`, `listar_modelos.php`, `crear_admin.php`, y quitar `APP_DEBUG=1`.
11. **(Opcional) Instalar Ollama** y descargar el modelo de visión para el plan B.

## 5.4 Lista de verificación después de desplegar

| Verificación | Estado |
|---|---|
| El sitio abre por HTTPS desde fuera de la red local | ⬜ |
| Registro, inicio de sesión y compra funcionan | ⬜ |
| Llega el correo de confirmación | ⬜ |
| Llegan las alertas por Telegram | ⬜ |
| El cron del resumen diario corre | ⬜ |
| La lectura de fórmulas funciona desde el celular | ⬜ |
| Las fotos no se pueden abrir por URL directa | ⬜ |
| `.env` no es accesible desde el navegador | ⬜ |
| Los archivos de prueba fueron eliminados | ⬜ |
| El plan B responde al quitar la clave de Gemini | ⬜ |

## 5.5 Mantenimiento

### Revisar la salud de las automatizaciones

```sql
-- Errores de las últimas 24 horas
SELECT flujo, fecha, intentos, resultado
FROM log_automatizaciones
WHERE estado = 'error' AND fecha >= NOW() - INTERVAL 1 DAY
ORDER BY fecha DESC;

-- Resumen por flujo de la última semana
SELECT flujo,
       COUNT(*)                              AS ejecuciones,
       SUM(estado = 'error')                 AS errores,
       ROUND(AVG(duracion_ms))               AS ms_promedio
FROM log_automatizaciones
WHERE fecha >= NOW() - INTERVAL 7 DAY
GROUP BY flujo;

-- Estado de las fórmulas subidas
SELECT estado, COUNT(*) AS total
FROM formulas_subidas
GROUP BY estado;
```

### Tareas periódicas

| Tarea | Frecuencia | Estado |
|---|---|---|
| Revisar errores en `log_automatizaciones` | Semanal | manual |
| Verificar que los modelos de Gemini del `.env` sigan vigentes (`listar_modelos.php`) | Mensual | manual |
| Respaldo (backup) de la base de datos | Diario | ⬜ por configurar |
| Borrar fotos de fórmulas antiguas (`borrar_formulas_antiguas.php`, 30 días por defecto) | Diario, con cron | ✅ implementado; ⬜ falta programar el cron en el servidor |
| Revisar `formulas_admin.php` (fórmulas y auditoría de la IA) | Semanal | manual |
| Rotar las claves de Gemini, SMTP y Telegram | Cada 6 meses o si hay sospecha de fuga | manual |
| Ampliar `medSinonimos()` con productos reales | Según las fórmulas nuevas | manual |
| Limpiar `log_automatizaciones` de más de 6 meses | Semestral | manual |

### Problemas frecuentes

| Síntoma | Causa probable | Solución |
|---|---|---|
| El chat siempre responde el mensaje de respaldo | Clave inválida, modelo retirado o cuota agotada | Revisar el log; actualizar `GEMINI_MODEL`; esperar la renovación de cuota |
| "Error interno al procesar la fórmula" | Falta la tabla o un archivo | Activar `APP_DEBUG=1` y leer el motivo entre corchetes |
| No llega el correo | `SMTP_PASS` incorrecta o PHPMailer ausente | Revisar la fila de error en el log |
| No llegan las alertas | Token o chat ID de Telegram incorrectos | Probar `enviar_resumen.php` por consola |
| Las fotos no se guardan | Permisos de `FORMULAS_DIR` | `chmod 750` y propietario del usuario del servidor web |
| El resumen diario no llega | cron mal configurado o zona horaria | Verificar el crontab y `TZ=America/Bogota` |

## 5.6 Cumplimiento y gobierno de datos

- **Autorización:** el aviso previo a la subida de la fórmula y el registro en `formulas_subidas`.
- **Finalidad limitada:** las fotos se usan solo para verificar disponibilidad.
- **Retención:** `borrar_formulas_antiguas.php` (plazo en `FORMULAS_DIAS_RETENCION`).
- **Derechos del titular:** definir cómo un cliente solicita ver, corregir o eliminar sus datos (consultas y reclamos, Ley 1581 de 2012).
- **Proveedor externo de IA:** documentar qué se envía a Gemini y verificar sus términos sobre uso de datos. Si no son aceptables para datos de salud, usar solo el modelo local (`VISION_SOLO_LOCAL=1`).

## 5.7 Evolución futura

- Cola persistente (Redis o similar) para las tareas diferidas.
- Vista de administrador para consultar las fotos subidas con control de acceso.
- Pruebas automáticas ejecutadas con integración continua.
- Ampliar el módulo de visión a otros casos, por ejemplo reconocimiento de productos por foto.
