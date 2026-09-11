-- Identificador técnico único para manutenção segura das batidas.
ALTER TABLE pontos
    ADD COLUMN edit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (edit_id);
