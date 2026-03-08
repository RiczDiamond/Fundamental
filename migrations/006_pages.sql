CREATE TABLE IF NOT EXISTS `pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(190) NOT NULL,
  `slug` varchar(190) NOT NULL,
  `excerpt` text,
  `content` longtext,
  `builder_json` longtext,
  `meta_title` varchar(190) DEFAULT NULL,
  `meta_description` varchar(255) DEFAULT NULL,
  `status` enum('draft','published','archived') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pages_slug` (`slug`),
  KEY `idx_pages_status_published_at` (`status`,`published_at`),
  KEY `idx_pages_slug_status` (`slug`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET @has_fk_pages_created_by = (
  SELECT COUNT(*)
  FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'pages'
    AND constraint_name = 'fk_pages_created_by'
);
SET @sql_fk_pages_created_by = IF(
  @has_fk_pages_created_by = 0,
  'ALTER TABLE `pages` ADD CONSTRAINT `fk_pages_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk_pages_created_by FROM @sql_fk_pages_created_by;
EXECUTE stmt_fk_pages_created_by;
DEALLOCATE PREPARE stmt_fk_pages_created_by;

SET @has_fk_pages_updated_by = (
  SELECT COUNT(*)
  FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE()
    AND table_name = 'pages'
    AND constraint_name = 'fk_pages_updated_by'
);
SET @sql_fk_pages_updated_by = IF(
  @has_fk_pages_updated_by = 0,
  'ALTER TABLE `pages` ADD CONSTRAINT `fk_pages_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk_pages_updated_by FROM @sql_fk_pages_updated_by;
EXECUTE stmt_fk_pages_updated_by;
DEALLOCATE PREPARE stmt_fk_pages_updated_by;

INSERT IGNORE INTO `pages` (`title`, `slug`, `excerpt`, `content`, `status`, `published_at`)
VALUES (
  'Home',
  'home',
  'Welkom op Fundamental CMS.',
  '<p>Dit is de homepage uit de database.</p>',
  'published',
  NOW()
);