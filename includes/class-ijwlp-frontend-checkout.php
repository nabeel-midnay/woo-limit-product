<?php

/**
 * WooCommerce Limited Product Plugin - Frontend Checkout Page Class
 * 
 * Handles checkout page functionality for limited edition products
 * 
 * @package WooCommerce Limited Product
 * @version 1.0.0
 */

if (!defined('ABSPATH'))
    exit;

class IJWLP_Frontend_Checkout
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Add limited edition number to order item meta
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'add_limited_edition_to_order_item'), 10, 4);

        // Update status from 'block' to 'ordered' when order is created
        add_action('woocommerce_checkout_order_created', array($this, 'update_limited_edition_status'), 10, 1);


        // Show Limited Edition Number with order item meta
        add_action('woocommerce_order_item_meta_start', array($this, 'show_limited_edition_in_item_meta'), 10, 3);
    }

    /**
     * Display Limited Edition Number in order item meta
     */
    public function show_limited_edition_in_item_meta($item_id, $item, $order)
    {
        $limited_number = $item->get_meta('Limited Edition Number');
        $numbers = IJWLP_Frontend_Common::normalize_limited_number_for_processing($limited_number);

        if (!empty($numbers)) {
            $display = implode(', ', $numbers);
            echo '<div class="ijwlp-limited-number-meta"><strong>' . esc_html__('Limited Edition Number', 'woo-limit-product') . ':</strong> ' . esc_html($display) . '</div>';
        }
    }

    /**
     * Add limited edition number to order item meta
     */
    public function add_limited_edition_to_order_item($item, $cart_item_key, $values, $order)
    {
        if (isset($values['woo_limit']) && $values['woo_limit'] !== '') {
            $limited_number = IJWLP_Frontend_Common::normalize_limited_number_for_storage($values['woo_limit']);
            if ($limited_number !== '') {
                $item->add_meta_data('Limited Edition Number', $limited_number);
                $item->add_meta_data('_cart_item_key', $cart_item_key);
            }
        }
    }

    /**
     * Update limited edition number status from 'block' to 'ordered' when order is created
     */
    public function update_limited_edition_status($order)
    {
        if (!$order) {
            return;
        }

        $order_id = $order->get_id();
        global $wpdb, $table_prefix;
        $table = $table_prefix . 'woo_limit';

        // Process each order item
        foreach ($order->get_items() as $item_id => $item) {
            $cart_item_key = $item->get_meta('_cart_item_key');
            $limited_number = $item->get_meta('Limited Edition Number');

            if (empty($limited_number) || empty($cart_item_key)) {
                continue;
            }

            // Normalize limited_number to string for DB comparisons
            $limited_number = IJWLP_Frontend_Common::normalize_limited_number_for_storage($limited_number);


            // Get product ID
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            $actual_product_id = $variation_id > 0 ? $variation_id : $product_id;

            // Get parent product ID
            $product = wc_get_product($actual_product_id);
            $parent_product_id = $actual_product_id;

            if ($product && $product->is_type('variation')) {
                $parent_product_id = $product->get_parent_id();
            }

            // Update database record: change status from 'block' to 'ordered'
            $updated = $wpdb->update(
                $table,
                array(
                    'order_id' => (string) $order_id,
                    'order_item_id' => (string) $item_id,
                    'status' => 'ordered',
                    'order_status' => $order->get_status()
                ),
                array(
                    'cart_key' => (string) $cart_item_key,
                    'limit_no' => (string) $limited_number,
                    'status' => 'block'
                ),
                array('%s', '%s', '%s', '%s'),
                array('%s', '%s', '%s')
            );

            if ($updated === false || $updated === 0) {
                // Try to find by product ID and number if cart key doesn't match
                $updated = $wpdb->update(
                    $table,
                    array(
                        'order_id' => (string) $order_id,
                        'order_item_id' => (string) $item_id,
                        'status' => 'ordered',
                        'order_status' => $order->get_status()
                    ),
                    array(
                        'parent_product_id' => (string) $parent_product_id,
                        'limit_no' => (string) $limited_number,
                        'status' => 'block'
                    ),
                    array('%s', '%s', '%s', '%s'),
                    array('%s', '%s', '%s')
                );
            }
        }
    }
}
