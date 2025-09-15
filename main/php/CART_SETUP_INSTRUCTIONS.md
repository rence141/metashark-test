# Shopping Cart Setup Instructions

## Database Setup

To enable the shopping cart functionality, you need to set up the database tables. Follow these steps:

### 1. Run the SQL Setup Script

Execute the SQL commands in `setup_cart_tables.sql` in your MySQL database:

```sql
-- Run this in your MySQL database (MetaAccesories)
-- You can copy and paste the contents of setup_cart_tables.sql
```

### 2. Database Tables Created

The setup script will create:

- **`products`** table - Stores product information
- **`cart`** table - Stores user cart items
- Sample product data (8 products)

### 3. Features Included

✅ **Cart Management:**
- Add items to cart
- View cart contents
- Update item quantities
- Remove individual items
- Clear entire cart
- Real-time cart count in navigation

✅ **User Interface:**
- Responsive design matching site theme
- ASUS ROG green color scheme
- Mobile-friendly layout
- Interactive quantity controls

✅ **Database Integration:**
- Secure prepared statements
- User session validation
- Cart persistence across sessions

## File Structure

```
main/php/
├── carts_users.php          # Main cart page
├── setup_cart_tables.sql   # Database setup script
├── shop.php               # Updated with cart functionality
└── CART_SETUP_INSTRUCTIONS.md
```

## Usage

1. **Setup Database:** Run the SQL setup script
2. **Login:** Users must be logged in to use cart features
3. **Add to Cart:** Click "Add to Cart" buttons on product pages
4. **View Cart:** Click "Cart" in the navigation menu
5. **Manage Items:** Update quantities or remove items from cart page

## Cart Features

- **Add Items:** Products are added to cart with quantity 1
- **Update Quantities:** Use +/- buttons or direct input
- **Remove Items:** Individual item removal with confirmation
- **Clear Cart:** Remove all items with confirmation
- **Cart Count:** Shows total items in navigation
- **Order Summary:** Displays subtotal, tax, and total

## Security Features

- User session validation
- SQL injection protection with prepared statements
- CSRF protection through form validation
- Input sanitization and validation

## Styling

The cart page uses the same design language as the main site:
- Dark theme (#0A0A0A background)
- ASUS ROG green (#44D62C) accents
- Responsive grid layout
- Hover effects and transitions
- Mobile-optimized interface

## Troubleshooting

**Cart not working?**
- Ensure database tables are created
- Check user login status
- Verify database connection in `db.php`

**Products not showing?**
- Run the setup script to add sample products
- Check if products table exists and has data

**Styling issues?**
- Ensure `fonts/fonts.css` is accessible
- Check browser console for CSS errors
