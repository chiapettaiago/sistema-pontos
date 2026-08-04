-- compat_biometria.sql
-- Ajustes para compatibilidade do cadastro e login facial/digital

CREATE TABLE IF NOT EXISTS biometricos_faciais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    descritores LONGTEXT NOT NULL,
    amostras INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_biometricos_faciais_funcionario (funcionario_id),
    INDEX idx_biometricos_faciais_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS biometricos_digitais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    funcionario_id INT NOT NULL,
    digital_template LONGTEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_biometricos_digitais_funcionario (funcionario_id),
    INDEX idx_biometricos_digitais_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE biometricos_faciais
    ADD COLUMN IF NOT EXISTS descritores LONGTEXT NOT NULL,
    ADD COLUMN IF NOT EXISTS amostras INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS ativo TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE biometricos_digitais
    ADD COLUMN IF NOT EXISTS digital_template LONGTEXT NOT NULL,
    ADD COLUMN IF NOT EXISTS ativo TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
