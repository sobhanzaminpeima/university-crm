-- Add tuition/program fee type compatibility + clean invalid study fields
SET @db_name = DATABASE();

-- universities.tuition_fee_type
SET @sql = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='universities' AND COLUMN_NAME='tuition_fee_type'
    ),
    'SELECT 1',
    'ALTER TABLE `universities` ADD COLUMN `tuition_fee_type` VARCHAR(30) NULL AFTER `tuition_range`'
  )
);
PREPARE stmt1 FROM @sql; EXECUTE stmt1; DEALLOCATE PREPARE stmt1;

-- university_programs.fee_type
SET @sql2 = (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='university_programs' AND COLUMN_NAME='fee_type'
    ),
    'SELECT 1',
    'ALTER TABLE `university_programs` ADD COLUMN `fee_type` VARCHAR(30) NULL AFTER `fee`'
  )
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- Delete garbage study_fields such as pure numeric/hyphen values
DELETE FROM `study_fields`
WHERE TRIM(`name`) REGEXP '^[0-9\\-[:space:]]+$';

