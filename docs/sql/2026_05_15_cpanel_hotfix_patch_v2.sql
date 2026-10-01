-- Vertue CRM cPanel hotfix v2 (safe for missing tables)
-- Date: 2026-05-15

SET @db_name = DATABASE();

-- 0) Ensure base table exists (university_programs)
CREATE TABLE IF NOT EXISTS `university_programs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `university_id` BIGINT UNSIGNED NOT NULL,
  `degree_level` VARCHAR(60) NULL,
  `program_name` VARCHAR(255) NOT NULL,
  `language` VARCHAR(40) NULL,
  `duration` VARCHAR(40) NULL,
  `currency` VARCHAR(8) NULL,
  `fee` DECIMAL(12,2) NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `up_tenant_idx` (`tenant_id`),
  KEY `up_university_idx` (`university_id`),
  KEY `up_program_idx` (`program_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) university_programs.thesis_type compatibility
SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'university_programs'
        AND COLUMN_NAME = 'thesis_type'
    ),
    'SELECT 1',
    'ALTER TABLE `university_programs` ADD COLUMN `thesis_type` VARCHAR(20) NULL AFTER `degree_level`'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Study fields / intake tables
CREATE TABLE IF NOT EXISTS `study_fields` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `study_fields_tenant_name_uq` (`tenant_id`, `name`),
  KEY `study_fields_tenant_idx` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intake_terms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `intake_terms_tenant_name_uq` (`tenant_id`, `name`),
  KEY `intake_terms_tenant_idx` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Notification settings table
CREATE TABLE IF NOT EXISTS `tenant_notification_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `whatsapp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `whatsapp_api_url` VARCHAR(255) NULL,
  `whatsapp_api_token` TEXT NULL,
  `whatsapp_phone_number_id` VARCHAR(120) NULL,
  `email_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `sms_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `ai_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tns_tenant_uq` (`tenant_id`),
  KEY `tns_tenant_idx` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default notification rows only if tenants table exists
SET @tenants_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'tenants'
);
SET @sql_insert_tns = IF(
  @tenants_exists > 0,
  'INSERT INTO `tenant_notification_settings` (`tenant_id`, `created_at`, `updated_at`)
   SELECT t.id, NOW(), NOW()
   FROM `tenants` t
   LEFT JOIN `tenant_notification_settings` s ON s.tenant_id = t.id
   WHERE s.id IS NULL',
  'SELECT 1'
);
PREPARE stmt2 FROM @sql_insert_tns; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

