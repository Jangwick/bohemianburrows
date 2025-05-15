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
 * Display payment method with optional badge
 * 
 * @param string $method The payment method from database
 * @param bool $badge Whether to return as HTML badge
 * @return string Formatted payment method HTML
 */
function display_payment_method($method, $badge = false) {
    $name = get_payment_method_name($method); // Uses the updated logic
    
    if (!$badge) {
        return $name;
    }
    
    // Determine badge class based on the (potentially unknown) method
    $lower_trimmed_method = strtolower(trim($method ?? ''));
    $class = 'bg-secondary'; // Default badge for unspecified or unknown
    
    if ($name === 'Not Specified') {
        $class = 'bg-light text-dark border';
    } elseif ($lower_trimmed_method == 'cash' || $lower_trimmed_method == 'cod') {
        $class = 'bg-success';
    } elseif ($lower_trimmed_method == 'card') {
        $class = 'bg-primary';
    } elseif ($lower_trimmed_method == 'gcash') {
        $class = 'bg-info';
    } elseif ($lower_trimmed_method == 'paymaya') {
        $class = 'bg-warning text-dark';
    }
    // If it's an unknown method but not "Not Specified", it will use 'bg-secondary'
    
    return '<span class="badge ' . $class . '">' . $name . '</span>';
}

/**
 * Display order status with badge
 * 
 * @param string $status The status from database
 * @return string Formatted status HTML
 */
function display_order_status($status) {
    $status = strtolower(trim($status ?? 'pending'));
    
    $class = 'status-badge status-' . $status;
    $label = ucfirst($status);
    
    return '<span class="badge ' . $class . '">' . $label . '</span>';
}
?>
