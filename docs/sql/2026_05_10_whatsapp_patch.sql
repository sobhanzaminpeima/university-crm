-- WhatsApp settings + SaaS feature patch
-- Run this in phpMyAdmin on your cPanel database (same DB used by CRM).

CREATE TABLE IF NOT EXISTS `tenant_notification_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `whatsapp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `whatsapp_number` varchar(30) DEFAULT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'custom',
  `api_url` varchar(255) DEFAULT NULL,
  `api_token` varchar(255) DEFAULT NULL,
  `notify_new_student` tinyint(1) NOT NULL DEFAULT 0,
  `notify_application_update` tinyint(1) NOT NULL DEFAULT 0,
  `notify_document_update` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_notification_settings_tenant_unique` (`tenant_id`),
  CONSTRAINT `tenant_notification_settings_tenant_fk` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `features` (`key`, `name`, `created_at`, `updated_at`)
SELECT 'whatsapp_notifications', 'WhatsApp Notifications', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `features` WHERE `key` = 'whatsapp_notifications'
);

INSERT INTO `tenant_features` (`tenant_id`, `feature_id`, `is_enabled`, `created_at`, `updated_at`)
SELECT t.`id`, f.`id`, 0, NOW(), NOW()
FROM `tenants` t
JOIN `features` f ON f.`key` = 'whatsapp_notifications'
LEFT JOIN `tenant_features` tf ON tf.`tenant_id` = t.`id` AND tf.`feature_id` = f.`id`
WHERE tf.`id` IS NULL;

INSERT INTO `tenant_notification_settings`
(`tenant_id`, `whatsapp_enabled`, `provider`, `notify_new_student`, `notify_application_update`, `notify_document_update`, `created_at`, `updated_at`)
SELECT t.`id`, 0, 'custom', 0, 0, 0, NOW(), NOW()
FROM `tenants` t
LEFT JOIN `tenant_notification_settings` s ON s.`tenant_id` = t.`id`
WHERE s.`id` IS NULL;

