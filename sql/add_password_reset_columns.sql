-- Add password reset token and expiry to users
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS reset_token VARCHAR(255) NULL AFTER verification_expires,
  ADD COLUMN IF NOT EXISTS reset_expires DATETIME NULL AFTER reset_token;

CREATE INDEX IF NOT EXISTS idx_users_reset_expires ON users (reset_expires);


