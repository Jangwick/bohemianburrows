<?php
/**
 * Display helpers for consistent formatting across the application
 */

/**
 * Get payment method display name
 * 
 * @param string $method The payment method from database
 * @return string Formatted payment method name
 */
function get_payment_method_name($method) {
    $trimmed_method = trim($method ?? ''); // Handle null, then trim

    if ($trimmed_method === '') {
        return 'Not Specified'; // Explicitly handle empty/null after trimming
    }
    
    $lower_method = strtolower($trimmed_method);
    
    switch ($lower_method) {
        case 'cod':
            return 'Cash on Delivery';
        case 'cash':
            return 'Cash';
        case 'gcash':
            return 'GCash';
        case 'paymaya':
            return 'PayMaya';
        case 'card':
            return 'Credit/Debit Card';
        default:
            // For unknown, non-empty methods, display the stored value (capitalized)
            return ucfirst($trimmed_method); 
    }
}

/**
 * Displays payment method with appropriate formatting
 * 
 * @param string $method Payment method (cash, card, gcash, paymaya, etc)
 * @param bool $show_icon Whether to show with icon
 * @return string Formatted payment method HTML
 */
function display_payment_method($method, $show_icon = false) {
    // Force to string and trim
    $method = trim(strval($method ?: 'cash'));
    
    // Default to cash if empty
    if (empty($method)) {
        $method = 'cash';
    }
    
    $method = strtolower($method);
    
    $icons = [
        'cash' => '<i class="fas fa-money-bill-wave text-success"></i>',
        'card' => '<i class="fas fa-credit-card text-primary"></i>',
        'gcash' => '<i class="fas fa-mobile-alt text-info"></i>',
        'paymaya' => '<i class="fas fa-wallet text-warning"></i>',
        'cod' => '<i class="fas fa-truck text-secondary"></i>'
    ];
    
    $badges = [
        'cash' => '<span class="payment-method-badge payment-cash">Cash</span>',
        'card' => '<span class="payment-method-badge payment-card">Card</span>',
        'gcash' => '<span class="payment-method-badge payment-gcash">GCash</span>',
        'paymaya' => '<span class="payment-method-badge payment-paymaya">PayMaya</span>',
        'cod' => '<span class="payment-method-badge payment-cod">COD</span>'
    ];
    
    // Create display name with proper capitalization
    $display_name = isset($method) ? ucfirst($method) : 'Cash';
    
    // Add icon if requested
    if ($show_icon) {
        $icon = isset($icons[$method]) ? $icons[$method] . ' ' : $icons['cash'] . ' ';
        $badge = isset($badges[$method]) ? $badges[$method] : $badges['cash'];
        return $badge;
    }
    
    return $display_name;
}

/**
 * Display order status with badge
 * 
 * @param string $status The status from database
 * @return string Formatted status HTML
 */
function display_order_status($status) {
    $status = strtolower(trim($status ?? ''));
    
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending">Pending</span>';
        case 'paid':
            return '<span class="status-badge status-paid">Paid</span>';
        case 'processing':
            return '<span class="status-badge status-processing">Processing</span>';
        case 'shipped':
            return '<span class="status-badge status-shipped">Shipped</span>';
        case 'delivered':
            return '<span class="status-badge status-delivered">Delivered</span>';
        case 'completed':
            return '<span class="status-badge status-completed">Completed</span>';
        case 'cancelled':
            return '<span class="status-badge status-cancelled">Cancelled</span>';
        case 'refunded':
            return '<span class="status-badge status-refunded">Refunded</span>';
        default:
            return '<span class="status-badge status-pending">Pending</span>';
    }
}
?>
