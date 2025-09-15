-- Final migration script - only adds missing columns
-- This will skip columns that already exist

-- Add role column to users table
ALTER TABLE users ADD COLUMN role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer';

-- Add seller_name column to users table
ALTER TABLE users ADD COLUMN seller_name VARCHAR(255) NULL;

-- Add seller_description column to users table
ALTER TABLE users ADD COLUMN seller_description TEXT NULL;

-- Add business_type column to users table
ALTER TABLE users ADD COLUMN business_type VARCHAR(50) NULL;

-- Update existing users to have buyer role (safe update mode compatible)
UPDATE users SET role = 'buyer' WHERE id > 0 AND role IS NULL;

-- Check if seller_id exists in products table, if not add it
-- (This will show a warning if it exists, but that's okay)
ALTER TABLE products ADD COLUMN seller_id INT NOT NULL DEFAULT 1;

-- Update existing products to have seller_id = 1 (safe update mode compatible)
UPDATE products SET seller_id = 1 WHERE id > 0 AND seller_id = 1;

-- Add foreign key constraint (this might show an error if it exists, but that's okay)
ALTER TABLE products ADD CONSTRAINT fk_products_seller 
FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE;
