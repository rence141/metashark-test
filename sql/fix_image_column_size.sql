-- Fix image column size to handle longer URLs
-- The current VARCHAR(255) is too small for many image URLs

-- Increase image column size to handle longer URLs
ALTER TABLE products MODIFY COLUMN image VARCHAR(1000);

-- Also increase SKU column size for longer product codes
ALTER TABLE products MODIFY COLUMN sku VARCHAR(255);

-- Verify the changes
DESCRIBE products;
