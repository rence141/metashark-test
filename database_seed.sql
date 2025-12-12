-- USE metashark; -- Uncomment and replace 'metashark' if you need to select the database explicitly

-- Drop tables in reverse order of foreign key dependencies
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users; -- Drop users table
DROP TABLE IF EXISTS admins; -- Drop admins table

-- Create 'users' table with the provided schema
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    phone VARCHAR(20),
    country VARCHAR(100),
    profile_image VARCHAR(255) DEFAULT 'default-avatar.svg',
    otp_expiry DATETIME,
    otp_code VARCHAR(10),
    total_sales DECIMAL(10, 2) DEFAULT 0.00,
    seller_rating DECIMAL(3, 2) DEFAULT 0.00,
    is_active_seller BOOLEAN DEFAULT 0,
    role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer',
    is_suspended BOOLEAN DEFAULT 0,
    is_verified BOOLEAN DEFAULT 0,
    verification_code VARCHAR(255),
    verification_expires DATETIME,
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    seller_name VARCHAR(255),
    seller_description TEXT,
    business_type VARCHAR(100),
    phone_verified BOOLEAN DEFAULT 0,
    country_code VARCHAR(10),
    country_name VARCHAR(100),
    language VARCHAR(10) DEFAULT 'en',
    theme VARCHAR(20) DEFAULT 'dark',
    avatar VARCHAR(255),
    auth_provider VARCHAR(50) DEFAULT 'local',
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT 0,
    suspension_reason TEXT,
    suspended_at DATETIME
);

-- Create 'admins' table (remains separate for dashboard login)
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create 'categories' table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE
);

-- Create 'products' table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL, -- This should now refer to a user with role='seller'
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_deleted BOOLEAN DEFAULT 0,
    FOREIGN KEY (seller_id) REFERENCES users(id) -- Link to users table
);

-- Create 'product_categories' junction table
CREATE TABLE product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (product_id, category_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- Create 'orders' table
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'processing', 'shipped', 'completed', 'cancelled') NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    billing_country VARCHAR(100),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create 'order_items' table
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    price_at_purchase DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);


--
-- Sample Data Insertions
-- Note: 'created_at' dates are adjusted relative to NOW() for chart visibility.
-- Passwords are all '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9' (hashed 'password')
--

-- Sample data for 'admins' table (for dashboard login)
INSERT INTO admins (name, email, password) VALUES
('Super Admin', 'admin@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9');


