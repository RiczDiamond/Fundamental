SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

ALTER TABLE `blogs`
  ADD COLUMN `builder_json` longtext NULL AFTER `content`,
  ADD COLUMN `scheduled_at` datetime NULL AFTER `published_at`,
  ADD COLUMN `last_autosaved_at` datetime NULL AFTER `scheduled_at`;

ALTER TABLE `blogs`
  ADD KEY `idx_blogs_status_scheduled` (`status`,`scheduled_at`);

CREATE TABLE `blog_revisions` (
  `id` int NOT NULL,
  `blog_id` int NOT NULL,
  `editor_id` int DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `intro` text,
  `category` varchar(100) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` varchar(320) DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `excerpt` text,
  `content` longtext,
  `builder_json` longtext,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `blog_revisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog_revisions_blog_created` (`blog_id`,`created_at`),
  ADD KEY `idx_blog_revisions_editor` (`editor_id`);

ALTER TABLE `blog_revisions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `blog_revisions`
  ADD CONSTRAINT `fk_blog_revisions_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_blog_revisions_editor` FOREIGN KEY (`editor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `blog_autosaves` (
  `id` int NOT NULL,
  `blog_id` int NOT NULL,
  `editor_id` int DEFAULT NULL,
  `payload_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `blog_autosaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog_autosaves_blog_editor` (`blog_id`,`editor_id`,`created_at`);

ALTER TABLE `blog_autosaves`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `blog_autosaves`
  ADD CONSTRAINT `fk_blog_autosaves_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_blog_autosaves_editor` FOREIGN KEY (`editor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `blog_preview_tokens` (
  `id` int NOT NULL,
  `blog_id` int NOT NULL,
  `token` varchar(128) NOT NULL,
  `required_role` varchar(20) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `blog_preview_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blog_preview_tokens_token` (`token`),
  ADD KEY `idx_blog_preview_tokens_blog` (`blog_id`),
  ADD KEY `idx_blog_preview_tokens_expires` (`expires_at`);

ALTER TABLE `blog_preview_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `blog_preview_tokens`
  ADD CONSTRAINT `fk_blog_preview_tokens_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_blog_preview_tokens_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `media_folders` (
  `id` int NOT NULL,
  `name` varchar(120) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `media_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_media_folders_parent` (`parent_id`),
  ADD UNIQUE KEY `uq_media_folder_name_parent` (`name`,`parent_id`);

ALTER TABLE `media_folders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `media_folders`
  ADD CONSTRAINT `fk_media_folders_parent` FOREIGN KEY (`parent_id`) REFERENCES `media_folders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_media_folders_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `media_items` (
  `id` int NOT NULL,
  `folder_id` int DEFAULT NULL,
  `filename` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `size_bytes` int DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `crop_x` int DEFAULT NULL,
  `crop_y` int DEFAULT NULL,
  `crop_w` int DEFAULT NULL,
  `crop_h` int DEFAULT NULL,
  `resize_w` int DEFAULT NULL,
  `resize_h` int DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `media_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_media_items_folder_created` (`folder_id`,`created_at`),
  ADD KEY `idx_media_items_uploaded_by` (`uploaded_by`);

ALTER TABLE `media_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `media_items`
  ADD CONSTRAINT `fk_media_items_folder` FOREIGN KEY (`folder_id`) REFERENCES `media_folders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_media_items_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `menu_items` (
  `id` int NOT NULL,
  `location` varchar(50) NOT NULL DEFAULT 'main',
  `label` varchar(120) NOT NULL,
  `url` varchar(255) NOT NULL,
  `sort_order` int NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_menu_items_location_order` (`location`,`sort_order`,`id`);

ALTER TABLE `menu_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `menu_items`
  ADD CONSTRAINT `fk_menu_items_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

INSERT IGNORE INTO `menu_items` (`location`, `label`, `url`, `sort_order`, `is_active`, `created_by`)
VALUES
('main', 'Home', '/', 10, 1, NULL),
('main', 'Blog', '/blog', 20, 1, NULL),
('main', 'Dashboard', '/dashboard', 30, 1, NULL);

COMMIT;