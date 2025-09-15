-- Simple script to add only the missing columns
-- This will skip columns that already exist

-- Add role column (skip if exists)
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer';

-- Add seller_name column (skip if exists)
ALTER TABLE users ADD COLUMN IF NOT EXISTS seller_name VARCHAR(255) NULL;

-- Add seller_description column (skip if exists)
ALTER TABLE users ADD COLUMN IF NOT EXISTS seller_description TEXT NULL;

-- Add seller_rating column (skip if exists)
ALTER TABLE users ADD COLUMN IF NOT EXISTS seller_rating DECIMAL(3,2) DEFAULT 0.00;

-- Add business_type column (skip if exists)
ALTER TABLE users ADD COLUMN IF NOT EXISTS business_type VARCHAR(50) NULL;

-- Add is_active_seller column (skip if exists)
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active_seller BOOLEAN DEFAULT FALSE;

-- Add seller_id column to products table (skip if exists)
ALTER TABLE products ADD COLUMN IF NOT EXISTS seller_id INT NOT NULL DEFAULT 1;

-- Update existing users to have buyer role
UPDATE users SET role = 'buyer' WHERE role IS NULL;

-- Update existing products to have seller_id = 1
UPDATE products SET seller_id = 1 WHERE seller_id = 1;

-- Add foreign key constraint (only if it doesn't exist)
-- Note: This might fail if constraint already exists, but that's okay
ALTER TABLE products ADD CONSTRAINT fk_products_seller 
FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE;
