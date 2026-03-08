SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET @has_col_pages_template_preset = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'pages'
    AND column_name = 'template_preset'
);
SET @sql_col_pages_template_preset = IF(
  @has_col_pages_template_preset = 0,
  'ALTER TABLE `pages` ADD COLUMN `template_preset` varchar(80) DEFAULT NULL AFTER `page_type`',
  'SELECT 1'
);
PREPARE stmt_col_pages_template_preset FROM @sql_col_pages_template_preset;
EXECUTE stmt_col_pages_template_preset;
DEALLOCATE PREPARE stmt_col_pages_template_preset;

SET @has_col_pages_template_payload_json = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'pages'
    AND column_name = 'template_payload_json'
);
SET @sql_col_pages_template_payload_json = IF(
  @has_col_pages_template_payload_json = 0,
  'ALTER TABLE `pages` ADD COLUMN `template_payload_json` LONGTEXT DEFAULT NULL AFTER `template_preset`',
  'SELECT 1'
);
PREPARE stmt_col_pages_template_payload_json FROM @sql_col_pages_template_payload_json;
EXECUTE stmt_col_pages_template_payload_json;
DEALLOCATE PREPARE stmt_col_pages_template_payload_json;

SET @has_idx_pages_template_preset = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'pages'
    AND index_name = 'idx_pages_template_preset'
);
SET @sql_idx_pages_template_preset = IF(
  @has_idx_pages_template_preset = 0,
  'ALTER TABLE `pages` ADD KEY `idx_pages_template_preset` (`template_preset`)',
  'SELECT 1'
);
PREPARE stmt_idx_pages_template_preset FROM @sql_idx_pages_template_preset;
EXECUTE stmt_idx_pages_template_preset;
DEALLOCATE PREPARE stmt_idx_pages_template_preset;

COMMIT;
