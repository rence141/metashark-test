-- Adds user verification fields and order payment fields (idempotent/safe)
-- Run this after your base schema is created.

-- USERS: add verification columns
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
  ADD COLUMN IF NOT EXISTS verification_code VARCHAR(12) NULL AFTER is_verified,
  ADD COLUMN IF NOT EXISTS verification_expires DATETIME NULL AFTER verification_code;

-- ORDERS: add status and paid timestamp
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER total,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER status;

-- Helpful indexes
CREATE INDEX IF NOT EXISTS idx_users_is_verified ON users (is_verified);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status);

-- Note: Application expects tables `order_items`, `vouchers`, `cart` to exist.
-- Ensure `order_items(order_id, product_id, quantity, price)` is present in your schema.


