# Meta Shark - Functionality Reference

This document outlines the core functionalities of the Meta Shark application, organized by feature area. It is based on the file structure described in the main `README.md`.

---

## 1. Public & Storefront

Handles the public-facing pages that users can access without logging in.

- **`index.html`**: The main landing page for the application.
- **`main/php/shop.php`**: The primary storefront that lists all available products.
- **`main/php/product-details.php`**: Displays detailed information for a single product, including description, price, and seller info.
- **`main/php/phones.php`, `laptop.php`, etc.**: Category-specific pages that filter products based on their type.

---

## 2. Authentication

Manages user registration, login, and session management.

- **`main/php/login_users.php`**: Presents the login form and processes user credentials.
- **`main/php/signup_users.php`**: Presents the registration form for new buyers.
- **`main/php/seller_signup.php`**: A separate registration form for users who want to become sellers.
- **`main/php/logout.php`**: Destroys the user's session and logs them out.
- **`main/php/forgot_password.php`**: Handles the "forgot password" request by generating and emailing a reset token.
- **`main/php/google_login.php` & `google_callback.php`**: Manages the OAuth 2.0 flow for signing in with a Google account.

---

## 3. Cart & Checkout

Contains the logic for the shopping cart and the final purchase process.

- **`main/php/carts_users.php`**: Displays the contents of the current user's shopping cart. Allows users to update quantities or remove items.
- **`main/php/checkout_users.php`**: The final checkout page where users enter shipping information and select a payment method.
- **`main/php/payment_start.php`**: Initiates the payment process after checkout confirmation.
- **`main/php/payment_mock.php`**: A simulated payment gateway for testing the checkout flow.
- **`main/php/payment_gcash.php`**: A handler for processing payments via GCash (using PayMongo).
- **`main/php/payment_callback.php`**: The callback URL that payment gateways use to notify the application of payment status.

---

## 4. Order Management

Provides views for buyers and sellers to track and manage orders.

- **`main/php/orders.php`**: A buyer-facing list of their past and current orders.
- **`main/php/order_details.php`**: A detailed view of a single order for a buyer.
- **`main/php/order_status.php`**: Shows the current status and tracking information for a buyer's order.
- **`main/php/seller_order_status.php`**: A seller-facing view to manage the status of items they have sold.
- **`main/php/seller_order_updates.php`**: Processes status updates submitted by sellers.

---

## 5. Chat System

An in-app messaging system for communication between users.

- **`main/php/chat.php`**: The main chat interface, including the conversation list and message view.
- **`main/php/chat_handler.php`**: A backend API used for polling for new messages to provide real-time updates.

---

## 6. Seller & Admin Dashboards

A suite of tools for sellers and administrators to manage the platform.

### Seller
- **`main/php/seller_dashboard.php`**: The main landing page for sellers after logging in.
- **`main/php/seller_shop.php`**: A public view of a specific seller's shop and all their products.
- **`main/php/add_product.php`**: Form for sellers to add new products to the marketplace.
- **`main/php/edit_product.php`**: Form for sellers to edit their existing products.
- **`main/php/delete_product.php`**: Handles the deletion of a seller's product.
- **`main/php/seller_profile.php`**: Page for sellers to manage their public profile and shop information.

### Admin
- **`main/php/admin_dashboard.php`**: The main dashboard for administrators, showing key site metrics.
- **`main/php/admin_login.php`**: The secure login portal for administrators.
- **`main/php/admin_requests.php`**: A page for super admins to approve or deny requests for new admin accounts.

---