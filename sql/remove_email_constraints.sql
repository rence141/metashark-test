-- Fix duplicate email constraints in users table
-- This will allow the same email to be used for multiple accounts with different roles

-- Remove the first email unique constraint
ALTER TABLE users DROP INDEX email;

-- Remove the second email unique constraint  
ALTER TABLE users DROP INDEX email_2;

-- Verify the constraints are removed
SHOW CREATE TABLE users;
