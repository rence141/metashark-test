# MetaShark

## Overview
MetaShark is a PHP/MySQL e‑commerce web app supporting both buyers and sellers. It provides product browsing by category, shopping cart and checkout flows with vouchers, and a seller dashboard for managing products. Sessions, roles, CSRF protection, and secure OAuth login are implemented throughout.

## Recent Updates (2025)
- **Security**: Google OAuth credentials are now secured outside the web root.
- **Password Reset**: Fully functional forgot/reset password flow with improved UX and error handling.
- **AI Chat**: Chat logic separated into its own PHP file for maintainability.
- **Checkout**: Reviewed and improved for security, input validation, and transactional order creation.
- **Payment Integration**: GCash/PayMongo integration in progress (`payment_gcash.php`).
- **Composer**: Updated `composer.json` to require `guzzlehttp/guzzle` and `phpmailer/phpmailer`.
- **Debugging**: Payment scripts now provide detailed debug/error output.

## Features
### Buyer Accounts
- Signup, login, logout
- Profile page with image support and theme preference (dark/light)
- Google OAuth login support
- Forgot/reset password flow

### Seller System
- Become Seller onboarding and separate seller signup/login
- Seller dashboard: add/edit/delete products, ownership checks
- Public seller shop page per seller

### Product Catalog
- Main shop listing with seller info and categories
- Category pages: accessories, gaming, laptop, phone, tablets
- Product stock tracking, featured/active flags

### Cart and Checkout
- AJAX-enhanced add to cart, cart page, cart count
- Checkout with shipping details, payment method selection
- Voucher/discount code system with min‑purchase rules and CSRF tokens
- Order creation: line items, stock decrement, cart clearing
- Email notification scaffolding for buyers and sellers

### AI Chat
- Interactive chat interface with Verna, a professional virtual assistant for MetaShark support
- Session-based chat history with sidebar navigation for viewing past conversations
- New chat functionality to start a fresh session with a clean message area
- Refresh chat to sync with server-side history, ensuring up-to-date session data
- Clear history option to delete all chat sessions with confirmation prompt
- Delete individual sessions from the sidebar for precise history management
- Responsive UI with light/dark theme support and notifications for user actions

### Sessions, Roles, and Security
- Session-based auth and role enforcement (buyer/seller/admin)
- CSRF tokens on sensitive POST actions; filtered/sanitized inputs
- Test pages for session/cart isolation and seller redirects

### Theme and UI
- Light/dark mode toggle persisted in session
- Responsive styles for checkout and fonts

### Maintenance and Tooling
- DB connection helper, SQL migrations, cleanup/fix scripts
- Profile image investigation/fix tools

## Tech Stack
- **Backend**: PHP (mysqli)
- **Database**: MySQL/MariaDB
- **Frontend**: HTML/CSS/JS
- **Vendor**: Composer (`guzzlehttp/guzzle`, `phpmailer/phpmailer`, Twilio SDK)

## Project Structure
Root static pages: index.html, about_us.html, privacy_policy.html
App code: main/php/
Auth & users:
login_users.php, signup_users.php, profile.php, logout.php,
forgot_password.php, google_login_process.php, google_callback.php
Shop & categories:
shop.php, accessories.php, gaming.php, laptop.php, phone.php, tablets.php
Cart & checkout:
carts_users.php, checkout_users.php
Sellers:
become_seller.php, seller_signup.php, seller_login.php,
seller_dashboard.php, seller_profile.php, seller_shop.php,
add_product.php, edit_product.php, delete_product.php
Utilities & tests:
db.php, theme_toggle.php, debug_, test_, verify_cleanup.php,
ai_chat.php, payment_gcash.php
Assets: uploads/ (product/profile images), fonts/
Styles: css/
SQL migrations: sql/

## Setup
1. Install XAMPP (Apache + MySQL) and place this repo under `htdocs`.
2. Create a MySQL database and user.
3. Update credentials in `main/php/db.php`.
4. Import schema/migrations in order (`sql/`).
5. Start Apache and MySQL, then visit `http://localhost/SaysonCo/index.html` or `main/php/shop.php`.
6. Run Composer in `main/` if vendor libraries are required:
   ```bash
   composer install
   ```

## Database & Migrations
Core tables: setup_seller_system.sql, setup_cart_tables.sql

Incremental updates: add_missing_columns.sql, add_seller_columns.sql

Fixes: fix_*, remove_email_constraints.sql

Consolidated migrations: final_migration.sql, safe_final_migration.sql

## Configuration
DB: Edit main/php/db.php for host, user, password, and database.

Email/Twilio: Composer includes Twilio SDK; not currently configured.

Google OAuth: Credentials secured; see google_login_process.php and google_callback.php.

## Security Notes
- Session-based authentication and role checks
- CSRF tokens on sensitive POST actions
- Input sanitization/validation before queries
- Secure OAuth and password reset handling

## Key Pages
- Shop: main/php/shop.php
- Cart: main/php/carts_users.php
- Checkout: main/php/checkout_users.php
- User login/signup: main/php/login_users.php, main/php/signup_users.php
- Seller dashboard: main/php/seller_dashboard.php
- AI Chat: main/php/ai_chat.php
- Payment: main/php/payment_gcash.php

## License
Bicol University. This is a project made in Bicol University Polangui CSD any form of copyright is only approved in either the owner of this project and collaborators of the University.