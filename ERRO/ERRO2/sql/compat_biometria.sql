-- compat_biometria.sql
-- Versao segura para alinhar biometria entre local e servidor
-- Compatível com MySQL 5.7 / MariaDB sem usar ADD COLUMN IF NOT EXISTS

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS `biometricos_faciais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `funcionario_id` int(11) NOT NULL,
  `descritores` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'face-api.js',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bf_funcionario` (`funcionario_id`),
  KEY `idx_bf_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `biometricos_digitais` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `funcionario_id` int(11) NOT NULL,
  `digital_template` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `digital_formato` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'ISO_19794_2',
  `ativo` tinyint(1) DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bd_funcionario` (`funcionario_id`),
  KEY `idx_bd_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adiciona colunas somente se ainda não existirem
SET @db_name := DATABASE();

-- biometricos_faciais.modelo
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `biometricos_faciais` ADD COLUMN `modelo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT ''face-api.js''',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'biometricos_faciais'
    AND column_name = 'modelo'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- biometricos_faciais.updated_at
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `biometricos_faciais` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'biometricos_faciais'
    AND column_name = 'updated_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- biometricos_digitais.digital_formato
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `biometricos_digitais` ADD COLUMN `digital_formato` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT ''ISO_19794_2''',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'biometricos_digitais'
    AND column_name = 'digital_formato'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- biometricos_digitais.updated_at
SET @sql := (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `biometricos_digitais` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = @db_name
    AND table_name = 'biometricos_digitais'
    AND column_name = 'updated_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

