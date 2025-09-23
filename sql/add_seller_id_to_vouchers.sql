-- Add seller scoping to vouchers (idempotent where supported)
ALTER TABLE vouchers
  ADD COLUMN IF NOT EXISTS seller_id INT NULL AFTER code;

-- Optional: index for faster seller queries
CREATE INDEX IF NOT EXISTS idx_vouchers_seller_id ON vouchers (seller_id);

-- Note: If your MySQL version doesn't support IF NOT EXISTS on ADD COLUMN,
-- run this only once or wrap with a conditional in a migration framework.


