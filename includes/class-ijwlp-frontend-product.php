<?php

/**
 * WooCommerce Limited Product Plugin - Frontend Product Page Class
 * 
 * Handles product page functionality for limited edition products
 * 
 * @package WooCommerce Limited Product
 * @version 1.0.0
 */

if (!defined('ABSPATH'))
    exit;

class IJWLP_Frontend_Product
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Add limited edition number field after add to cart button
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_limited_edition_number_field'), 5);

        // Validate limited edition number before adding to cart
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_limited_edition_number'), 10, 3);

        // Add limited edition number to cart item data
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_limited_edition_to_cart_item'), 10, 3);

        // Modify cart item key to group by variation ID or product ID
        add_filter('woocommerce_cart_id', array($this, 'modify_cart_item_key'), 10, 5);
    }

    /**
     * Add limited edition number input field
     */
    public function add_limited_edition_number_field()
    {
        global $product;

        if (!$product) {
            return;
        }

        $pro_id = $product->get_id();
        $is_limited = get_post_meta($pro_id, '_woo_limit_status', true);

        if ($is_limited !== 'yes') {
            return;
        }

        $start = get_post_meta($pro_id, '_woo_limit_start_value', true);
        $end = get_post_meta($pro_id, '_woo_limit_end_value', true);

        if (!$start || !$end) {
            return;
        }

        // Get available numbers
        $available_numbers = limitedNosAvailable($pro_id);

        // Get admin settings for label
        $limit_label = IJWLP_Options::get_setting('limitlabel', __('Limited Edition Number', 'woolimited'));

        // Get stock quantity for the main product
        $stock_quantity = $product->get_stock_quantity();

        // Get stock quantities for variations (if product is variable)
        $variation_quantities = [];
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                $variation_quantities[$variation_id] = $variation ? intval($variation->get_stock_quantity()) : 0;
            }
        }

        // Reduce quantities by anything already in the user's cart
        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $cart_product_id = isset($cart_item['product_id']) ? intval($cart_item['product_id']) : 0;
                $cart_variation_id = isset($cart_item['variation_id']) ? intval($cart_item['variation_id']) : 0;
                $cart_qty = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 0;

                if ($product->is_type('variable')) {
                    // If this cart item is a variation of the current parent product, reduce that variation's stock
                    if ($cart_variation_id > 0) {
                        $cart_variation_product = wc_get_product($cart_variation_id);
                        $parent_id = $cart_variation_product ? intval($cart_variation_product->get_parent_id()) : 0;
                        if ($parent_id === $pro_id) {
                            if (isset($variation_quantities[$cart_variation_id])) {
                                $variation_quantities[$cart_variation_id] = max(0, $variation_quantities[$cart_variation_id] - $cart_qty);
                            }
                        }
                    } else {
                        // In rare cases a parent product might be added directly; reduce parent stock
                        if ($cart_product_id === $pro_id) {
                            $stock_quantity = max(0, intval($stock_quantity) - $cart_qty);
                        }
                    }
                } else {
                    // Simple (non-variable) product: reduce stock when product matches
                    if ($cart_product_id === $pro_id) {
                        $stock_quantity = max(0, intval($stock_quantity) - $cart_qty);
                    }
                }
            }
        }

        // Prepare variation quantities in a JSON format for hidden field
        $variation_quantities_json = !empty($variation_quantities) ? wp_json_encode($variation_quantities) : '[]';

?>
        <div class="woo-limit-selection-error" style="display:none"></div>
        <div class="woo-limit-product woo-limit-field-wrapper woo-limit-product-item-wrapper" data-start="<?php echo esc_attr($start); ?>" data-end="<?php echo esc_attr($end); ?>" data-product-id="<?php echo esc_attr($pro_id); ?>">
            <p class="woo-limit-field-label">
                <?php echo esc_html($limit_label); ?>
            </p>
            <span class="woo-number-range">
                <?php esc_html_e('Enter a number between: ', 'woolimited'); ?>
                <?php echo esc_html($start); ?> - <?php echo esc_html($end); ?>
            </span>

            <input type="hidden" name="woo-limit-stock-quantity" class="woo-limit-stock-quantity" value="<?php echo esc_attr($stock_quantity); ?>" />
            <input type="hidden" name="woo-limit-variation-quantities" class="woo-limit-variation-quantities" value="<?php echo esc_attr($variation_quantities_json); ?>" />

            <div class="woo-limit-input-group">
                <input
                    type="number"
                    id="woo-limit"
                    name="woo-limit"
                    class="woo-limit"
                    value=""
                    min="<?php echo esc_attr($start); ?>"
                    max="<?php echo esc_attr($end); ?>"
                    required />
            </div>

            <input
                type="hidden"
                name="woo-limit-available"
                class="woo-limit-available-numbers"
                value="<?php echo esc_attr($available_numbers); ?>" />
            <div class="woo-limit-message" style="display: none;"></div>
        </div>
