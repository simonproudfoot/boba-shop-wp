# Enhanced Checkout System Setup

This document outlines the new checkout system that includes shipping calculations, delivery address collection, and order management.

## Features

### 1. Shipping Calculation
- **Standard shipping**: £3.50
- **Free shipping**: Orders over £40
- Shipping costs are automatically calculated and added to Stripe payments

### 2. Delivery Address Collection
- Full customer information collection
- Delivery address with multiple fields
- Optional delivery notes (e.g., "Leave behind the bin")
- Form validation and AJAX submission

### 3. Order Management
- Custom database table for orders
- Order status tracking
- Admin interface for managing orders
- Stripe integration with webhook support

## Setup Instructions

### 1. Database Setup
The system will automatically create the required database table when the theme is activated. The table `wp_shop_orders` will store:
- Order details
- Customer information
- Delivery address
- Stripe transaction data
- Order status and payment status

### 2. Stripe Configuration
Ensure you have the following ACF fields set up in "Shop Settings":
- `stripe_secret_key` - Your Stripe secret key
- `stripe_publishable_key` - Your Stripe publishable key
- `stripe_webhook_secret` - Webhook endpoint secret (optional)

### 3. Webhook Setup
In your Stripe dashboard, set up a webhook endpoint pointing to:
```
https://yoursite.com/stripe-webhook
```

The webhook should listen for these events:
- `checkout.session.completed`
- `payment_intent.succeeded`
- `payment_intent.payment_failed`

### 4. File Structure
The following files have been created/modified:
```
develop/
├── functions/
│   ├── orders.php              # Order management functions
│   ├── ajax-handlers.php       # AJAX handlers for forms
│   ├── stripe-webhook.php      # Stripe webhook processing
│   └── admin-orders.php        # Admin interface for orders
├── page-checkout.php           # Updated checkout page
└── functions.php               # Main functions file (updated)
```

## How It Works

### 1. Checkout Flow
1. Customer views cart with shipping calculations
2. Customer fills out delivery address form
3. Address is saved via AJAX
4. Order is created in database
5. Stripe checkout session is created with shipping included
6. Customer completes payment
7. Webhook processes successful payment
8. Order status is updated

### 2. Shipping Logic
```php
function calculate_shipping_cost($subtotal) {
    $free_shipping_threshold = 40.00; // £40
    $shipping_cost = 3.50; // £3.50
    
    if ($subtotal >= $free_shipping_threshold) {
        return 0.00; // Free shipping
    }
    
    return $shipping_cost;
}
```

### 3. Order Data Structure
Each order includes:
- Unique order ID (e.g., ORD1234567890abc)
- Customer details (name, email)
- Delivery address (formatted)
- Delivery notes
- Order totals (subtotal, shipping, total)
- Stripe session ID and customer ID
- Order and payment status

## Admin Features

### Order Management
- View all orders in admin panel
- Update order status (pending, confirmed, shipped, delivered, cancelled)
- View detailed order information
- Track payment status

### Order Statuses
- **Pending**: Order created, awaiting payment
- **Confirmed**: Payment received, order confirmed
- **Shipped**: Order has been shipped
- **Delivered**: Order delivered to customer
- **Cancelled**: Order cancelled

## Customization

### Shipping Rules
To modify shipping rules, edit the `calculate_shipping_cost()` function in `functions/orders.php`.

### Form Fields
To add/remove delivery form fields, modify the form HTML in `page-checkout.php` and update the corresponding AJAX handler.

### Order Statuses
To add custom order statuses, modify the admin interface in `functions/admin-orders.php`.

## Testing

### Test Mode
1. Use Stripe test keys
2. Test with test card numbers
3. Verify webhook delivery
4. Check order creation in database

### Production Mode
1. Switch to live Stripe keys
2. Test with real payment methods
3. Verify webhook security
4. Monitor order processing

## Troubleshooting

### Common Issues

1. **Orders not being created**
   - Check database table exists
   - Verify AJAX handlers are working
   - Check for JavaScript errors

2. **Shipping not calculated**
   - Verify `calculate_shipping_cost()` function is included
   - Check cart total calculation

3. **Webhook not working**
   - Verify webhook endpoint URL
   - Check webhook secret configuration
   - Monitor error logs

4. **Stripe session creation fails**
   - Verify Stripe keys are correct
   - Check line items format
   - Verify currency settings

### Debug Mode
Enable WordPress debug logging to troubleshoot issues:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Security Considerations

- All form submissions use nonces
- Input is sanitized and validated
- AJAX endpoints verify permissions
- Webhook signature verification
- SQL queries use prepared statements

## Performance Notes

- Orders table is indexed for common queries
- AJAX requests are lightweight
- Webhook processing is asynchronous
- Admin interface loads orders efficiently

## Support

For issues or questions:
1. Check error logs
2. Verify configuration
3. Test with minimal setup
4. Review Stripe documentation
