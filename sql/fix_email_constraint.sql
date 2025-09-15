-- Fix email constraint to allow multiple regular users with same email
-- but only one seller per email

-- First, remove the unique constraint on email
ALTER TABLE users DROP INDEX email;

-- Remove the duplicate email_2 constraint if it exists
ALTER TABLE users DROP INDEX email_2;

-- Add a composite unique constraint for seller emails only
-- This allows multiple regular users with same email, but only one seller per email
ALTER TABLE users ADD CONSTRAINT unique_seller_email 
UNIQUE (email, role) 
WHERE role IN ('seller', 'admin');

-- Note: The above constraint might not work in older MySQL versions
-- Alternative approach: Create a separate index for seller emails
-- ALTER TABLE users ADD INDEX idx_seller_email (email, role);
