-- Add avatar support to users table
ALTER TABLE users ADD COLUMN avatar VARCHAR(255) DEFAULT NULL AFTER is_admin;
