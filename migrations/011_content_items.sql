CREATE TABLE IF NOT EXISTS `content_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(80) NOT NULL,
  `title` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `excerpt` text,
  `content` longtext,
  `featured_image` varchar(255) DEFAULT NULL,
  `payload_json` longtext,
  `meta_title` varchar(190) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_content_type_slug` (`type`,`slug`),
  KEY `idx_content_type_status_pub` (`type`,`status`,`published_at`),
  KEY `idx_content_starts_at` (`starts_at`),
  KEY `idx_content_ends_at` (`ends_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET @has_fk_content_created_by = (
  SELECT COUNT(*)
  FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'content_items'
    AND constraint_name = 'fk_content_items_created_by'
);
SET @sql_fk_content_created_by = IF(
  @has_fk_content_created_by = 0,
  'ALTER TABLE `content_items` ADD CONSTRAINT `fk_content_items_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk_content_created_by FROM @sql_fk_content_created_by;
EXECUTE stmt_fk_content_created_by;
DEALLOCATE PREPARE stmt_fk_content_created_by;

SET @has_fk_content_updated_by = (
  SELECT COUNT(*)
  FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'content_items'
    AND constraint_name = 'fk_content_items_updated_by'
);
SET @sql_fk_content_updated_by = IF(
  @has_fk_content_updated_by = 0,
  'ALTER TABLE `content_items` ADD CONSTRAINT `fk_content_items_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk_content_updated_by FROM @sql_fk_content_updated_by;
EXECUTE stmt_fk_content_updated_by;
DEALLOCATE PREPARE stmt_fk_content_updated_by;
