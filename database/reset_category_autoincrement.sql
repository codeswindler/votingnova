-- Reset AUTO_INCREMENT for categories table
-- WARNING: Only use this if you understand the implications
-- This will reset the AUTO_INCREMENT counter to the next available ID
-- Use this ONLY if you want sequential IDs without gaps

USE votingnova;

-- Get the maximum ID currently in use
SET @max_id = (SELECT COALESCE(MAX(id), 0) FROM categories);

-- Reset AUTO_INCREMENT to be one more than the max ID
-- This ensures the next inserted category will have ID = max_id + 1
SET @sql = CONCAT('ALTER TABLE categories AUTO_INCREMENT = ', @max_id + 1);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the new AUTO_INCREMENT value
SELECT AUTO_INCREMENT 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'votingnova' 
AND TABLE_NAME = 'categories';
