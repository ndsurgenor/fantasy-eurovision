-- Migration 002: Replace flag emoji text with uploaded image filenames
-- Run against fantasyeurovision_db before deploying updated application code.

ALTER TABLE country_catalogue
    CHANGE flag_emoji flag_image VARCHAR(255) DEFAULT NULL;

ALTER TABLE countries
    CHANGE flag_emoji flag_image VARCHAR(255) DEFAULT NULL;
