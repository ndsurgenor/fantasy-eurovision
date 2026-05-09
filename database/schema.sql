-- Fantasy Eurovision — MySQL Schema
-- Run this once to create all tables on a fresh install:
--   mysql -u root -p fantasyeurovision_db < database/schema.sql
-- For existing installs, run database/migrations/001_multi_contest.sql instead.

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name          VARCHAR(100)     NOT NULL,
    email         VARCHAR(255)     NOT NULL,
    password_hash VARCHAR(255)     NOT NULL,
    is_admin      TINYINT(1)       NOT NULL DEFAULT 0,
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS country_catalogue (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    flag_image VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_catalogue (
    id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name   VARCHAR(100) NOT NULL,
    colour VARCHAR(20)  NOT NULL DEFAULT '#6366f1',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contests (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    year         YEAR         NOT NULL,
    name         VARCHAR(255) NOT NULL,
    budget_limit DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    status       ENUM('setup','open','closed','finished') NOT NULL DEFAULT 'setup',
    is_active    TINYINT(1)   NOT NULL DEFAULT 0,
    launch_date  DATE         DEFAULT NULL,
    launch_time  TIME         DEFAULT NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contest_groups (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contest_id  INT UNSIGNED NOT NULL,
    group_id    INT UNSIGNED DEFAULT NULL,
    name        VARCHAR(100) NOT NULL,
    colour      VARCHAR(20)  NOT NULL DEFAULT '#6366f1',
    is_wildcard TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_cg_contest FOREIGN KEY (contest_id) REFERENCES contests      (id) ON DELETE CASCADE,
    CONSTRAINT fk_cg_group   FOREIGN KEY (group_id)   REFERENCES group_catalogue (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS countries (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contest_id      INT UNSIGNED NOT NULL,
    catalogue_id    INT UNSIGNED DEFAULT NULL,
    group_id        INT UNSIGNED NOT NULL,
    name            VARCHAR(100) NOT NULL,
    flag_image      VARCHAR(255) DEFAULT NULL,
    price           DECIMAL(4,2) NOT NULL,
    final_score_raw INT          DEFAULT NULL,
    running_order   INT          DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_countries_contest   FOREIGN KEY (contest_id)   REFERENCES contests         (id) ON DELETE CASCADE,
    CONSTRAINT fk_countries_catalogue FOREIGN KEY (catalogue_id) REFERENCES country_catalogue (id) ON DELETE SET NULL,
    CONSTRAINT fk_countries_group     FOREIGN KEY (group_id)     REFERENCES contest_groups    (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS entries (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NOT NULL,
    contest_id   INT UNSIGNED NOT NULL,
    submitted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_cost   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    total_score  INT          NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entries_user_contest (user_id, contest_id),
    CONSTRAINT fk_entries_user    FOREIGN KEY (user_id)    REFERENCES users    (id) ON DELETE CASCADE,
    CONSTRAINT fk_entries_contest FOREIGN KEY (contest_id) REFERENCES contests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS entry_countries (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id   INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ec_entry_country (entry_id, country_id),
    CONSTRAINT fk_ec_entry   FOREIGN KEY (entry_id)   REFERENCES entries   (id) ON DELETE CASCADE,
    CONSTRAINT fk_ec_country FOREIGN KEY (country_id) REFERENCES countries (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
