-- FARMAVIDA: registro de cada foto de fórmula subida al chatbot
-- (quién la subió, cuándo, y qué resultó). La imagen vive en storage/formulas/
USE farmavida;

CREATE TABLE IF NOT EXISTS formulas_subidas (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id     INT DEFAULT NULL,                 -- NULL si no había sesión
    archivo        VARCHAR(80) NOT NULL,             -- nombre aleatorio en storage/formulas/
    mime           VARCHAR(30) NOT NULL,
    tamano_kb      INT NOT NULL,
    proveedor      VARCHAR(20) DEFAULT NULL,         -- gemini | ollama
    estado         ENUM('procesando','leida','ilegible','fallo_ia') NOT NULL DEFAULT 'procesando',
    meds_leidos    TINYINT DEFAULT NULL,
    meds_disponibles TINYINT DEFAULT NULL,
    fecha          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha),
    CONSTRAINT fk_formula_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB;
