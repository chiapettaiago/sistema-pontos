CREATE TABLE IF NOT EXISTS credenciais_passkey (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_tipo ENUM('sistema','funcionario') NOT NULL,
    usuario_id INT NOT NULL,
    credencial_id VARBINARY(1024) NOT NULL,
    chave_publica TEXT NOT NULL,
    contador_assinatura BIGINT UNSIGNED NOT NULL DEFAULT 0,
    transportes VARCHAR(255) NULL,
    nome VARCHAR(100) NOT NULL DEFAULT 'Meu dispositivo',
    ultimo_uso DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_passkey_credencial (credencial_id(255)),
    KEY idx_passkey_usuario (usuario_tipo, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