<?php
    }

    /**
     * Validate limited edition number before adding to cart
     */
    public function validate_limited_edition_number($passed, $pro_id, $quantity)
    {
        // Skip validation if this is an AJAX call (validation happens in AJAX handler)
        if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'ijwlp_add_to_cart') {
            return $passed;
        }


        // For variable products, check parent product
        $product = wc_get_product($pro_id);
        $check_pro_id = $pro_id;

        if ($product && $product->is_type('variation')) {
            $check_pro_id = $product->get_parent_id();
        }

        $is_limited = get_post_meta($check_pro_id, '_woo_limit_status', true);

        if ($is_limited !== 'yes') {
            return $passed;
        }

        // Check if limited edition number is provided
        if (!isset($_POST['woo-limit']) || empty($_POST['woo-limit'])) {
            wc_add_notice(
                __('Please enter a Limited Edition Number.', 'woolimited'),
                'error'
            );
            return false;
        }

        $limited_number = sanitize_text_field($_POST['woo-limit']);

        // Validate number is within range (use parent product ID for variable products)
        $start = get_post_meta($check_pro_id, '_woo_limit_start_value', true);
        $end = get_post_meta($check_pro_id, '_woo_limit_end_value', true);

        if (!$start || !$end) {
            wc_add_notice(
                __('Invalid product configuration.', 'woolimited'),
                'error'
            );
            return false;
        }

        // Clean the number (remove leading zeros for validation, but preserve original)
        $clean_number = intval($limited_number);

        if ($clean_number < intval($start) || $clean_number > intval($end)) {
            wc_add_notice(
                sprintf(
                    __('Limited Edition Number must be between %s and %s.', 'woolimited'),
                    $start,
                    $end
                ),
                'error'
            );
            return false;
        }

        // Check if number is available (use parent product ID for variable products)
        $available_numbers = limitedNosAvailable($check_pro_id);
        $available_array = array_map('trim', explode(',', $available_numbers));

        // Check both original and cleaned number
        if (!in_array($limited_number, $available_array) && !in_array($clean_number, $available_array)) {
            wc_add_notice(
                __('This Limited Edition Number is not available.', 'woolimited'),
                'error'
            );
            return false;
        }

        return $passed;
    }

    /**
     * Add limited edition number to cart item data
     * Store as a normalized string for consistent storage (comma-separated if multiple)
     */
    public function add_limited_edition_to_cart_item($cart_item_data, $pro_id, $variation_id)
    {
        if (isset($_POST['woo-limit']) && $_POST['woo-limit'] !== '') {
            $limited_number = sanitize_text_field($_POST['woo-limit']);

            // Normalize to string for storage (keeps consistency across cart/session/meta)
            if (class_exists('IJWLP_Frontend_Common') && method_exists('IJWLP_Frontend_Common', 'normalize_limited_number_for_storage')) {
                $cart_item_data['woo_limit'] = IJWLP_Frontend_Common::normalize_limited_number_for_storage($limited_number);
            } else {
                $cart_item_data['woo_limit'] = (string) $limited_number;
            }

            // Store the actual product ID used (for variable products, use variation ID)
            $actual_pro_id = $variation_id > 0 ? $variation_id : $pro_id;
            $cart_item_data['woo_limit_pro_id'] = $actual_pro_id;
        }

        return $cart_item_data;
    }

    /**
     * Modify cart item key to group by variation ID (for variations) or product ID (for non-variations)
     * This applies to ALL products. Limited edition numbers are NOT included in the key,
     * so items with the same variation_id/product_id will be grouped together regardless of edition number.
     */
    public function modify_cart_item_key($cart_id, $product_id, $variation_id = 0, $variation = array(), $cart_item_data = array())
    {
        // Build cart ID based on grouping rules:
        // - For variation products: group by variation_id only
        // - For non-variation products: group by product_id only
        if ($variation_id > 0) {
            // Variation product: group by variation ID
            $group_key = $variation_id;
        } else {
            // Non-variation product: group by product ID
            $group_key = $product_id;
        }

        // Note: We don't include limited edition numbers in the key so items with same variation/product group together
        // Limited edition numbers will be stored as a normalized string in cart item data
        return md5($group_key);
    }
}
