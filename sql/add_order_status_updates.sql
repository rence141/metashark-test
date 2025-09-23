-- Tracks per-order status timeline updates
CREATE TABLE IF NOT EXISTS order_status_updates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  seller_id INT NULL,
  status VARCHAR(100) NOT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_order (order_id),
  INDEX idx_seller (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


