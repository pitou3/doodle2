-- Schéma initial doodle2 (MariaDB 10.3+ / MySQL 5.7+)

CREATE TABLE IF NOT EXISTS polls (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id VARCHAR(32) NOT NULL,
    admin_token_hash CHAR(64) NOT NULL,
    type ENUM('date', 'classic') NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    author_name VARCHAR(100) NOT NULL,
    author_email VARCHAR(190) NULL,
    edit_policy ENUM('all', 'own', 'none') NOT NULL DEFAULT 'own',
    allow_maybe TINYINT(1) NOT NULL DEFAULT 1,
    deadline DATETIME NULL,
    closed_at DATETIME NULL,
    ends_at DATETIME NULL,
    lang CHAR(2) NOT NULL DEFAULT 'fr',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_public_id (public_id),
    KEY idx_ends_at (ends_at),
    KEY idx_last_activity (last_activity_at),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS poll_options (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    poll_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    option_date DATE NULL,
    label VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_poll (poll_id, option_date, position),
    CONSTRAINT fk_options_poll FOREIGN KEY (poll_id) REFERENCES polls (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS votes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    poll_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    edit_token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_poll (poll_id),
    CONSTRAINT fk_votes_poll FOREIGN KEY (poll_id) REFERENCES polls (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vote_choices (
    vote_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NOT NULL,
    value TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (vote_id, option_id),
    KEY idx_option (option_id),
    CONSTRAINT fk_choices_vote FOREIGN KEY (vote_id) REFERENCES votes (id) ON DELETE CASCADE,
    CONSTRAINT fk_choices_option FOREIGN KEY (option_id) REFERENCES poll_options (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(60) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    username VARCHAR(60) NOT NULL,
    attempted_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
