-- Migration: add permission tables (MySQL-compatible)

CREATE TABLE IF NOT EXISTS perm_account (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS perm_group (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS perm_account_group (
    account_id INT NOT NULL,
    group_id INT NOT NULL,
    PRIMARY KEY (account_id, group_id),
    CONSTRAINT fk_pag_account FOREIGN KEY (account_id) REFERENCES perm_account(account_id) ON DELETE CASCADE,
    CONSTRAINT fk_pag_group FOREIGN KEY (group_id) REFERENCES perm_group(group_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS perm_permission (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS perm_group_permission (
    group_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (group_id, permission_id),
    CONSTRAINT fk_gperm_group FOREIGN KEY (group_id) REFERENCES perm_group(group_id) ON DELETE CASCADE,
    CONSTRAINT fk_gperm_permission FOREIGN KEY (permission_id) REFERENCES perm_permission(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS perm_account_permission (
    account_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (account_id, permission_id),
    CONSTRAINT fk_aperm_account FOREIGN KEY (account_id) REFERENCES perm_account(account_id) ON DELETE CASCADE,
    CONSTRAINT fk_aperm_permission FOREIGN KEY (permission_id) REFERENCES perm_permission(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
