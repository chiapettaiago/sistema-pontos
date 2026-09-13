CREATE TABLE IF NOT EXISTS links_ponto_publico (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT NOT NULL,
    criado_por INT NULL,
    expira_em DATETIME NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_link_publico_empresa_criado (empresa_id, criado_em),
    KEY idx_link_publico_expiracao (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
