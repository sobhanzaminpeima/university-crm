-- Vertue CRM Hotfix SQL Patch (2026-05-08)
-- Apply in phpMyAdmin on the target CRM database.
-- Safe to run once on existing installs.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Study Fields catalog table
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

-- 2) Intake Terms catalog table
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

-- 3) New permissions
INSERT INTO `permissions` (`key`, `name`, `group_key`, `created_at`)
VALUES
  ('reports.view', 'View Reports', 'reports', NOW()),
  ('audit.view', 'View Audit Logs', 'users', NOW()),
  ('agent_performance.view', 'View Agent Performance', 'users', NOW()),
  ('study_fields.view', 'View Study Fields', 'settings', NOW()),
  ('study_fields.update', 'Manage Study Fields', 'settings', NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `group_key` = VALUES(`group_key`);

-- 4) Grant these permissions to role: admin
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`)
SELECT r.id, p.id, NOW()
FROM `roles` r
JOIN `permissions` p
  ON p.`key` IN (
    'reports.view',
    'audit.view',
    'agent_performance.view',
    'study_fields.view',
    'study_fields.update'
  )
WHERE r.`slug` = 'admin'
  AND NOT EXISTS (
    SELECT 1
    FROM `role_permissions` rp
    WHERE rp.`role_id` = r.id
      AND rp.`permission_id` = p.id
  );

SET FOREIGN_KEY_CHECKS = 1;

