<?php
/**
 * Shared utility functions for The Bohemian Burrows
 */

// Only declare the function if it doesn't already exist
if (!function_exists('formatPaymentMethod')) {
    /**
     * Format payment method for consistent display across the site
     * 
     * @param string $method Payment method code
     * @param bool $badge Whether to return as a badge (true) or plain text (false)
     * @return string Formatted payment method name
     */
    function formatPaymentMethod($method, $badge = false) {
        // Normalize input to handle case inconsistencies
        $method = strtolower(trim($method));
        
        // Map payment method codes to display names
        $paymentMethods = [
            'cod' => 'Cash on Delivery',
            'cash' => 'Cash',
            'gcash' => 'GCash',
            'paymaya' => 'PayMaya',
            'card' => 'Credit/Debit Card'
        ];
        
        // Get display name or default to capitalized input
        $displayName = isset($paymentMethods[$method]) ? 
                       $paymentMethods[$method] : 
                       ucfirst($method);
        
        // Return as HTML badge if requested
        if ($badge) {
            $badgeClass = getBadgeClassForPaymentMethod($method);
            return '<span class="badge ' . $badgeClass . '">' . $displayName . '</span>';
        }
        
        return $displayName;
    }
}

// Only declare this helper function if it doesn't already exist
if (!function_exists('getBadgeClassForPaymentMethod')) {
    /**
     * Get Bootstrap badge class for a payment method
     *
     * @param string $method Payment method code
     * @return string CSS class for the badge
     */
    function getBadgeClassForPaymentMethod($method) {
        $method = strtolower(trim($method));
        
        switch ($method) {
            case 'cod':
            case 'cash':
                return 'bg-success';
            case 'card':
                return 'bg-primary';
            case 'gcash':
                return 'bg-info';
            case 'paymaya':
                return 'bg-warning text-dark';
            default:
                return 'bg-secondary';
        }
    }
}

/**
 * Format order status for consistent display across the site
 * 
 * @param string $status Status code
 * @param bool $badge Whether to return as a badge (true) or plain text (false)
 * @return string Formatted status
 */
function formatOrderStatus($status, $badge = false) {
    $status = strtolower($status);
    
    if ($badge) {
        switch($status) {
            case 'pending':
                return '<span class="badge status-badge status-pending">Pending</span>';
            case 'processing':
                return '<span class="badge status-badge status-processing">Processing</span>';
            case 'shipped':
                return '<span class="badge status-badge status-shipped">Shipped</span>';
            case 'delivered':
                return '<span class="badge status-badge status-delivered">Delivered</span>';
            case 'cancelled':
                return '<span class="badge status-badge status-cancelled">Cancelled</span>';
            default:
                return '<span class="badge status-badge status-' . $status . '">' . ucfirst($status) . '</span>';
        }
    } else {
        return ucfirst($status);
    }
}
?>
