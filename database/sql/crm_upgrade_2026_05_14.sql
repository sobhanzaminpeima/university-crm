-- CRM Upgrade Patch (2026-05-14)
-- Safe to run in phpMyAdmin.
-- It creates missing tables/columns for:
-- 1) Student lifecycle + lead source + application follow-up
-- 2) Automation notification policy
-- 3) Integration settings (Email/SMS/AI)
-- 4) Lead source costs for KPI (CAC/ROI)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- 1) students: lifecycle + lead source
-- -----------------------------------------------------
SET @db_name = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND COLUMN_NAME = 'lead_source'
    ),
    'SELECT 1',
    'ALTER TABLE `students` ADD COLUMN `lead_source` VARCHAR(60) NULL AFTER `target_country`'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND COLUMN_NAME = 'lifecycle_stage'
    ),
    'SELECT 1',
    'ALTER TABLE `students` ADD COLUMN `lifecycle_stage` VARCHAR(40) NULL AFTER `stage`'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND INDEX_NAME = 'students_lead_source_idx'
    ),
    'SELECT 1',
    'CREATE INDEX `students_lead_source_idx` ON `students` (`lead_source`)'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND INDEX_NAME = 'students_lifecycle_stage_idx'
    ),
    'SELECT 1',
    'CREATE INDEX `students_lifecycle_stage_idx` ON `students` (`lifecycle_stage`)'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 2) applications: next follow-up
-- -----------------------------------------------------
SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'applications' AND COLUMN_NAME = 'next_followup_at'
    ),
    'SELECT 1',
    'ALTER TABLE `applications` ADD COLUMN `next_followup_at` DATETIME NULL AFTER `deadline`'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 2.1) university_programs: thesis_type (optional compatibility)
-- -----------------------------------------------------
SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'university_programs' AND COLUMN_NAME = 'thesis_type'
    ),
    'SELECT 1',
    'ALTER TABLE `university_programs` ADD COLUMN `thesis_type` VARCHAR(20) NULL AFTER `degree_level`'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'applications' AND INDEX_NAME = 'applications_next_followup_idx'
    ),
    'SELECT 1',
    'CREATE INDEX `applications_next_followup_idx` ON `applications` (`next_followup_at`)'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------
-- 3) tenant notification settings (WhatsApp + docs pending policy)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenant_notification_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `whatsapp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `whatsapp_number` VARCHAR(30) NULL,
  `provider` VARCHAR(20) NOT NULL DEFAULT 'custom',
  `api_url` VARCHAR(255) NULL,
  `api_token` VARCHAR(255) NULL,
  `notify_new_student` TINYINT(1) NOT NULL DEFAULT 0,
  `notify_application_update` TINYINT(1) NOT NULL DEFAULT 0,
  `notify_document_update` TINYINT(1) NOT NULL DEFAULT 0,
  `docs_pending_days` SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  `docs_pending_task_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `docs_pending_email_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `docs_pending_whatsapp_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `docs_pending_sms_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_notification_settings_tenant_uq` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4) tenant integration settings (Email/SMS/AI)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenant_integration_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `email_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `email_from_address` VARCHAR(190) NULL,
  `email_from_name` VARCHAR(120) NULL,
  `sms_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `sms_api_url` VARCHAR(255) NULL,
  `sms_api_token` VARCHAR(255) NULL,
  `ai_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `ai_provider` VARCHAR(40) NOT NULL DEFAULT 'openai',
  `ai_model` VARCHAR(80) NULL,
  `ai_api_key` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_integration_settings_tenant_uq` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 5) lead source monthly costs (for KPI: CAC / ROI)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `lead_source_costs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NOT NULL,
  `source_key` VARCHAR(60) NOT NULL,
  `monthly_cost` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_source_costs_tenant_source_uq` (`tenant_id`, `source_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 6) seed defaults for existing tenants
-- -----------------------------------------------------
INSERT INTO `tenant_notification_settings`
(`tenant_id`,`whatsapp_enabled`,`provider`,`notify_new_student`,`notify_application_update`,`notify_document_update`,
 `docs_pending_days`,`docs_pending_task_enabled`,`docs_pending_email_enabled`,`docs_pending_whatsapp_enabled`,`docs_pending_sms_enabled`,
 `created_at`,`updated_at`)
SELECT t.id, 0, 'custom', 0, 0, 0, 3, 1, 0, 1, 0, NOW(), NOW()
FROM `tenants` t
LEFT JOIN `tenant_notification_settings` s ON s.tenant_id = t.id
WHERE s.tenant_id IS NULL;

INSERT INTO `tenant_integration_settings`
(`tenant_id`,`email_enabled`,`sms_enabled`,`ai_enabled`,`ai_provider`,`created_at`,`updated_at`)
SELECT t.id, 0, 0, 0, 'openai', NOW(), NOW()
FROM `tenants` t
LEFT JOIN `tenant_integration_settings` s ON s.tenant_id = t.id
WHERE s.tenant_id IS NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- End of patch