-- Sample data for 'users' table (based on provided data, adjusted dates)
INSERT INTO users (fullname, email, password, created_at, phone, country, profile_image, is_active_seller, role, is_suspended, is_verified, country_code, country_name, theme) VALUES
('Rence Prepotente', 'lorenzezz0987@gmail.com', '$2y$10$xwHbu6RLfJC4uEymrcCWjeqBGOiTfxOg4LgGObklHyCnMQfXmepey', NOW() - INTERVAL 100 DAY, '09672479726', NULL, '1757851883_Screenshot 2025-09-05 094517.png', 0, 'buyer', 0, 0, NULL, NULL, 'dark'),
('Jane Smith', 'jane.smith@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 95 DAY, '09123456789', 'USA', 'default-avatar.svg', 0, 'buyer', 0, 1, 'US', 'United States', 'dark'),
('Peter Jones', 'peter.jones@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 80 DAY, '09223344556', 'Canada', 'default-avatar.svg', 0, 'buyer', 1, 0, 'CA', 'Canada', 'light'), -- Suspended
('Alice Brown', 'alice.brown@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 70 DAY, '09334455667', 'UK', 'default-avatar.svg', 0, 'buyer', 0, 1, 'GB', 'United Kingdom', 'dark'),
('Bob White', 'bob.white@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 60 DAY, '09445566778', 'Australia', 'default-avatar.svg', 0, 'buyer', 0, 1, 'AU', 'Australia', 'light'), -- Not used in is_deleted, set as active for a bit.

('MetaAccess Seller', 'lorenzeprepotente@gmail.com', '$2y$10$QvajffdDYW1E6wRqzCqJFuOAYxnn3s2Eyj.UfoK3sYW4zMibrNEpW', NOW() - INTERVAL 90 DAY, '09672479726', NULL, '1757857228_Screenshot 2025-09-11 225103.png', 1, 'seller', 0, 1, NULL, NULL, 'dark'),
('Syntax Seller', 'saribajames@gmail.com', '$2y$10$niMtXzPKvs0VfvarWAVLZeol16Vw/Es5hJ3o5Sm48UQne0kiDvqfe', NOW() - INTERVAL 85 DAY, '09672479726', NULL, '7_1760426922_0001-1.png', 1, 'seller', 0, 1, NULL, NULL, 'dark'),
('Black Shark Seller', 'renceprepotente@gmail.com', '$2y$10$XBCEhd1ifHEZKvwQS3H6TeWHO/P0n1/NxcGSL6OYsnpMv4yAOJCVW', NOW() - INTERVAL 75 DAY, '09672479726', 'Philippines', '8_1757942288_Screenshot 2025-09-15 203145.png', 1, 'seller', 0, 1, 'PH', 'Philippines', 'dark'),
('Dolphin Shark Seller', 'jandecember25@gmail.com', '$2y$10$OxO6unJ3K6LSOlt6tHz9nekB9Q5fIJcLTtBzsZhzOyUNdwkoYkydO', NOW() - INTERVAL 65 DAY, '09701453369', NULL, '12_1759295578_Screenshot 2025-10-01 113619.png', 1, 'seller', 0, 1, NULL, NULL, 'dark'),
('Inactive Seller', 'inactiveseller@gmail.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 50 DAY, '09112233445', 'Germany', 'default-avatar.svg', 0, 'seller', 0, 1, 'DE', 'Germany', 'dark'), -- Inactive seller


-- Additional users for user growth chart (more recent activity)
('UserA', 'usera@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 8 DAY, NULL, NULL, 'default-avatar.svg', 0, 'buyer', 0, 0, NULL, NULL, 'dark'),
('UserB', 'userb@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 7 DAY, NULL, NULL, 'default-avatar.svg', 0, 'buyer', 0, 0, NULL, NULL, 'dark'),
('UserC', 'userc@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 6 DAY, NULL, NULL, 'default-avatar.svg', 0, 'buyer', 1, 0, NULL, NULL, 'dark'), -- Suspended
('UserD', 'userd@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 5 DAY, NULL, NULL, 'default-avatar.svg', 0, 'buyer', 0, 0, NULL, NULL, 'dark'),
('UserE', 'usere@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 4 DAY, NULL, NULL, 'default-avatar.svg', 0, 'buyer', 0, 0, NULL, NULL, 'dark'), -- Will be marked deleted by logic below
('UserF', 'userf@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 3 DAY, NULL, NULL, 'default-avatar.svg', 0, 'buyer', 0, 0, NULL, NULL, 'dark'),
('UserG', 'userg@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 2 DAY, NULL, NULL, 'default-avatar.svg', 0, 'buyer', 0, 0, NULL, NULL, 'dark'),
('UserH', 'userh@example.com', '$2y$10$p0b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9b.yN2h.q1G9', NOW() - INTERVAL 1 DAY, NULL, NULL, 'default-avatar.svg', 0, 'buyer', 0, 0, NULL, NULL, 'dark');

-- Mark User E as deleted
UPDATE users SET is_deleted = 1 WHERE email = 'usere@example.com';
-- Mark Rence Prepotente (lorenzezz0987@gmail.com) as deleted
UPDATE users SET is_deleted = 1 WHERE email = 'lorenzezz0987@gmail.com';


-- IDs of users who are sellers
SET @seller1_id = (SELECT id FROM users WHERE email = 'lorenzeprepotente@gmail.com'); -- MetaAccess Seller
SET @seller2_id = (SELECT id FROM users WHERE email = 'saribajames@gmail.com'); -- Syntax Seller
SET @seller3_id = (SELECT id FROM users WHERE email = 'renceprepotente@gmail.com'); -- Black Shark Seller
SET @seller4_id = (SELECT id FROM users WHERE email = 'jandecember25@gmail.com'); -- Dolphin Shark Seller
SET @inactive_seller_id = (SELECT id FROM users WHERE email = 'inactiveseller@gmail.com');


-- Sample data for 'categories' table
INSERT INTO categories (name) VALUES
('Electronics'),
('Apparel'),
('Books'),
('Accessories'),
('Home Goods'),
('Sustainable');

SET @cat_electronics = (SELECT id FROM categories WHERE name = 'Electronics');
SET @cat_apparel = (SELECT id FROM categories WHERE name = 'Apparel');
SET @cat_books = (SELECT id FROM categories WHERE name = 'Books');
SET @cat_accessories = (SELECT id FROM categories WHERE name = 'Accessories');
SET @cat_home_goods = (SELECT id FROM categories WHERE name = 'Home Goods');
SET @cat_sustainable = (SELECT id FROM categories WHERE name = 'Sustainable');


-- Sample data for 'products' table
INSERT INTO products (seller_id, name, description, price, stock_quantity, created_at, is_deleted) VALUES
(@seller1_id, 'Wireless Headphones', 'High-quality sound with noise cancellation.', 99.99, 50, NOW() - INTERVAL 120 DAY, 0),
(@seller1_id, 'Smartwatch X', 'Track your fitness and receive notifications.', 149.99, 30, NOW() - INTERVAL 100 DAY, 0),
(@seller2_id, 'Summer Dress', 'Light and airy for the summer season.', 45.00, 15, NOW() - INTERVAL 80 DAY, 0),
(@seller2_id, 'Designer T-Shirt', 'Premium cotton t-shirt.', 29.99, 5, NOW() - INTERVAL 70 DAY, 0), -- Low Stock
(@seller3_id, 'Fantasy Novel', 'An epic adventure story.', 15.50, 100, NOW() - INTERVAL 60 DAY, 0),
(@seller3_id, 'Cookbook', 'Recipes from around the world.', 22.00, 70, NOW() - INTERVAL 50 DAY, 0),
(@seller1_id, 'Portable Charger', 'Fast charging for all your devices.', 35.99, 0, NOW() - INTERVAL 40 DAY, 0), -- Out of Stock
(@seller4_id, 'Reusable Water Bottle', 'Eco-friendly and durable.', 19.99, 80, NOW() - INTERVAL 20 DAY, 0),
(@seller4_id, 'Bamboo Toothbrush Set', 'Sustainable oral care.', 12.00, 20, NOW() - INTERVAL 15 DAY, 0),
(@seller1_id, 'Gaming Mouse', 'Ergonomic design for gamers.', 59.99, 12, NOW() - INTERVAL 10 DAY, 0);

SET @prod_headphones = (SELECT id FROM products WHERE name = 'Wireless Headphones');
SET @prod_smartwatch = (SELECT id FROM products WHERE name = 'Smartwatch X');
SET @prod_dress = (SELECT id FROM products WHERE name = 'Summer Dress');
SET @prod_tshirt = (SELECT id FROM products WHERE name = 'Designer T-Shirt');
SET @prod_novel = (SELECT id FROM products WHERE name = 'Fantasy Novel');
SET @prod_cookbook = (SELECT id FROM products WHERE name = 'Cookbook');
SET @prod_charger = (SELECT id FROM products WHERE name = 'Portable Charger');
SET @prod_bottle = (SELECT id FROM products WHERE name = 'Reusable Water Bottle');
SET @prod_toothbrush = (SELECT id FROM products WHERE name = 'Bamboo Toothbrush Set');
SET @prod_gaming_mouse = (SELECT id FROM products WHERE name = 'Gaming Mouse');


-- Sample data for 'product_categories' table (linking products to categories)
INSERT INTO product_categories (product_id, category_id) VALUES
(@prod_headphones, @cat_electronics), (@prod_headphones, @cat_accessories),
(@prod_smartwatch, @cat_electronics), (@prod_smartwatch, @cat_accessories),
(@prod_dress, @cat_apparel),
(@prod_tshirt, @cat_apparel),
(@prod_novel, @cat_books),
(@prod_cookbook, @cat_books), (@prod_cookbook, @cat_home_goods),
(@prod_charger, @cat_electronics), (@prod_charger, @cat_accessories),
(@prod_bottle, @cat_home_goods), (@prod_bottle, @cat_sustainable),
(@prod_toothbrush, @cat_home_goods), (@prod_toothbrush, @cat_sustainable),
(@prod_gaming_mouse, @cat_electronics);


-- Sample data for 'orders' table
-- IDs of buyers
SET @buyer1_id = (SELECT id FROM users WHERE email = 'jane.smith@example.com');
SET @buyer2_id = (SELECT id FROM users WHERE email = 'alice.brown@example.com');
SET @buyer3_id = (SELECT id FROM users WHERE email = 'bob.white@example.com');
SET @buyer4_id = (SELECT id FROM users WHERE email = 'usera@example.com');
SET @buyer5_id = (SELECT id FROM users WHERE email = 'userf@example.com');

INSERT INTO orders (user_id, total_amount, status, created_at, billing_country) VALUES
(@buyer1_id, 144.99, 'completed', NOW() - INTERVAL 90 DAY, 'United States'),
(@buyer1_id, 45.00, 'processing', NOW() - INTERVAL 85 DAY, 'Canada'),
(@buyer2_id, 15.50, 'completed', NOW() - INTERVAL 75 DAY, 'United States'),
(@buyer3_id, 29.99, 'completed', NOW() - INTERVAL 65 DAY, 'United Kingdom'),
(@buyer2_id, 22.00, 'pending', NOW() - INTERVAL 55 DAY, 'Canada'),
(@buyer1_id, 99.99, 'completed', NOW() - INTERVAL 45 DAY, 'Australia'),
(@buyer4_id, 19.99, 'completed', NOW() - INTERVAL 35 DAY, 'United States'),
(@buyer5_id, 12.00, 'completed', NOW() - INTERVAL 25 DAY, 'Germany'),
(@buyer1_id, 59.99, 'shipped', NOW() - INTERVAL 15 DAY, 'France'),
(@buyer2_id, 35.99, 'completed', NOW() - INTERVAL 5 DAY, 'United States'),
(@buyer3_id, 45.00, 'completed', NOW() - INTERVAL 10 DAY, 'United States'),
(@buyer4_id, 15.50, 'completed', NOW() - INTERVAL 80 DAY, 'Japan'),
(@buyer5_id, 149.99, 'completed', NOW() - INTERVAL 70 DAY, 'Germany'),
(@buyer1_id, 22.00, 'pending', NOW() - INTERVAL 60 DAY, 'France'),
(@buyer2_id, 99.99, 'completed', NOW() - INTERVAL 50 DAY, 'United Kingdom');


-- Sample data for 'order_items' table (linking orders to products)
-- Ensure order_id corresponds to existing orders, and product_id to existing products
INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES
((SELECT id FROM orders WHERE user_id = @buyer1_id AND created_at <= NOW() - INTERVAL 90 DAY LIMIT 1), @prod_headphones, 1, 99.99),
((SELECT id FROM orders WHERE user_id = @buyer1_id AND created_at <= NOW() - INTERVAL 90 DAY LIMIT 1), @prod_novel, 1, 15.50),
((SELECT id FROM orders WHERE user_id = @buyer1_id AND created_at <= NOW() - INTERVAL 85 DAY AND status = 'processing' LIMIT 1), @prod_dress, 1, 45.00),
((SELECT id FROM orders WHERE user_id = @buyer2_id AND created_at <= NOW() - INTERVAL 75 DAY LIMIT 1), @prod_novel, 1, 15.50),
((SELECT id FROM orders WHERE user_id = @buyer3_id AND created_at <= NOW() - INTERVAL 65 DAY LIMIT 1), @prod_tshirt, 1, 29.99),
((SELECT id FROM orders WHERE user_id = @buyer2_id AND created_at <= NOW() - INTERVAL 55 DAY AND status = 'pending' LIMIT 1), @prod_cookbook, 1, 22.00),
((SELECT id FROM orders WHERE user_id = @buyer1_id AND created_at <= NOW() - INTERVAL 45 DAY LIMIT 1), @prod_headphones, 1, 99.99),
((SELECT id FROM orders WHERE user_id = @buyer4_id AND created_at <= NOW() - INTERVAL 35 DAY LIMIT 1), @prod_bottle, 1, 19.99),
((SELECT id FROM orders WHERE user_id = @buyer5_id AND created_at <= NOW() - INTERVAL 25 DAY LIMIT 1), @prod_toothbrush, 1, 12.00),
((SELECT id FROM orders WHERE user_id = @buyer1_id AND created_at <= NOW() - INTERVAL 15 DAY AND status = 'shipped' LIMIT 1), @prod_gaming_mouse, 1, 59.99),
((SELECT id FROM orders WHERE user_id = @buyer2_id AND created_at <= NOW() - INTERVAL 5 DAY LIMIT 1), @prod_charger, 1, 35.99);

-- Additional order_items for other seeded orders
INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase) VALUES
((SELECT id FROM orders WHERE user_id = @buyer3_id AND created_at <= NOW() - INTERVAL 10 DAY LIMIT 1), @prod_dress, 1, 45.00),
((SELECT id FROM orders WHERE user_id = @buyer4_id AND created_at <= NOW() - INTERVAL 80 DAY LIMIT 1), @prod_novel, 1, 15.50),
((SELECT id FROM orders WHERE user_id = @buyer5_id AND created_at <= NOW() - INTERVAL 70 DAY LIMIT 1), @prod_smartwatch, 1, 149.99),
((SELECT id FROM orders WHERE user_id = @buyer1_id AND created_at <= NOW() - INTERVAL 60 DAY AND status = 'pending' LIMIT 1), @prod_cookbook, 1, 22.00),
((SELECT id FROM orders WHERE user_id = @buyer2_id AND created_at <= NOW() - INTERVAL 50 DAY LIMIT 1), @prod_headphones, 1, 99.99);
