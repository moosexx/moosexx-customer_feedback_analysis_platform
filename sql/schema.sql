-- Create Users table
CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100),
    middle_name VARCHAR(100),
    last_name VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Migration: Add name columns if they don't exist (for existing databases)
-- Using stored procedure for compatibility with older MySQL versions
DELIMITER $$

CREATE PROCEDURE add_name_columns_if_missing()
BEGIN
    DECLARE full_name_exists INT DEFAULT 0;
    
    -- Check if full_name column exists
    SELECT COUNT(*) INTO full_name_exists
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME = 'full_name';
    
    -- If full_name doesn't exist, add it first (for very old databases)
    IF full_name_exists = 0 THEN
        ALTER TABLE users ADD COLUMN full_name VARCHAR(255);
    END IF;
    
    -- Add first_name column if it doesn't exist
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'users' 
                   AND COLUMN_NAME = 'first_name') THEN
        ALTER TABLE users ADD COLUMN first_name VARCHAR(100) AFTER email;
    END IF;
    
    -- Add middle_name column if it doesn't exist
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'users' 
                   AND COLUMN_NAME = 'middle_name') THEN
        ALTER TABLE users ADD COLUMN middle_name VARCHAR(100) AFTER first_name;
    END IF;
    
    -- Add last_name column if it doesn't exist
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'users' 
                   AND COLUMN_NAME = 'last_name') THEN
        ALTER TABLE users ADD COLUMN last_name VARCHAR(100) AFTER middle_name;
    END IF;
END$$

DELIMITER ;

-- Call the procedure to add columns
CALL add_name_columns_if_missing();

-- Drop the procedure after use
DROP PROCEDURE IF EXISTS add_name_columns_if_missing;

-- Migrate existing full_name data to the new columns (if any exists)
-- This preserves all existing user data during the transition
-- Only runs if full_name column exists and has data
UPDATE users 
SET 
    first_name = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(COALESCE(full_name, ''), ' ', 1), ' ', -1)),
    middle_name = CASE 
        WHEN LENGTH(COALESCE(full_name, '')) - LENGTH(REPLACE(COALESCE(full_name, ''), ' ', '')) >= 2 
        THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(COALESCE(full_name, ''), ' ', 2), ' ', -1))
        ELSE '' 
    END,
    last_name = CASE 
        WHEN LENGTH(COALESCE(full_name, '')) - LENGTH(REPLACE(COALESCE(full_name, ''), ' ', '')) >= 1 
        THEN TRIM(SUBSTRING_INDEX(COALESCE(full_name, ''), ' ', -1))
        ELSE '' 
    END
WHERE (COALESCE(full_name, '') IS NOT NULL AND TRIM(full_name) != '')
  AND (COALESCE(first_name, '') = '')
  AND (COALESCE(middle_name, '') = '')
  AND (COALESCE(last_name, '') = '');

-- Remove full_name column after migration is complete
-- This ensures no redundancy and uses only separate name columns
ALTER TABLE users DROP COLUMN IF EXISTS full_name;

-- Create Businesses table
CREATE TABLE IF NOT EXISTS businesses (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    industry VARCHAR(100),
    description TEXT,
    business_address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    logo_path VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create Feedback table
CREATE TABLE IF NOT EXISTS feedback (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    customer_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(20),
    rating INT CHECK (rating >= 1 AND rating <= 5),
    category VARCHAR(100),
    comment TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Create Custom Questions table
CREATE TABLE IF NOT EXISTS custom_questions (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    question_text TEXT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create Feedback Answers table (for custom question responses)
CREATE TABLE IF NOT EXISTS feedback_answers (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    feedback_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_text TEXT NOT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES custom_questions(id) ON DELETE CASCADE
);

-- Create Dismissed Recommendations table
CREATE TABLE IF NOT EXISTS dismissed_recommendations (
    id INT NOT NULL PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    recommendation_hash VARCHAR(64) NOT NULL, -- Hash of recommendation content for uniqueness
    recommendation_title VARCHAR(255) NOT NULL,
    recommendation_type VARCHAR(50) NOT NULL,
    recommendation_priority VARCHAR(20) NOT NULL,
    dismissed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_recommendation (user_id, recommendation_hash)
);
