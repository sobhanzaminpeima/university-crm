-- Vertue CRM latest database patch - 2026-06-09
-- Run this on the current CRM database when artisan migrate cannot be used.
-- It adds SaaS subdomain fields, new SaaS feature flags, and cleans duplicate
-- applications/programs created before the latest application fixes.

SET @database_name = DATABASE();

-- ---------------------------------------------------------------------------
-- SaaS tenant subdomain/access fields
-- ---------------------------------------------------------------------------
SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tenants ADD COLUMN subdomain VARCHAR(80) NULL AFTER slug',
        'SELECT "tenants.subdomain already exists"'
    )
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'tenants' AND column_name = 'subdomain'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tenants ADD COLUMN custom_domain VARCHAR(190) NULL AFTER subdomain',
        'SELECT "tenants.custom_domain already exists"'
    )
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'tenants' AND column_name = 'custom_domain'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tenants ADD COLUMN access_url VARCHAR(255) NULL AFTER custom_domain',
        'SELECT "tenants.access_url already exists"'
    )
    FROM information_schema.columns
    WHERE table_schema = @database_name AND table_name = 'tenants' AND column_name = 'access_url'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE tenants
SET subdomain = LOWER(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(NULLIF(slug, ''), name)), ' ', '-'), '_', '-'), '.', '-'))
WHERE subdomain IS NULL OR subdomain = '';

UPDATE tenants
SET access_url = CONCAT('https://', subdomain, '.YOUR-BASE-DOMAIN.com')
WHERE (access_url IS NULL OR access_url = '') AND subdomain IS NOT NULL AND subdomain != '';

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE tenants ADD UNIQUE INDEX tenants_subdomain_unique (subdomain)',
        'SELECT "tenants_subdomain_unique already exists"'
    )
    FROM information_schema.statistics
    WHERE table_schema = @database_name AND table_name = 'tenants' AND index_name = 'tenants_subdomain_unique'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- SaaS feature flags added in the latest build
-- ---------------------------------------------------------------------------
INSERT INTO features (`key`, `name`, `created_at`, `updated_at`) VALUES
('advanced_search', 'Advanced Search', NOW(), NOW()),
('mobile_bottom_nav', 'Mobile Bottom Navigation', NOW(), NOW()),
('tenant_backup', 'Tenant Backup', NOW(), NOW()),
('university_program_dedupe', 'University Program Deduplication', NOW(), NOW()),
('application_dedupe', 'Application Deduplication', NOW(), NOW()),
('study_catalog_cleanup', 'Study Catalog Cleanup', NOW(), NOW()),
('whatsapp_notifications', 'WhatsApp Notifications', NOW(), NOW()),
('api_tokens', 'API Tokens', NOW(), NOW()),
('automation_rules', 'Automation Rules', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `updated_at` = NOW();

INSERT INTO tenant_features (`tenant_id`, `feature_id`, `is_enabled`, `created_at`, `updated_at`)
SELECT tenants.id, features.id, 1, NOW(), NOW()
FROM tenants
JOIN features ON features.`key` IN (
    'advanced_search',
    'mobile_bottom_nav',
    'tenant_backup',
    'university_program_dedupe',
    'application_dedupe',
    'study_catalog_cleanup',
    'whatsapp_notifications',
    'api_tokens',
    'automation_rules'
)
LEFT JOIN tenant_features existing
    ON existing.tenant_id = tenants.id AND existing.feature_id = features.id
WHERE existing.id IS NULL;

-- ---------------------------------------------------------------------------
-- Clean duplicate applications:
-- same tenant + student + university + program + intake
-- ---------------------------------------------------------------------------
DELETE duplicate_rows
FROM applications duplicate_rows
JOIN (
    SELECT
        tenant_id,
        student_id,
        university_id,
        LOWER(TRIM(program)) AS n_program,
        LOWER(TRIM(intake)) AS n_intake,
        MIN(id) AS keep_id
    FROM applications
    GROUP BY tenant_id, student_id, university_id, LOWER(TRIM(program)), LOWER(TRIM(intake))
    HAVING COUNT(*) > 1
) grouped
    ON grouped.tenant_id = duplicate_rows.tenant_id
    AND grouped.student_id = duplicate_rows.student_id
    AND grouped.university_id = duplicate_rows.university_id
    AND grouped.n_program = LOWER(TRIM(duplicate_rows.program))
    AND grouped.n_intake = LOWER(TRIM(duplicate_rows.intake))
WHERE duplicate_rows.id != grouped.keep_id;

-- ---------------------------------------------------------------------------
-- Clean duplicate university programs:
-- same tenant + university + program name + degree + language + thesis type.
-- Fee type is intentionally NOT part of duplicate identity anymore.
-- ---------------------------------------------------------------------------
DELETE duplicate_rows
FROM university_programs duplicate_rows
JOIN (
    SELECT
        tenant_id,
        university_id,
        LOWER(TRIM(program_name)) AS n_program_name,
        LOWER(TRIM(degree_level)) AS n_degree_level,
        LOWER(TRIM(language)) AS n_language,
        LOWER(TRIM(COALESCE(thesis_type, ''))) AS n_thesis_type,
        MIN(id) AS keep_id
    FROM university_programs
    WHERE program_name IS NOT NULL AND program_name != ''
    GROUP BY
        tenant_id,
        university_id,
        LOWER(TRIM(program_name)),
        LOWER(TRIM(degree_level)),
        LOWER(TRIM(language)),
        LOWER(TRIM(COALESCE(thesis_type, '')))
    HAVING COUNT(*) > 1
) grouped
    ON grouped.tenant_id = duplicate_rows.tenant_id
    AND grouped.university_id = duplicate_rows.university_id
    AND grouped.n_program_name = LOWER(TRIM(duplicate_rows.program_name))
    AND grouped.n_degree_level = LOWER(TRIM(duplicate_rows.degree_level))
    AND grouped.n_language = LOWER(TRIM(duplicate_rows.language))
    AND grouped.n_thesis_type = LOWER(TRIM(COALESCE(duplicate_rows.thesis_type, '')))
WHERE duplicate_rows.id != grouped.keep_id;

-- ---------------------------------------------------------------------------
-- Mark matching Laravel migrations as completed when patch is applied manually.
-- Safe because migration names are unique.
-- ---------------------------------------------------------------------------
INSERT INTO migrations (`migration`, `batch`)
SELECT '2026_06_09_000900_dedupe_applications_and_saas_subdomains',
       COALESCE((SELECT MAX(batch) + 1 FROM migrations existing_batches), 1)
WHERE NOT EXISTS (
    SELECT 1 FROM migrations WHERE migration = '2026_06_09_000900_dedupe_applications_and_saas_subdomains'
);

INSERT INTO migrations (`migration`, `batch`)
SELECT '2026_06_09_001000_dedupe_university_programs_relaxed_key',
       COALESCE((SELECT MAX(batch) FROM migrations existing_batches), 1)
WHERE NOT EXISTS (
    SELECT 1 FROM migrations WHERE migration = '2026_06_09_001000_dedupe_university_programs_relaxed_key'
);

