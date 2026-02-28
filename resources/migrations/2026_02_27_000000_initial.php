<?php
declare(strict_types=1);

return [
		'up' => function (PDO $link) {
				$sql = <<<'SQL'
				CREATE TABLE IF NOT EXISTS `commentmeta` (
					`meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`comment_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`meta_key` VARCHAR(255) DEFAULT NULL,
					`meta_value` LONGTEXT,
					PRIMARY KEY (`meta_id`),
					KEY `comment_id` (`comment_id`),
					KEY `meta_key` (`meta_key`(191))
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `comments` (
					`comment_ID` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`comment_post_ID` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`comment_author` TINYTEXT,
					`comment_author_email` VARCHAR(100) DEFAULT NULL,
					`comment_author_url` VARCHAR(200) DEFAULT NULL,
					`comment_author_IP` VARCHAR(100) DEFAULT NULL,
					  `comment_date` DATETIME NULL DEFAULT NULL,
					  `comment_date_gmt` DATETIME NULL DEFAULT NULL,
					`comment_content` TEXT,
					`comment_karma` INT(11) NOT NULL DEFAULT '0',
					`comment_approved` VARCHAR(20) NOT NULL DEFAULT '1',
					`comment_agent` VARCHAR(255) DEFAULT NULL,
					`comment_type` VARCHAR(20) DEFAULT NULL,
					`comment_parent` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					PRIMARY KEY (`comment_ID`),
					KEY `comment_post_ID` (`comment_post_ID`),
					KEY `comment_approved_date_gmt` (`comment_approved`,`comment_date_gmt`),
					KEY `comment_date_gmt` (`comment_date_gmt`),
					KEY `comment_parent` (`comment_parent`),
					KEY `comment_author_email` (`comment_author_email`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `links` (
					`link_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`link_url` VARCHAR(255) DEFAULT NULL,
					`link_name` VARCHAR(255) DEFAULT NULL,
					`link_image` VARCHAR(255) DEFAULT NULL,
					`link_target` VARCHAR(25) DEFAULT NULL,
					`link_description` VARCHAR(255) DEFAULT NULL,
					`link_visible` VARCHAR(20) NOT NULL DEFAULT 'Y',
					`link_owner` BIGINT(20) UNSIGNED NOT NULL DEFAULT '1',
					`link_rating` INT(11) NOT NULL DEFAULT '0',
					  `link_updated` DATETIME NULL DEFAULT NULL,
					`link_rel` VARCHAR(255) DEFAULT NULL,
					`link_notes` MEDIUMTEXT,
					`link_rss` VARCHAR(255) DEFAULT NULL,
					PRIMARY KEY (`link_id`),
					KEY `link_visible` (`link_visible`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `options` (
					`option_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`option_name` VARCHAR(64) NOT NULL,
					`option_value` LONGTEXT,
					`autoload` VARCHAR(20) NOT NULL DEFAULT 'yes',
					PRIMARY KEY (`option_id`),
					UNIQUE KEY `option_name` (`option_name`),
					KEY `autoload` (`autoload`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `postmeta` (
					`meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`post_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`meta_key` VARCHAR(255) DEFAULT NULL,
					`meta_value` LONGTEXT,
					PRIMARY KEY (`meta_id`),
					KEY `post_id` (`post_id`),
					KEY `meta_key` (`meta_key`(191))
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `posts` (
					`ID` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`post_author` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					  `post_date` DATETIME NULL DEFAULT NULL,
					  `post_date_gmt` DATETIME NULL DEFAULT NULL,
					`post_content` LONGTEXT,
					`post_title` TEXT,
					`post_excerpt` TEXT,
					`post_status` VARCHAR(20) NOT NULL DEFAULT 'publish',
					`comment_status` VARCHAR(20) NOT NULL DEFAULT 'open',
					`ping_status` VARCHAR(20) NOT NULL DEFAULT 'open',
					`post_password` VARCHAR(20) DEFAULT NULL,
					`post_name` VARCHAR(200) DEFAULT NULL,
					`to_ping` TEXT,
					`pinged` TEXT,
					  `post_modified` DATETIME NULL DEFAULT NULL,
					  `post_modified_gmt` DATETIME NULL DEFAULT NULL,
					`post_content_filtered` LONGTEXT,
					`post_parent` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`guid` VARCHAR(255) DEFAULT NULL,
					`menu_order` INT(11) NOT NULL DEFAULT '0',
					`post_type` VARCHAR(20) NOT NULL DEFAULT 'post',
					`post_mime_type` VARCHAR(100) DEFAULT NULL,
					`comment_count` BIGINT(20) NOT NULL DEFAULT '0',
					PRIMARY KEY (`ID`),
					KEY `post_name` (`post_name`(191)),
					KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
					KEY `post_parent` (`post_parent`),
					KEY `post_author` (`post_author`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `terms` (
					`term_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`name` VARCHAR(200) NOT NULL DEFAULT '',
					`slug` VARCHAR(200) NOT NULL DEFAULT '',
					`term_group` BIGINT(10) NOT NULL DEFAULT '0',
					PRIMARY KEY (`term_id`),
					UNIQUE KEY `slug` (`slug`),
					KEY `name` (`name`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `termmeta` (
					`meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`term_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`meta_key` VARCHAR(255) DEFAULT NULL,
					`meta_value` LONGTEXT,
					PRIMARY KEY (`meta_id`),
					KEY `term_id` (`term_id`),
					KEY `meta_key` (`meta_key`(191))
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `term_relationships` (
					`object_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`term_taxonomy_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`term_order` INT(11) NOT NULL DEFAULT '0',
					PRIMARY KEY (`object_id`,`term_taxonomy_id`),
					KEY `term_taxonomy_id` (`term_taxonomy_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `term_taxonomy` (
					`term_taxonomy_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`term_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`taxonomy` VARCHAR(32) NOT NULL DEFAULT '',
					`description` LONGTEXT,
					`parent` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`count` BIGINT(20) NOT NULL DEFAULT '0',
					PRIMARY KEY (`term_taxonomy_id`),
					UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
					KEY `taxonomy` (`taxonomy`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `usermeta` (
					`umeta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`user_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
					`meta_key` VARCHAR(255) DEFAULT NULL,
					`meta_value` LONGTEXT,
					PRIMARY KEY (`umeta_id`),
					KEY `user_id` (`user_id`),
					KEY `meta_key` (`meta_key`(191))
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

				CREATE TABLE IF NOT EXISTS `users` (
					`ID` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
					`user_login` VARCHAR(60) NOT NULL DEFAULT '',
					`user_pass` VARCHAR(64) NOT NULL DEFAULT '',
					`user_nicename` VARCHAR(50) NOT NULL DEFAULT '',
					`user_email` VARCHAR(100) NOT NULL DEFAULT '',
					`user_url` VARCHAR(100) NOT NULL DEFAULT '',
					  `user_registered` DATETIME NULL DEFAULT NULL,
					`user_activation_key` VARCHAR(60) NOT NULL DEFAULT '',
					`user_status` INT(11) NOT NULL DEFAULT '0',
					`display_name` VARCHAR(250) NOT NULL DEFAULT '',
					PRIMARY KEY (`ID`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
				SQL;

				$link->exec($sql);
		},

		'down' => function (PDO $link) {
				$drop = <<<'SQL'
				DROP TABLE IF EXISTS `term_relationships`;
				DROP TABLE IF EXISTS `term_taxonomy`;
				DROP TABLE IF EXISTS `termmeta`;
				DROP TABLE IF EXISTS `terms`;
				DROP TABLE IF EXISTS `postmeta`;
				DROP TABLE IF EXISTS `posts`;
				DROP TABLE IF EXISTS `commentmeta`;
				DROP TABLE IF EXISTS `comments`;
				DROP TABLE IF EXISTS `links`;
				DROP TABLE IF EXISTS `options`;
				DROP TABLE IF EXISTS `usermeta`;
				DROP TABLE IF EXISTS `users`;
				SQL;

				$link->exec($drop);
		}
];

