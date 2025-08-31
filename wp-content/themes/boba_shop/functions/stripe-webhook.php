<?php
/**
 * Stripe Webhook Handler
 * Processes Stripe events and updates order statuses
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle Stripe webhook events
 */
function handle_stripe_webhook() {
    // Get the webhook secret from ACF or environment
    $webhook_secret = get_field('stripe_webhook_secret') ?: getenv('STRIPE_WEBHOOK_SECRET');
    
    if (!$webhook_secret) {
        error_log('Stripe webhook secret not configured');
        http_response_code(500);
        exit;
    }
    
    // Get the raw POST data
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
    
    try {
        $event = \Stripe\Webhook::constructEvent(
            $payload, $sig_header, $webhook_secret
        );
    } catch(\UnexpectedValueException $e) {
        error_log('Invalid payload: ' . $e->getMessage());
        http_response_code(400);
        exit;
    } catch(\Stripe\Exception\SignatureVerificationException $e) {
        error_log('Invalid signature: ' . $e->getMessage());
        http_response_code(400);
        exit;
    }
    
    // Handle the event
    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            handle_checkout_completed($session);
            break;
        case 'payment_intent.succeeded':
            $payment_intent = $event->data->object;
            handle_payment_succeeded($payment_intent);
            break;
        case 'payment_intent.payment_failed':
            $payment_intent = $event->data->object;
            handle_payment_failed($payment_intent);
            break;
        default:
            error_log('Received unknown event type: ' . $event->type);
    }
    
    http_response_code(200);
}

/**
 * Handle successful checkout completion
 */
function handle_checkout_completed($session) {
    error_log('Checkout completed for session: ' . $session->id);
    
    // Update order status
    $result = update_order_status(
        $session->id,
        'confirmed',
        'paid'
    );
    
    if ($result) {
        error_log('Order status updated successfully for session: ' . $session->id);
        
        // Update customer ID if available
        if (!empty($session->customer)) {
            update_order_customer_id($session->id, $session->customer);
        }
        
        // Send confirmation email
        send_order_confirmation_email($session->id);
        
        // Update inventory
        update_inventory_from_session($session->id);
    } else {
        error_log('Failed to update order status for session: ' . $session->id);
    }
}

/**
 * Handle successful payment
 */
function handle_payment_succeeded($payment_intent) {
    error_log('Payment succeeded for payment intent: ' . $payment_intent->id);
    
    // Find order by payment intent metadata
    if (!empty($payment_intent->metadata->session_id)) {
        $result = update_order_status(
            $payment_intent->metadata->session_id,
            'confirmed',
            'paid'
        );
        
        if ($result) {
            error_log('Order status updated successfully for payment intent: ' . $payment_intent->id);
        }
    }
}

/**
 * Handle failed payment
 */
function handle_payment_failed($payment_intent) {
    error_log('Payment failed for payment intent: ' . $payment_intent->id);
    
    // Find order by payment intent metadata
    if (!empty($payment_intent->metadata->session_id)) {
        $result = update_order_status(
            $payment_intent->metadata->session_id,
            'pending',
            'failed'
        );
        
        if ($result) {
            error_log('Order status updated to failed for payment intent: ' . $payment_intent->id);
        }
    }
}

/**
 * Send order confirmation email
 */
function send_order_confirmation_email($session_id) {
    $order = get_order_by_session_id($session_id);
    
    if (!$order) {
        error_log('Order not found for session: ' . $session_id);
        return false;
    }
    
    $to = $order->customer_email;
    $subject = 'Order Confirmation - ' . $order->order_id;
    
    $message = "Thank you for your order!\n\n";
    $message .= "Order ID: " . $order->order_id . "\n";
    $message .= "Order Total: £" . number_format($order->order_total, 2) . "\n";
    $message .= "Shipping Cost: £" . number_format($order->shipping_cost, 2) . "\n";
    $message .= "Subtotal: £" . number_format($order->subtotal, 2) . "\n\n";
    $message .= "Delivery Address:\n" . $order->delivery_address . "\n\n";
    
    if (!empty($order->delivery_notes)) {
        $message .= "Delivery Notes: " . $order->delivery_notes . "\n\n";
    }
    
    $message .= "We'll process your order and ship it soon.\n\n";
    $message .= "Best regards,\nYour Store Team";
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    $sent = wp_mail($to, $subject, $message, $headers);
    
    if ($sent) {
        error_log('Order confirmation email sent for order: ' . $order->order_id);
    } else {
        error_log('Failed to send order confirmation email for order: ' . $order->order_id);
    }
    
    return $sent;
}

/**
 * Update inventory after successful order
 */
function update_inventory_from_session($session_id) {
    // This function would update product inventory
    // Implementation depends on your inventory management system
    error_log('Inventory update triggered for session: ' . $session_id);
}

// Register webhook endpoint
add_action('init', function() {
    add_rewrite_rule(
        'stripe-webhook/?$',
        'index.php?stripe_webhook=1',
        'top'
    );
});

add_filter('query_vars', function($vars) {
    $vars[] = 'stripe_webhook';
    return $vars;
});

add_action('template_redirect', function() {
    if (get_query_var('stripe_webhook')) {
        handle_stripe_webhook();
        exit;
    }
});
