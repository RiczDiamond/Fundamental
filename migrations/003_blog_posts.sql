SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `blogs` (
  `id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(160) NOT NULL,
  `permalink` varchar(255) NOT NULL,
  `excerpt` text,
  `content` longtext,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `author_id` int DEFAULT NULL,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `blogs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blogs_slug` (`slug`),
  ADD UNIQUE KEY `uq_blogs_permalink` (`permalink`),
  ADD KEY `idx_blogs_status_published` (`status`,`published_at`),
  ADD KEY `idx_blogs_author` (`author_id`);

ALTER TABLE `blogs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `blogs`
  ADD CONSTRAINT `fk_blogs_author` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

COMMIT;
