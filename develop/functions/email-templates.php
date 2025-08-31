<?php
/**
 * Email Templates for Boba Store
 * 
 * This file contains email templates for order confirmations and other notifications.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Get the confirmation email template
 * 
 * @param object $order Order object
 * @param array $order_items Array of order items
 * @return string HTML email content
 */
function get_confirmation_email_template($order, $order_items) {
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    
    // Get header image URL (you can customize this)
    $header_image_url = get_header_image() ?: $site_url . '/wp-content/uploads/email-header.jpg';
    
    // Format delivery address
    $delivery_address = nl2br(esc_html($order->delivery_address));
    
    // Calculate totals
    $subtotal = $order->subtotal;
    $shipping = $order->shipping_cost;
    $total = $order->order_total;
    
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Order Confirmation - ' . esc_html($site_name) . '</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            }
            
            .email-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 40px 20px;
                text-align: center;
                color: white;
            }
            
            .header-image {
                max-width: 200px;
                height: auto;
                margin-bottom: 20px;
                border-radius: 8px;
            }
            
            .email-header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
                text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            }
            
            .email-header p {
                margin: 10px 0 0 0;
                font-size: 16px;
                opacity: 0.9;
            }
            
            .email-content {
                padding: 40px 30px;
            }
            
            .order-number {
                background: #f8f9fa;
                border-left: 4px solid #667eea;
                padding: 20px;
                margin-bottom: 30px;
                border-radius: 0 8px 8px 0;
            }
            
            .order-number h2 {
                margin: 0 0 10px 0;
                color: #667eea;
                font-size: 24px;
            }
            
            .order-number p {
                margin: 0;
                color: #666;
                font-size: 16px;
            }
            
            .section {
                margin-bottom: 30px;
            }
            
            .section h3 {
                color: #333;
                border-bottom: 2px solid #667eea;
                padding-bottom: 10px;
                margin-bottom: 20px;
                font-size: 20px;
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .info-item {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            
            .info-item label {
                display: block;
                font-weight: 600;
                color: #667eea;
                font-size: 12px;
                text-transform: uppercase;
                margin-bottom: 5px;
            }
            
            .info-item span {
                color: #333;
                font-size: 16px;
            }
            
            .order-items {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
                border: 1px solid #e9ecef;
            }
            
            .order-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px 0;
                border-bottom: 1px solid #e9ecef;
            }
            
            .order-item:last-child {
                border-bottom: none;
            }
            
            .item-details {
                flex: 1;
            }
            
            .item-name {
                font-weight: 600;
                color: #333;
                margin-bottom: 5px;
            }
            
            .item-meta {
                color: #666;
                font-size: 14px;
            }
            
            .item-price {
                text-align: right;
                font-weight: 600;
                color: #333;
            }
            
            .order-summary {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
                border: 1px solid #e9ecef;
            }
            
            .summary-row {
                display: flex;
                justify-content: space-between;
                padding: 10px 0;
                border-bottom: 1px solid #e9ecef;
            }
            
            .summary-row:last-child {
                border-bottom: none;
                border-top: 2px solid #667eea;
                font-weight: 600;
                font-size: 18px;
                color: #667eea;
            }
            
            .delivery-address {
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
                border: 1px solid #e9ecef;
                white-space: pre-line;
            }
            
            .email-footer {
                background: #2c3e50;
                color: white;
                text-align: center;
                padding: 30px 20px;
            }
            
            .email-footer p {
                margin: 0 0 15px 0;
                opacity: 0.8;
            }
            
            .social-links {
                margin-bottom: 20px;
            }
            
            .social-links a {
                color: white;
                text-decoration: none;
                margin: 0 10px;
                opacity: 0.8;
                transition: opacity 0.3s;
            }
            
            .social-links a:hover {
                opacity: 1;
            }
            
            .cta-button {
                display: inline-block;
                background: #667eea;
                color: white;
                text-decoration: none;
                padding: 15px 30px;
                border-radius: 25px;
                font-weight: 600;
                margin: 20px 0;
                transition: background-color 0.3s;
            }
            
            .cta-button:hover {
                background: #5a6fd8;
            }
            
            @media (max-width: 600px) {
                .email-content {
                    padding: 20px 15px;
                }
                
                .info-grid {
                    grid-template-columns: 1fr;
                }
                
                .order-item {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .item-price {
                    text-align: left;
                    margin-top: 10px;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Header with Image -->
            <div class="email-header">
                <img src="' . esc_url($header_image_url) . '" alt="' . esc_attr($site_name) . '" class="header-image">
                <h1>Thank You for Your Order!</h1>
                <p>Your order has been confirmed and is being processed</p>
            </div>
            
            <!-- Main Content -->
            <div class="email-content">
                <!-- Order Number -->
                <div class="order-number">
                    <h2>Order #' . esc_html($order->order_id) . '</h2>
                    <p>Order Date: ' . esc_html(date('F j, Y \a\t g:i A', strtotime($order->created_at))) . '</p>
                </div>
                
                <!-- Customer Information -->
                <div class="section">
                    <h3>Customer Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <label>Name</label>
                            <span>' . esc_html($order->customer_name) . '</span>
                        </div>
                        <div class="info-item">
                            <label>Email</label>
                            <span>' . esc_html($order->customer_email) . '</span>
                        </div>
                    </div>
                </div>
                
                <!-- Delivery Information -->
                <div class="section">
                    <h3>Delivery Information</h3>
                    <div class="delivery-address">
                        ' . $delivery_address . '
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="section">
                    <h3>Items Ordered</h3>
                    <div class="order-items">';
    
    if (!empty($order_items)) {
        foreach ($order_items as $item) {
            $html .= '
                        <div class="order-item">
                            <div class="item-details">
                                <div class="item-name">' . esc_html($item->product_name) . '</div>
                                <div class="item-meta">
                                    SKU: ' . esc_html($item->product_sku ?: 'N/A') . ' | 
                                    Qty: ' . esc_html($item->quantity);
            
            if (!empty($item->variant_id)) {
                $html .= ' | Variant: ' . esc_html($item->variant_id);
            }
            
            $html .= '
                                </div>
                            </div>
                            <div class="item-price">
                                £' . number_format($item->total_price, 2) . '
                            </div>
                        </div>';
        }
    } else {
        $html .= '
                        <p style="text-align: center; color: #666; font-style: italic;">Order items not available</p>';
    }
    
    $html .= '
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="section">
                    <h3>Order Summary</h3>
                    <div class="order-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span>£' . number_format($subtotal, 2) . '</span>
                        </div>
                        <div class="summary-row">
                            <span>Shipping:</span>
                            <span>£' . number_format($shipping, 2) . '</span>
                        </div>
                        <div class="summary-row">
                            <span>Total:</span>
                            <span>£' . number_format($total, 2) . '</span>
                        </div>
                    </div>
                </div>
                
                <!-- Call to Action -->
                <div class="section" style="text-align: center;">
                    <a href="' . esc_url($site_url) . '" class="cta-button">Continue Shopping</a>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="email-footer">
                <div class="social-links">
                    <a href="#">Facebook</a>
                    <a href="#">Twitter</a>
                    <a href="#">Instagram</a>
                </div>
                <p>Thank you for choosing ' . esc_html($site_name) . '!</p>
                <p>If you have any questions, please contact us at <a href="mailto:support@' . parse_url($site_url, PHP_URL_HOST) . '" style="color: white;">support@' . parse_url($site_url, PHP_URL_HOST) . '</a></p>
                <p style="font-size: 12px; opacity: 0.6;">
                    This email was sent to ' . esc_html($order->customer_email) . ' because you placed an order on our website.<br>
                    You can unsubscribe from marketing emails at any time.
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Send confirmation email to customer
 * 
 * @param object $order Order object
 * @param array $order_items Array of order items
 * @return bool True if email sent successfully, false otherwise
 */
function send_confirmation_email($order, $order_items) {
    // Debug logging
    error_log('send_confirmation_email called for order: ' . $order->order_id);
    error_log('Customer email: ' . $order->customer_email);
    error_log('Order items count: ' . count($order_items));
    
    // Get email template
    $email_content = get_confirmation_email_template($order, $order_items);
    
    // Debug: Check if template was generated
    if (empty($email_content)) {
        error_log('ERROR: Email template content is empty');
        return false;
    }
    
    error_log('Email template generated successfully, length: ' . strlen($email_content));
    
    // Email headers
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    error_log('Site name: ' . $site_name);
    error_log('Admin email: ' . $admin_email);
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . $admin_email . '>',
        'Reply-To: ' . $admin_email
    );
    
    // Email subject
    $subject = 'Order Confirmation - ' . $site_name . ' - #' . $order->order_id;
    
    error_log('Attempting to send email with subject: ' . $subject);
    error_log('Email headers: ' . print_r($headers, true));
    
    // Send email
    $sent = wp_mail($order->customer_email, $subject, $email_content, $headers);
    
    // Log email sending
    if ($sent) {
        error_log('SUCCESS: Confirmation email sent successfully to ' . $order->customer_email . ' for order ' . $order->order_id);
    } else {
        error_log('FAILED: Failed to send confirmation email to ' . $order->customer_email . ' for order ' . $order->order_id);
        
        // Additional debugging for wp_mail failures
        global $phpmailer;
        if (isset($phpmailer)) {
            error_log('PHPMailer error: ' . $phpmailer->ErrorInfo);
        }
    }
    
    return $sent;
}

/**
 * Alternative email sending function that bypasses potential plugin conflicts
 * 
 * @param object $order Order object
 * @param array $order_items Array of order items
 * @return bool True if email sent successfully, false otherwise
 */
function send_confirmation_email_direct($order, $order_items) {
    error_log('send_confirmation_email_direct called for order: ' . $order->order_id);
    
    // Get email template
    $email_content = get_confirmation_email_template($order, $order_items);
    
    if (empty($email_content)) {
        error_log('ERROR: Email template content is empty in direct function');
        return false;
    }
    
    // Email headers
    $site_name = get_bloginfo('name');
    $admin_email = get_option('admin_email');
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $site_name . ' <' . $admin_email . '>',
        'Reply-To: ' . $admin_email
    );
    
    // Email subject
    $subject = 'Order Confirmation - ' . $site_name . ' - #' . $order->order_id;
    
    error_log('Direct email function attempting to send...');
    
    // Try to send using WordPress mail function
    $sent = wp_mail($order->customer_email, $subject, $email_content, $headers);
    
    if ($sent) {
        error_log('SUCCESS: Direct email function sent successfully');
        return true;
    }
    
    error_log('Direct wp_mail failed, trying alternative method...');
    
    // Alternative: Try using WordPress's built-in mailer directly
    global $wp_mailer;
    if (!isset($wp_mailer)) {
        require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
        require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
        require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        $wp_mailer = new PHPMailer\PHPMailer\PHPMailer(true);
    }
    
    try {
        $wp_mailer->clearAddresses();
        $wp_mailer->setFrom($admin_email, $site_name);
        $wp_mailer->addAddress($order->customer_email);
        $wp_mailer->Subject = $subject;
        $wp_mailer->isHTML(true);
        $wp_mailer->Body = $email_content;
        $wp_mailer->CharSet = 'UTF-8';
        
        $sent = $wp_mailer->send();
        
        if ($sent) {
            error_log('SUCCESS: Alternative mailer sent successfully');
            return true;
        } else {
            error_log('FAILED: Alternative mailer also failed');
            return false;
        }
    } catch (Exception $e) {
        error_log('EXCEPTION in alternative mailer: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get plain text version of confirmation email
 * 
 * @param object $order Order object
 * @param array $order_items Array of order items
 * @return string Plain text email content
 */
function get_confirmation_email_plain_text($order, $order_items) {
    $site_name = get_bloginfo('name');
    $site_url = get_bloginfo('url');
    
    $text = "Thank You for Your Order!\n";
    $text .= "Your order has been confirmed and is being processed\n\n";
    
    $text .= "Order #" . $order->order_id . "\n";
    $text .= "Order Date: " . date('F j, Y \a\t g:i A', strtotime($order->created_at)) . "\n\n";
    
    $text .= "Customer Information:\n";
    $text .= "Name: " . $order->customer_name . "\n";
    $text .= "Email: " . $order->customer_email . "\n\n";
    
    $text .= "Delivery Address:\n";
    $text .= $order->delivery_address . "\n\n";
    
    $text .= "Items Ordered:\n";
    if (!empty($order_items)) {
        foreach ($order_items as $item) {
            $text .= "- " . $item->product_name . " (Qty: " . $item->quantity . ") - £" . number_format($item->total_price, 2) . "\n";
        }
    }
    
    $text .= "\nOrder Summary:\n";
    $text .= "Subtotal: £" . number_format($order->subtotal, 2) . "\n";
    $text .= "Shipping: £" . number_format($order->shipping_cost, 2) . "\n";
    $text .= "Total: £" . number_format($order->order_total, 2) . "\n\n";
    
    $text .= "Continue shopping at: " . $site_url . "\n\n";
    $text .= "Thank you for choosing " . $site_name . "!\n";
    $text .= "If you have any questions, please contact us at support@" . parse_url($site_url, PHP_URL_HOST);
    
    return $text;
}

/**
 * Test function to manually test email sending
 * Call this function directly to test if emails are working
 */
function test_email_system() {
    error_log('=== TESTING EMAIL SYSTEM ===');
    
    // Create a test order object
    $test_order = (object) array(
        'order_id' => 'TEST_' . time(),
        'customer_name' => 'Test Customer',
        'customer_email' => get_option('admin_email'), // Send to admin email
        'delivery_address' => "123 Test Street\nTest City, TC 12345\nUnited Kingdom",
        'delivery_notes' => 'Test delivery notes',
        'order_total' => 25.99,
        'shipping_cost' => 3.50,
        'subtotal' => 22.49,
        'created_at' => current_time('mysql')
    );
    
    // Create test order items
    $test_items = array(
        (object) array(
            'product_name' => 'Test Product 1',
            'product_sku' => 'TEST001',
            'variant_id' => 'VARIANT1',
            'quantity' => 2,
            'unit_price' => 11.25,
            'total_price' => 22.50
        )
    );
    
    error_log('Test order created: ' . print_r($test_order, true));
    error_log('Test items created: ' . print_r($test_items, true));
    
    // Test the main email function
    error_log('Testing main email function...');
    $result1 = send_confirmation_email($test_order, $test_items);
    error_log('Main email function result: ' . ($result1 ? 'SUCCESS' : 'FAILED'));
    
    // Test the alternative email function
    error_log('Testing alternative email function...');
    $result2 = send_confirmation_email_direct($test_order, $test_items);
    error_log('Alternative email function result: ' . ($result2 ? 'SUCCESS' : 'FAILED'));
    
    error_log('=== EMAIL SYSTEM TEST COMPLETE ===');
    
    return array(
        'main_function' => $result1,
        'alternative_function' => $result2
    );
}
