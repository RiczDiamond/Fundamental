SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

SET @has_col_blogs_builder_json = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'blogs'
    AND column_name = 'builder_json'
);
SET @sql_drop_blogs_builder_json = IF(
  @has_col_blogs_builder_json = 1,
  'ALTER TABLE `blogs` DROP COLUMN `builder_json`',
  'SELECT 1'
);
PREPARE stmt_drop_blogs_builder_json FROM @sql_drop_blogs_builder_json;
EXECUTE stmt_drop_blogs_builder_json;
DEALLOCATE PREPARE stmt_drop_blogs_builder_json;

SET @has_col_blog_revisions_builder_json = (
  SELECT COUNT(*)
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'blog_revisions'
    AND column_name = 'builder_json'
);
SET @sql_drop_blog_revisions_builder_json = IF(
  @has_col_blog_revisions_builder_json = 1,
  'ALTER TABLE `blog_revisions` DROP COLUMN `builder_json`',
  'SELECT 1'
);
PREPARE stmt_drop_blog_revisions_builder_json FROM @sql_drop_blog_revisions_builder_json;
EXECUTE stmt_drop_blog_revisions_builder_json;
DEALLOCATE PREPARE stmt_drop_blog_revisions_builder_json;

COMMIT;
