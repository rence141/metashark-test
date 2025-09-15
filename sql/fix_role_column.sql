-- Quick fix to add role column if it doesn't exist
-- This will fix the "Unknown column 'is_seller'" error

-- Add role column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('buyer', 'seller', 'admin') DEFAULT 'buyer';

-- Update existing users to have buyer role if they don't have a role
UPDATE users SET role = 'buyer' WHERE role IS NULL OR role = '';

-- Verify the fix
SELECT 'Role column added successfully' as status;
SELECT id, username, role FROM users LIMIT 5;
