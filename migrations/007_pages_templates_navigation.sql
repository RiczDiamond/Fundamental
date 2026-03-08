SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET @has_col_pages_template = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'pages'
    AND column_name = 'template'
);
SET @sql_col_pages_template = IF(
  @has_col_pages_template = 0,
  'ALTER TABLE `pages` ADD COLUMN `template` varchar(40) NOT NULL DEFAULT ''default'' AFTER `builder_json`',
  'SELECT 1'
);
PREPARE stmt_col_pages_template FROM @sql_col_pages_template;
EXECUTE stmt_col_pages_template;
DEALLOCATE PREPARE stmt_col_pages_template;

SET @has_col_pages_page_type = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'pages'
    AND column_name = 'page_type'
);
SET @sql_col_pages_page_type = IF(
  @has_col_pages_page_type = 0,
  'ALTER TABLE `pages` ADD COLUMN `page_type` varchar(40) NOT NULL DEFAULT ''basic_page'' AFTER `template`',
  'SELECT 1'
);
PREPARE stmt_col_pages_page_type FROM @sql_col_pages_page_type;
EXECUTE stmt_col_pages_page_type;
DEALLOCATE PREPARE stmt_col_pages_page_type;

SET @has_idx_pages_template = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'pages'
    AND index_name = 'idx_pages_template'
);
SET @sql_idx_pages_template = IF(
  @has_idx_pages_template = 0,
  'ALTER TABLE `pages` ADD KEY `idx_pages_template` (`template`)',
  'SELECT 1'
);
PREPARE stmt_idx_pages_template FROM @sql_idx_pages_template;
EXECUTE stmt_idx_pages_template;
DEALLOCATE PREPARE stmt_idx_pages_template;

SET @has_idx_pages_page_type = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'pages'
    AND index_name = 'idx_pages_page_type'
);
SET @sql_idx_pages_page_type = IF(
  @has_idx_pages_page_type = 0,
  'ALTER TABLE `pages` ADD KEY `idx_pages_page_type` (`page_type`)',
  'SELECT 1'
);
PREPARE stmt_idx_pages_page_type FROM @sql_idx_pages_page_type;
EXECUTE stmt_idx_pages_page_type;
DEALLOCATE PREPARE stmt_idx_pages_page_type;

UPDATE `pages`
SET
  `template` = CASE
    WHEN `slug` IN ('home', '') THEN 'landing'
    WHEN `slug` = 'contact' THEN 'contact'
    ELSE 'default'
  END,
  `page_type` = CASE
    WHEN `slug` IN ('home', '') THEN 'landing_page'
    WHEN `slug` = 'contact' THEN 'contact_page'
    ELSE 'basic_page'
  END
WHERE (`template` IS NULL OR `template` = '' OR `page_type` IS NULL OR `page_type` = '');

SET @has_col_menu_parent_id = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'menu_items'
    AND column_name = 'parent_id'
);
SET @sql_col_menu_parent_id = IF(
  @has_col_menu_parent_id = 0,
  'ALTER TABLE `menu_items` ADD COLUMN `parent_id` int DEFAULT NULL AFTER `id`',
  'SELECT 1'
);
PREPARE stmt_col_menu_parent_id FROM @sql_col_menu_parent_id;
EXECUTE stmt_col_menu_parent_id;
DEALLOCATE PREPARE stmt_col_menu_parent_id;

SET @has_idx_menu_items_parent = (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'menu_items'
    AND index_name = 'idx_menu_items_parent'
);
SET @sql_idx_menu_items_parent = IF(
  @has_idx_menu_items_parent = 0,
  'ALTER TABLE `menu_items` ADD KEY `idx_menu_items_parent` (`parent_id`)',
  'SELECT 1'
);
PREPARE stmt_idx_menu_items_parent FROM @sql_idx_menu_items_parent;
EXECUTE stmt_idx_menu_items_parent;
DEALLOCATE PREPARE stmt_idx_menu_items_parent;

SET @has_fk_menu_parent = (
  SELECT COUNT(*)
  FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'menu_items'
    AND constraint_name = 'fk_menu_items_parent'
);
SET @sql_fk_menu_parent = IF(
  @has_fk_menu_parent = 0,
  'ALTER TABLE `menu_items` ADD CONSTRAINT `fk_menu_items_parent` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk_menu_parent FROM @sql_fk_menu_parent;
EXECUTE stmt_fk_menu_parent;
DEALLOCATE PREPARE stmt_fk_menu_parent;

COMMIT;
