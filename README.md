## Meta Shark — Storefront, Cart, Checkout, and Chat

### Overview
Meta Shark is a small e‑commerce web app built with PHP/MySQL. It includes a public storefront (landing and shop), product details, cart and checkout with payment stubs, order tracking, seller/admin areas, and an in‑app chat system.

### Key Features
- Landing page and category browsing
- Product listing and details
- Cart and checkout flow (with payment start/mock/GCash handlers)
- Order history and status tracking (buyer and seller views)
- Authentication (email/password, optional Google login)
- Seller dashboard and shop management
- In‑app 1:1 chat with media support

### Tech Stack
- PHP 8+ (XAMPP compatible)
- MySQL / MariaDB
- Vanilla JS + Bootstrap Icons
- CSS in `css/*.css`

### App Structure (selected routes and files)
- Public
  - `index.html` — landing page
  - `main/php/shop.php` — storefront/product listing
  - `main/php/product-details.php` — product detail page
  - Category pages: `main/php/phones.php`, `main/php/laptop.php`, `main/php/gaming.php`, `main/php/accessories.php`, `main/php/Tablets.php`
- Auth
  - `main/php/login_users.php`, `main/php/signup_users.php`, `main/php/logout.php`
  - Google login: `main/php/google_login.php`, `main/php/google_callback.php`, `main/php/google_login_process.php`
- Cart & Checkout
  - `main/php/carts_users.php` — cart view
  - `main/php/checkout_users.php` — checkout page
  - Payments: `main/php/payment_start.php`, `main/php/payment_mock.php`, `main/php/payment_gcash.php`, `main/php/payment_callback.php`
- Orders
  - `main/php/orders.php`, `main/php/order_details.php`, `main/php/order_status.php`
  - Sellers: `main/php/seller_order_status.php`, `main/php/seller_order_updates.php`
- Chat
  - `main/php/chat.php` — chat UI + send/receive
  - `main/php/chat_handler.php` — new message polling API
- Sellers/Admin
  - `main/php/seller_shop.php`, `main/php/add_product.php`, `main/php/edit_product.php`, `main/php/delete_product.php`
  - `main/php/seller_dashboard.php`, `main/php/seller_profile.php`
  - `main/php/admin_dashboard.php` plus admin auth files
- Shared
  - `main/php/db.php`, `main/php/config.php`
  - Assets: `css/*.css`, `main/js/*.js`
  - Uploads: `main/php/uploads/`, `main/Uploads/`

### Prerequisites
- XAMPP (Apache + MySQL) or equivalent LAMP/WAMP stack
- A configured `main/php/db.php` that returns a `$conn` MySQLi connection
- Sessions enabled; auth sets `$_SESSION['user_id']`

### Database Schema (core tables)

See ready‑made scripts in `sql/`:
- Cart: `sql/setup_cart_tables.sql`
- Orders: `sql/create_order_items_table.sql`, `sql/add_order_status_updates.sql`
- Users/Roles: `sql/safe_user_roles_migration.sql`, `sql/update_user_roles.sql`
- Notifications: `sql/add_notifications_table.sql`

Users table (excerpt):
```sql
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  seller_name VARCHAR(255) NULL,
  fullname VARCHAR(255) NOT NULL,
  profile_image VARCHAR(255) NULL
);
```

Products (typical shape):
```sql
CREATE TABLE products (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  image VARCHAR(255) NULL,
  category VARCHAR(100) NULL,
  seller_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

Cart tables (from `setup_cart_tables.sql`):
```sql
CREATE TABLE carts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cart_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cart_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  price DECIMAL(10,2) NOT NULL
);
```

Orders (from `create_order_items_table.sql`):
```sql
CREATE TABLE orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'PENDING',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE order_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(10,2) NOT NULL
);
```

Messages (chat):
```sql
CREATE TABLE chat_messages (
  id INT PRIMARY KEY AUTO_INCREMENT,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  message TEXT NULL,
  file_path VARCHAR(255) NULL,
  file_type ENUM('image', 'video', 'other') NULL,
  is_seen TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (sender_id),
  INDEX (receiver_id)
);
```

Notifications (used for chat and possibly orders):
```sql
CREATE TABLE notifications (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  message VARCHAR(255) NOT NULL,
  type VARCHAR(50) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id)
);
```

### Configuration
1. Ensure `main/php/db.php` creates `$conn`:
   ```php
   <?php
   $conn = new mysqli('localhost','username','password','database');
   if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }
   ?>
   ```
2. Make sure authentication sets `$_SESSION['user_id']` after login.
3. Optional theme: sets `<html data-theme>` from `$_SESSION['theme']` (defaults to `dark`).

### File Uploads
- Product images and profile images: `main/php/uploads/`
- Chat message media: `main/php/uploads/messages/` (auto‑created)
- Allowed previews in chat:
  - Images: `jpg, jpeg, png, gif, webp`
  - Videos: `mp4, webm, mov`
  - Other files: offered as download links

Make sure the web server has write permissions to the `main/php/uploads/` folders.

### End‑to‑End Buyer Flow (Landing → Checkout)
1. Landing: `index.html` links into `main/php/shop.php` and categories.
2. Browse: `shop.php` and category pages list products with prices and links to details.
3. Product: `product-details.php` shows full info and add‑to‑cart.
4. Cart: `carts_users.php` lets users edit quantities and remove items.
5. Checkout: `checkout_users.php` summarizes items, totals, shipping/payment options.
6. Payment: `payment_start.php` kicks off; `payment_mock.php` or `payment_gcash.php` simulates/processes payment; `payment_callback.php` finalizes and updates order status.
7. Orders: `orders.php` and `order_details.php` for history; `order_status.php` for current status.

### Chat Flow (in‑app messaging)
1. Open `main/php/chat.php` (requires login).
2. Sidebar lists conversations by last message; pick a user to open the thread.
3. Send text and/or media; messages mark as seen when viewed by the receiver.
4. Client polls `chat_handler.php?action=get_new_messages&seller_id=...` every 3s for lightweight notifications.

### Running Locally (XAMPP)
1. Place the project under `htdocs` (e.g., `C:/xampp/htdocs/SaysonCotest`).
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Import/create the database and tables listed above.
4. Configure `main/php/db.php` with your DB credentials.
5. Visit `http://localhost/SaysonCotest/index.html` for the landing page, then navigate to shop/cart/checkout.

### Troubleshooting
- Blank product or category pages: ensure product records exist; check DB connection in `db.php`.
- Cart not updating: verify `sql/setup_cart_tables.sql` was applied and session is active.
- Checkout issues: review order tables and `payment_*` logs; confirm callback updates status.
- Chat not listing threads: ensure `chat_messages` has rows for the logged‑in user.
- Avatars/media missing: verify files in `main/php/uploads/` and permissions.

### Security Notes
- Validate and sanitize inputs server‑side across auth, cart, checkout, and chat.
- Add CSRF protection to state‑changing POST routes.
- Add file type/size validation on uploads; consider storing outside web root.
- Never trust client totals; always compute server‑side from product and cart tables.

### License
Proprietary (update as appropriate).


