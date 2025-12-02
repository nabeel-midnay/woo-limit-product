<?php

/**
 * WooCommerce Limited Product Plugin - Timer Manager Class
 * 
 * Handles countdown timer functionality for limited-edition products
 * - Manages timer state and localStorage integration
 * - Provides AJAX endpoints for timer operations
 * - Removes expired limited products from cart
 * 
 * @package WooCommerce Limited Product
 * @version 1.0.0
 */

if (!defined('ABSPATH'))
    exit;

class IJWLP_Timer_Manager
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // AJAX handler to remove limited products when timer expires
        add_action('wp_ajax_ijwlp_remove_expired_limited_products', array($this, 'ajax_remove_expired_limited_products'));
        add_action('wp_ajax_nopriv_ijwlp_remove_expired_limited_products', array($this, 'ajax_remove_expired_limited_products'));

        // AJAX handler to check if cart has limited products
        add_action('wp_ajax_ijwlp_cart_has_limited_products', array($this, 'ajax_cart_has_limited_products'));
        add_action('wp_ajax_nopriv_ijwlp_cart_has_limited_products', array($this, 'ajax_cart_has_limited_products'));

        // Hook into cart item removal to clear timer if no limited items remain
        add_action('woocommerce_cart_item_removed', array($this, 'check_and_clear_timer_if_needed'), 10, 2);
    }

    /**
     * Check if cart contains limited-edition products
     * 
     * @return bool
     */
    public static function cart_has_limited_products()
    {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (self::is_limited_product($cart_item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a cart item is a limited-edition product
     * 
     * @param array $cart_item Cart item data
     * @return bool
     */
    public static function is_limited_product($cart_item)
    {
        $product_id = isset($cart_item['product_id']) ? intval($cart_item['product_id']) : 0;
        $variation_id = isset($cart_item['variation_id']) ? intval($cart_item['variation_id']) : 0;

        // For variations, check the parent product
        $check_id = $variation_id > 0 ? $variation_id : $product_id;

        // Get the actual product to check
        $product = wc_get_product($check_id);
        if (!$product) {
            return false;
        }

        // If it's a variation, get parent
        if ($variation_id > 0) {
            $parent_id = $product->get_parent_id();
            $check_id = $parent_id > 0 ? $parent_id : $check_id;
        }

        // Check meta
        $is_limited = get_post_meta($check_id, '_woo_limit_status', true);

        return $is_limited === 'yes';
    }

    /**
     * AJAX: Remove all limited products from cart when timer expires
     */
    public function ajax_remove_expired_limited_products()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ijwlp-timer-nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'woolimited')
            ));
        }

        if (!function_exists('WC') || !WC()->cart) {
            wp_send_json_error(array(
                'message' => __('Cart not available', 'woolimited')
            ));
        }

        $removed_count = 0;

        // Iterate through cart items and remove limited products
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (self::is_limited_product($cart_item)) {
                WC()->cart->remove_cart_item($cart_item_key);
                $removed_count++;
            }
        }

        // Clear timer from localStorage
        // (This will be handled by frontend JS, but we can log it server-side)

        wp_send_json_success(array(
            'message' => sprintf(
                __('%d limited product(s) removed due to timer expiry', 'woolimited'),
                $removed_count
            ),
            'removed_count' => $removed_count,
            'cart_has_limited' => self::cart_has_limited_products()
        ));
    }

    /**
     * AJAX: Check if cart contains limited products
     */
    public function ajax_cart_has_limited_products()
    {
        wp_send_json_success(array(
            'has_limited' => self::cart_has_limited_products()
        ));
    }

    /**
     * When a cart item is removed, check if there are still limited products
     * If not, the frontend will clear the timer (but we can also do cleanup here)
     * 
     * @param string $cart_item_key Cart item key
     * @param WC_Cart $cart Cart object
     */
    public function check_and_clear_timer_if_needed($cart_item_key, $cart)
    {
        // If no more limited products in cart, timer should be cleared by frontend JS
        // This is a backend marker for any additional processing needed
        if (!self::cart_has_limited_products()) {
            // Log: No limited products remain
            // Frontend JS will clear localStorage
        }
    }
}
