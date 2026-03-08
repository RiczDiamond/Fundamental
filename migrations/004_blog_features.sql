SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

ALTER TABLE `blogs`
  ADD COLUMN `featured_image` varchar(255) DEFAULT NULL AFTER `permalink`,
  ADD COLUMN `intro` text AFTER `featured_image`,
  ADD COLUMN `category` varchar(100) DEFAULT NULL AFTER `intro`,
  ADD COLUMN `tags` varchar(255) DEFAULT NULL AFTER `category`,
  ADD COLUMN `meta_title` varchar(255) DEFAULT NULL AFTER `tags`,
  ADD COLUMN `meta_description` varchar(320) DEFAULT NULL AFTER `meta_title`,
  ADD COLUMN `og_image` varchar(255) DEFAULT NULL AFTER `meta_description`,
  ADD COLUMN `view_count` int NOT NULL DEFAULT 0 AFTER `updated_at`,
  ADD COLUMN `like_count` int NOT NULL DEFAULT 0 AFTER `view_count`;

ALTER TABLE `blogs`
  ADD KEY `idx_blogs_category` (`category`),
  ADD KEY `idx_blogs_slug_status` (`slug`,`status`);

CREATE TABLE `blog_comments` (
  `id` int NOT NULL,
  `blog_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `author_name` varchar(120) NOT NULL,
  `author_email` varchar(190) DEFAULT NULL,
  `comment` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'approved',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `blog_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blog_comments_post` (`blog_id`,`created_at`),
  ADD KEY `idx_blog_comments_status` (`status`),
  ADD KEY `idx_blog_comments_user` (`user_id`);

ALTER TABLE `blog_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `blog_comments`
  ADD CONSTRAINT `fk_blog_comments_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_blog_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `blog_likes` (
  `id` int NOT NULL,
  `blog_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `session_key` varchar(128) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `blog_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blog_likes_post_session` (`blog_id`,`session_key`),
  ADD KEY `idx_blog_likes_post` (`blog_id`),
  ADD KEY `idx_blog_likes_user` (`user_id`);

ALTER TABLE `blog_likes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `blog_likes`
  ADD CONSTRAINT `fk_blog_likes_blog` FOREIGN KEY (`blog_id`) REFERENCES `blogs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_blog_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

COMMIT;
