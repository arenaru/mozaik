-- Mozaic database schema (MySQL 8, InnoDB, utf8mb4)
-- Loaded automatically by the db container on first start.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE IF NOT EXISTS users (
    id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username           VARCHAR(30)   NOT NULL,
    email              VARCHAR(255)  NOT NULL,
    password_hash      VARCHAR(255)  NOT NULL,
    display_name       VARCHAR(50)   NULL,
    bio                VARCHAR(300)  NULL,
    avatar_path        VARCHAR(255)  NULL,
    role               ENUM('user','admin') NOT NULL DEFAULT 'user',
    is_active          TINYINT(1)    NOT NULL DEFAULT 1,
    failed_login_count INT UNSIGNED  NOT NULL DEFAULT 0,
    locked_until       DATETIME      NULL,
    created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pins (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED  NOT NULL,
    title       VARCHAR(100)  NOT NULL,
    description TEXT          NULL,
    image_path  VARCHAR(255)  NOT NULL,
    source_url  VARCHAR(2048) NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pins_created_at (created_at),
    INDEX idx_pins_user_id (user_id),
    CONSTRAINT fk_pins_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS boards (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    name        VARCHAR(50)  NOT NULL,
    description VARCHAR(300) NULL,
    visibility  ENUM('public','private') NOT NULL DEFAULT 'public',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_boards_user_id (user_id),
    CONSTRAINT fk_boards_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS board_pins (
    board_id INT UNSIGNED NOT NULL,
    pin_id   INT UNSIGNED NOT NULL,
    saved_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (board_id, pin_id),
    INDEX idx_board_pins_pin_id (pin_id),
    CONSTRAINT fk_board_pins_board FOREIGN KEY (board_id) REFERENCES boards (id) ON DELETE CASCADE,
    CONSTRAINT fk_board_pins_pin   FOREIGN KEY (pin_id)   REFERENCES pins (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_id is intentionally not a foreign key: audit entries must survive account deletion.
CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NULL,
    action     VARCHAR(50)  NOT NULL,
    detail     VARCHAR(500) NULL,
    ip_address VARCHAR(45)  NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
