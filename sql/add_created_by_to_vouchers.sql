-- Adds created_by to vouchers for auditing who created each voucher.
-- Run after base vouchers table exists.

ALTER TABLE vouchers
  ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER seller_id;

CREATE INDEX IF NOT EXISTS idx_vouchers_created_by ON vouchers (created_by);

-- Optional backfill (if you want to set created_by = seller_id when present):
-- UPDATE vouchers SET created_by = seller_id WHERE created_by IS NULL AND seller_id IS NOT NULL;


