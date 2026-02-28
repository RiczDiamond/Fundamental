-- Sample seed data for wp_* tables

-- Users
INSERT INTO users (ID, user_login, user_pass, user_nicename, user_email, user_url, user_registered, user_activation_key, user_status, display_name)
VALUES (1, 'admin', MD5('password'), 'admin', 'admin@example.test', '', NOW(), '', 0, 'Site Admin');

-- Usermeta
INSERT INTO usermeta (umeta_id, user_id, meta_key, meta_value) VALUES
(NULL, 1, 'first_name', 'Site'),
(NULL, 1, 'last_name', 'Admin');

-- Options
INSERT INTO options (option_name, option_value, autoload) VALUES
('siteurl', 'http://example.test', 'yes'),
('blogname', 'Example Site', 'yes'),
('blogdescription', 'Just another site', 'yes'),
('admin_email', 'admin@example.test', 'yes');

-- Terms (categories)
INSERT INTO terms (term_id, name, slug, term_group) VALUES (1, 'Uncategorized', 'uncategorized', 0);

-- Term taxonomy
INSERT INTO term_taxonomy (term_taxonomy_id, term_id, taxonomy, description, parent, count) VALUES (1, 1, 'category', '', 0, 1);

-- A sample post
INSERT INTO posts (ID, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_password, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count)
VALUES (1, 1, NOW(), NOW(), 'This is the first seeded post.', 'Welcome to the seeded site', '', 'publish', 'open', 'open', '', 'welcome-to-seed', '', '', NOW(), NOW(), '', 0, 'http://example.test/?p=1', 0, 'post', '', 0);

-- Post meta
INSERT INTO postmeta (meta_id, post_id, meta_key, meta_value) VALUES (NULL, 1, '_edit_last', '1');

-- Link post to term
INSERT INTO term_relationships (object_id, term_taxonomy_id, term_order) VALUES (1, 1, 0);

-- Comments
INSERT INTO comments (comment_ID, comment_post_ID, comment_author, comment_author_email, comment_author_url, comment_author_IP, comment_date, comment_date_gmt, comment_content, comment_karma, comment_approved, comment_agent, comment_type, comment_parent, user_id)
VALUES (1, 1, 'Guest', 'guest@example.test', '', '127.0.0.1', NOW(), NOW(), 'First seeded comment', 0, '1', '', '', 0, 0);

-- Comment meta (example)
INSERT INTO commentmeta (meta_id, comment_id, meta_key, meta_value) VALUES (NULL, 1, 'approved_by', 'system');

-- Optional sample link
INSERT INTO links (link_id, link_url, link_name, link_image, link_target, link_description, link_visible, link_owner, link_rating, link_updated, link_rel, link_notes, link_rss)
VALUES (NULL, 'https://example.test', 'Example', '', '_blank', 'Example link', 'Y', 1, 0, NOW(), '', '', '');
