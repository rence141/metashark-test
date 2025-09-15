-- Add role system to users table
ALTER TABLE users ADD COLUMN role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer';
ALTER TABLE users ADD COLUMN seller_name VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN seller_description TEXT NULL;
ALTER TABLE users ADD COLUMN seller_rating DECIMAL(3,2) DEFAULT 0.00;
ALTER TABLE users ADD COLUMN business_type VARCHAR(50) NULL;
ALTER TABLE users ADD COLUMN is_active_seller BOOLEAN DEFAULT FALSE;

-- Update existing users to have buyer role
UPDATE users SET role = 'buyer' WHERE role IS NULL;

-- Add seller_id column to products table if not exists
ALTER TABLE products ADD COLUMN seller_id INT NOT NULL DEFAULT 1;

-- Add foreign key constraint for seller_id
ALTER TABLE products ADD CONSTRAINT fk_products_seller 
FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE;

-- Update existing products to have seller_id = 1 (first user)
UPDATE products SET seller_id = 1 WHERE seller_id = 1;

-- Create user_roles table for additional role management (optional)
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role VARCHAR(50) NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_role (user_id, role)
);

-- Create role_permissions table (optional for future expansion)
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    permission VARCHAR(100) NOT NULL,
    UNIQUE KEY unique_role_permission (role, permission)
);

-- Insert default permissions
INSERT IGNORE INTO role_permissions (role, permission) VALUES
('buyer', 'view_products'),
('buyer', 'add_to_cart'),
('buyer', 'make_purchase'),
('seller', 'view_products'),
('seller', 'add_to_cart'),
('seller', 'make_purchase'),
('seller', 'add_products'),
('seller', 'edit_products'),
('seller', 'delete_products'),
('seller', 'view_sales'),
('seller', 'manage_inventory'),
('admin', 'view_products'),
('admin', 'add_to_cart'),
('admin', 'make_purchase'),
('admin', 'add_products'),
('admin', 'edit_products'),
('admin', 'delete_products'),
('admin', 'view_sales'),
('admin', 'manage_inventory'),
('admin', 'manage_users'),
('admin', 'manage_orders'),
('admin', 'view_analytics');
