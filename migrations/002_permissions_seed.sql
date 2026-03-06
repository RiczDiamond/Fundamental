SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

INSERT IGNORE INTO `perm_group` (`group_id`, `group_name`, `description`) VALUES
(1, 'admin', 'Volledige toegang tot alle modules en beheerfuncties'),
(2, 'editor', 'Mag content beheren in blog en pages'),
(3, 'user', 'Basis toegang tot dashboard en lezen van content');

INSERT IGNORE INTO `perm_permission` (`permission_id`, `permission_name`, `description`) VALUES
(1, 'dashboard', 'Toegang tot dashboard'),
(2, 'blog.read', 'Blog artikelen bekijken'),
(3, 'blog.write', 'Blog artikelen aanmaken of bewerken'),
(4, 'pages.read', 'Pagina\'s bekijken'),
(5, 'pages.write', 'Pagina\'s aanmaken of bewerken'),
(6, 'users.manage', 'Gebruikers beheren');

INSERT IGNORE INTO `perm_group_permission` (`group_id`, `permission_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6),
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5),
(3, 1), (3, 2), (3, 4);

INSERT IGNORE INTO `perm_account` (`account_id`, `username`, `email`, `password_hash`, `created_at`)
SELECT
    u.id,
    COALESCE(NULLIF(u.username, ''), CONCAT('user', u.id)),
    u.email,
    u.password,
    COALESCE(u.created_at, NOW())
FROM `users` u
WHERE u.status = 'active';

INSERT IGNORE INTO `perm_account_group` (`account_id`, `group_id`)
SELECT pa.account_id,
       CASE
           WHEN u.role = 'admin' THEN 1
           WHEN u.role = 'editor' THEN 2
           ELSE 3
       END AS group_id
FROM `perm_account` pa
JOIN `users` u ON u.id = pa.account_id;

INSERT IGNORE INTO `perm_account_permission` (`account_id`, `permission_id`)
SELECT pa.account_id, p.permission_id
FROM `perm_account` pa
JOIN `users` u ON u.id = pa.account_id
JOIN `perm_permission` p ON p.permission_name = 'dashboard'
WHERE u.status = 'active'

UNION ALL

SELECT pa.account_id, p.permission_id
FROM `perm_account` pa
JOIN `users` u ON u.id = pa.account_id
JOIN `perm_permission` p ON p.permission_name = 'users.manage'
WHERE u.status = 'active' AND u.role = 'admin'

UNION ALL

SELECT pa.account_id, p.permission_id
FROM `perm_account` pa
JOIN `users` u ON u.id = pa.account_id
JOIN `perm_permission` p ON p.permission_name IN ('blog.write', 'pages.write')
WHERE u.status = 'active' AND u.role IN ('admin', 'editor');

COMMIT;
