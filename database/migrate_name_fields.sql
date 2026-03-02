-- Migration: Add first_name and last_name columns, migrate data from full_name
-- Run this to split full_name into first_name and last_name

USE votingnova;

-- Add new columns
ALTER TABLE system_users 
ADD COLUMN first_name VARCHAR(50) NULL AFTER phone,
ADD COLUMN last_name VARCHAR(50) NULL AFTER first_name;

-- Migrate existing full_name data
-- Split full_name into first_name and last_name
UPDATE system_users 
SET 
    first_name = TRIM(SUBSTRING_INDEX(full_name, ' ', 1)),
    last_name = CASE 
        WHEN LOCATE(' ', full_name) > 0 
        THEN TRIM(SUBSTRING(full_name, LOCATE(' ', full_name) + 1))
        ELSE NULL
    END
WHERE full_name IS NOT NULL AND full_name != '';

-- For admin_users table (if it has full_name)
-- Check if column exists first, then migrate
-- ALTER TABLE admin_users 
-- ADD COLUMN first_name VARCHAR(50) NULL AFTER username,
-- ADD COLUMN last_name VARCHAR(50) NULL AFTER first_name;
-- 
-- UPDATE admin_users 
-- SET 
--     first_name = TRIM(SUBSTRING_INDEX(full_name, ' ', 1)),
--     last_name = CASE 
--         WHEN LOCATE(' ', full_name) > 0 
--         THEN TRIM(SUBSTRING(full_name, LOCATE(' ', full_name) + 1))
--         ELSE NULL
--     END
-- WHERE full_name IS NOT NULL AND full_name != '';

-- Note: Keep full_name column for now for backward compatibility
-- You can drop it later after verifying everything works:
-- ALTER TABLE system_users DROP COLUMN full_name;
