-- Migration 001: Multi-contest support
-- Run against fantasyeurovision_db before deploying updated application code.

-- 1. Master country catalogue
CREATE TABLE IF NOT EXISTS country_catalogue (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    flag_emoji VARCHAR(10)  DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Master group catalogue
CREATE TABLE IF NOT EXISTS group_catalogue (
    id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name   VARCHAR(100) NOT NULL,
    colour VARCHAR(20)  NOT NULL DEFAULT '#6366f1',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Populate country_catalogue from existing countries data
INSERT INTO country_catalogue (name, flag_emoji)
SELECT DISTINCT name, flag_emoji FROM countries
WHERE name IS NOT NULL AND name != '';

-- 4. Add catalogue_id to countries and link to catalogue
ALTER TABLE countries
    ADD COLUMN catalogue_id INT UNSIGNED DEFAULT NULL AFTER contest_id,
    ADD CONSTRAINT fk_countries_catalogue
        FOREIGN KEY (catalogue_id) REFERENCES country_catalogue (id) ON DELETE SET NULL;

UPDATE countries c
JOIN country_catalogue cc
    ON cc.name = c.name
   AND (cc.flag_emoji <=> c.flag_emoji)
SET c.catalogue_id = cc.id;

-- 5. Add group_id and colour to contest_groups
ALTER TABLE contest_groups
    ADD COLUMN group_id INT UNSIGNED DEFAULT NULL AFTER contest_id,
    ADD COLUMN colour   VARCHAR(20)  NOT NULL DEFAULT '#6366f1' AFTER is_wildcard,
    ADD CONSTRAINT fk_cg_group
        FOREIGN KEY (group_id) REFERENCES group_catalogue (id) ON DELETE SET NULL;

-- 6. Rename status values before changing the ENUM
UPDATE contests SET status = 'closed'   WHERE status = 'locked';
UPDATE contests SET status = 'finished' WHERE status = 'scored';

-- 7. Alter contests: drop year uniqueness, change status enum, add is_active + launch_date
ALTER TABLE contests
    DROP INDEX uq_contests_year,
    MODIFY COLUMN status ENUM('setup','open','closed','finished') NOT NULL DEFAULT 'setup',
    ADD COLUMN is_active   TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN launch_date DATE       DEFAULT NULL            AFTER is_active;

-- 8. Mark the most recently created contest as active (if any exist)
UPDATE contests SET is_active = 1 ORDER BY id DESC LIMIT 1;
