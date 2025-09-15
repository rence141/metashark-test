-- Safe migration script that checks for existing columns
-- Add role column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'users' 
     AND table_schema = DATABASE() 
     AND column_name = 'role') = 0,
    'ALTER TABLE users ADD COLUMN role ENUM(''buyer'', ''seller'', ''admin'') DEFAULT ''buyer''',
    'SELECT ''role column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add seller_name column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'users' 
     AND table_schema = DATABASE() 
     AND column_name = 'seller_name') = 0,
    'ALTER TABLE users ADD COLUMN seller_name VARCHAR(255) NULL',
    'SELECT ''seller_name column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add seller_description column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'users' 
     AND table_schema = DATABASE() 
     AND column_name = 'seller_description') = 0,
    'ALTER TABLE users ADD COLUMN seller_description TEXT NULL',
    'SELECT ''seller_description column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add seller_rating column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'users' 
     AND table_schema = DATABASE() 
     AND column_name = 'seller_rating') = 0,
    'ALTER TABLE users ADD COLUMN seller_rating DECIMAL(3,2) DEFAULT 0.00',
    'SELECT ''seller_rating column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add business_type column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'users' 
     AND table_schema = DATABASE() 
     AND column_name = 'business_type') = 0,
    'ALTER TABLE users ADD COLUMN business_type VARCHAR(50) NULL',
    'SELECT ''business_type column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add is_active_seller column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'users' 
     AND table_schema = DATABASE() 
     AND column_name = 'is_active_seller') = 0,
    'ALTER TABLE users ADD COLUMN is_active_seller BOOLEAN DEFAULT FALSE',
    'SELECT ''is_active_seller column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing users to have buyer role (only if role column exists and is NULL)
UPDATE users SET role = 'buyer' WHERE role IS NULL;

-- Add seller_id column to products table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'products' 
     AND table_schema = DATABASE() 
     AND column_name = 'seller_id') = 0,
    'ALTER TABLE products ADD COLUMN seller_id INT NOT NULL DEFAULT 1',
    'SELECT ''seller_id column already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key constraint for seller_id (only if it doesn't exist)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE table_name = 'products' 
     AND table_schema = DATABASE() 
     AND constraint_name = 'fk_products_seller') = 0,
    'ALTER TABLE products ADD CONSTRAINT fk_products_seller FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT ''fk_products_seller constraint already exists'' as message'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing products to have seller_id = 1 (only if seller_id is 1)
UPDATE products SET seller_id = 1 WHERE seller_id = 1;
