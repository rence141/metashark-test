-- Add seller role to users table
ALTER TABLE users ADD COLUMN is_seller BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN seller_name VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN seller_description TEXT NULL;
ALTER TABLE users ADD COLUMN seller_rating DECIMAL(3,2) DEFAULT 0.00;
ALTER TABLE users ADD COLUMN total_sales INT DEFAULT 0;

-- Add seller_id to products table to track who owns each product
ALTER TABLE products ADD COLUMN seller_id INT NOT NULL DEFAULT 1;
ALTER TABLE products ADD COLUMN is_approved BOOLEAN DEFAULT TRUE;

-- Add foreign key constraint for seller_id
ALTER TABLE products ADD FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE;

-- Update existing products to have seller_id = 1 (admin)
UPDATE products SET seller_id = 1 WHERE seller_id = 1;

-- Create seller_products table for better organization (optional)
CREATE TABLE IF NOT EXISTS seller_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_seller_product (seller_id, product_id)
);

-- Create orders table for tracking purchases
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Create seller_reviews table
CREATE TABLE IF NOT EXISTS seller_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    buyer_id INT NOT NULL,
    order_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);
