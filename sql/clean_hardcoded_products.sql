-- Remove all hardcoded products from the database
-- This script will clean the database of any hardcoded products

-- First, clear the cart table (since it references products)
DELETE FROM cart;

-- Remove all products
DELETE FROM products;

-- Reset auto increment to start from 1
ALTER TABLE products AUTO_INCREMENT = 1;

-- Verify the cleanup
SELECT 'Products table cleaned' as status, COUNT(*) as remaining_products FROM products;
SELECT 'Cart table cleaned' as status, COUNT(*) as remaining_items FROM cart;
