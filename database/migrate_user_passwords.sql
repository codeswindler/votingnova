-- Migration script to add password fields to system_users table
USE votingnova;

-- Add password fields to system_users table
ALTER TABLE system_users 
ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER email,
ADD COLUMN IF NOT EXISTS temp_password VARCHAR(50) NULL AFTER password_hash,
ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) DEFAULT 0 AFTER temp_password,
ADD COLUMN IF NOT EXISTS last_password_change TIMESTAMP NULL AFTER must_change_password,
ADD INDEX idx_must_change_password (must_change_password);
