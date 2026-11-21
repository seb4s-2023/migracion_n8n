-- FARMAVIDA: registro de cada ejecución de una automatización
USE farmavida;

CREATE TABLE IF NOT EXISTS log_automatizaciones (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    flujo       VARCHAR(60) NOT NULL,
    estado      ENUM('ok','error') NOT NULL,
    intentos    TINYINT NOT NULL DEFAULT 1,
    entrada     TEXT DEFAULT NULL,
    resultado   TEXT DEFAULT NULL,
    duracion_ms INT DEFAULT NULL,
    fecha       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_flujo_fecha (flujo, fecha)
) ENGINE=InnoDB;
