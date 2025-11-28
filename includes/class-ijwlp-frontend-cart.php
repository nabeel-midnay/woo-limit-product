<?php

/**
 * WooCommerce Limited Product Plugin - Frontend Cart Page Class
 * 
 * Handles cart page functionality for limited edition products
 * 
 * @package WooCommerce Limited Product
 * @version 1.0.0
 */

if (!defined('ABSPATH'))
    exit;

class IJWLP_Frontend_Cart
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // Display limited edition number input field in cart - use cart item name action
        add_action('woocommerce_after_cart_item_name', array($this, 'append_limited_edition_input_to_name'), 99, 2);

        // Update limited edition number when changed in cart
        // Validate quantities on cart update (run before number updates)
        add_filter('woocommerce_update_cart_action_cart_updated', array($this, 'validate_cart_quantities_on_update'), 9, 1);
        add_filter('woocommerce_update_cart_action_cart_updated', array($this, 'update_cart_item_limited_number'), 10, 1);

        // Merge limited edition numbers when adding to existing cart item (run first)
        add_action('woocommerce_add_to_cart', array($this, 'merge_limited_edition_on_add'), 5, 6);

        // Group identical cart items (same product/variation) on cart display AND checkout
        // This ensures grouping happens whether user visits cart or goes directly to checkout
        add_action('woocommerce_before_cart', array($this, 'group_similar_cart_items'), 10);
        add_action('woocommerce_before_checkout_form', array($this, 'group_similar_cart_items'), 10);

        // Block limited edition number when item is added to cart (run after merge)
        add_action('woocommerce_add_to_cart', array($this, 'block_limited_edition_number'), 15, 6);

        // Remove limited edition numbers from database when item is removed from cart
        add_action('woocommerce_remove_cart_item', array($this, 'remove_limited_edition_from_database'), 10, 2);
        add_action('woocommerce_cart_item_removed', array($this, 'remove_limited_edition_from_database'), 10, 2);

        // Clear cart on logout if there are limited edition products in the cart
        add_action('wp_logout', array($this, 'maybe_clear_cart_on_logout'), 10);

        add_action('wp_footer', array($this, 'remove_modal'), 10);
    }

    /**
     * Validate cart quantities on cart update action.
     * Ensures total quantity per parent limited product does not exceed configured max.
     * Runs server-side (non-AJAX) when customer updates cart quantities.
     *
     * @param mixed $cart_updated
     * @return mixed
     */
    public function validate_cart_quantities_on_update($cart_updated)
    {
        $cart = WC()->cart;
        if (!$cart) {
            return $cart_updated;
        }

        $cart_items = $cart->get_cart();
        if (empty($cart_items) || !is_array($cart_items)) {
            return $cart_updated;
        }

        // Build map of parent_product_id => array of keys
        $parents = array();
        foreach ($cart_items as $key => $item) {
            $actual_pro_id = isset($item['woo_limit_pro_id']) ? $item['woo_limit_pro_id'] : (isset($item['variation_id']) && $item['variation_id'] > 0 ? $item['variation_id'] : $item['product_id']);
            $product = wc_get_product($actual_pro_id);
            $parent_product_id = $actual_pro_id;
            if ($product && $product->is_type('variation')) {
                $parent_product_id = $product->get_parent_id();
            }

            if (!isset($parents[$parent_product_id])) {
                $parents[$parent_product_id] = array();
            }
            $parents[$parent_product_id][] = $key;
        }

        // For each parent product that has a max quantity configured, validate totals
        foreach ($parents as $parent_id => $keys) {
            $is_limited = get_post_meta($parent_id, '_woo_limit_status', true);
            if ($is_limited !== 'yes') {
                continue;
            }

            $max_quantity = get_post_meta($parent_id, '_woo_limit_max_quantity', true);
            if (empty($max_quantity)) {
                continue;
            }

            // Sum quantities for this parent across all cart rows
            $total_qty = 0;
            foreach ($keys as $k) {
                $total_qty += isset($cart->cart_contents[$k]['quantity']) ? intval($cart->cart_contents[$k]['quantity']) : 0;
            }

            if ($total_qty <= intval($max_quantity)) {
                continue;
            }

            // Too many items in cart for this product: reduce quantities to respect max.
            $excess = $total_qty - intval($max_quantity);

            // Reduce quantities starting from the last cart row for this parent
            $reversed = array_reverse($keys);
            foreach ($reversed as $k) {
                if ($excess <= 0) {
                    break;
                }

                $row_qty = isset($cart->cart_contents[$k]['quantity']) ? intval($cart->cart_contents[$k]['quantity']) : 0;
                if ($row_qty <= 0) {
                    continue;
                }

                $reduce = min($row_qty, $excess);
                $new_qty = $row_qty - $reduce;

                if ($new_qty <= 0) {
                    // Remove the row entirely
                    $cart->set_quantity($k, 0, true);
                } else {
                    $cart->set_quantity($k, $new_qty, true);
                }

                $excess -= $reduce;
                $cart_updated = true;
            }

            // Add a friendly notice explaining the adjustment
            if ($excess <= 0) {
                $message = sprintf(__('Maximum quantity reached for this product. Cart adjusted to maximum of %d.', 'woolimited'), intval($max_quantity));
            } else {
                $message = sprintf(__('Maximum quantity reached for this product. Some items could not be added and were removed to respect limit of %d.', 'woolimited'), intval($max_quantity));
            }

            wc_add_notice($message, 'notice');
        }

        return $cart_updated;
    }

    /**
     * Group identical cart items (same product_id and variation_id).
     * Merges quantities and merges any limited edition numbers into the kept cart item.
     */
    public function group_similar_cart_items()
    {
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $cart_contents = $cart->get_cart();
        if (empty($cart_contents) || !is_array($cart_contents)) {
            return;
        }

        $map = array();

        foreach ($cart_contents as $cart_item_key => $cart_item) {
            $product_id = isset($cart_item['product_id']) ? intval($cart_item['product_id']) : 0;
            $variation_id = isset($cart_item['variation_id']) ? intval($cart_item['variation_id']) : 0;
            $map_key = $product_id . '|' . $variation_id;

            if (!isset($map[$map_key])) {
                $map[$map_key] = $cart_item_key;
                continue;
            }

            // Duplicate found - merge into existing
            $existing_key = $map[$map_key];
            $existing_item = isset($cart->cart_contents[$existing_key]) ? $cart->cart_contents[$existing_key] : null;
            if (!$existing_item) {
                continue;
            }

            // Sum quantities
            $existing_qty = isset($existing_item['quantity']) ? intval($existing_item['quantity']) : 0;
            $current_qty = isset($cart_item['quantity']) ? intval($cart_item['quantity']) : 0;
            $existing_item['quantity'] = $existing_qty + $current_qty;

            // Merge limited edition numbers if present
            $existing_nums = isset($existing_item['woo_limit']) ? (is_array($existing_item['woo_limit']) ? $existing_item['woo_limit'] : array($existing_item['woo_limit'])) : array();
            $current_nums = isset($cart_item['woo_limit']) ? (is_array($cart_item['woo_limit']) ? $cart_item['woo_limit'] : array($cart_item['woo_limit'])) : array();
            $combined_nums = array_values(array_unique(array_merge($existing_nums, $current_nums)));

            if (!empty($combined_nums)) {
                $existing_item['woo_limit'] = $combined_nums;
            } else {
                if (isset($existing_item['woo_limit'])) {
                    unset($existing_item['woo_limit']);
                }
            }

            // Update cart contents with merged item
            $cart->cart_contents[$existing_key] = $existing_item;

            // Update DB record for existing item (merge limited numbers)
            $actual_pro_id = isset($existing_item['woo_limit_pro_id']) ? $existing_item['woo_limit_pro_id'] : ($existing_item['variation_id'] > 0 ? $existing_item['variation_id'] : $existing_item['product_id']);
            $product = wc_get_product($actual_pro_id);
            $parent_product_id = $actual_pro_id;
            if ($product && $product->is_type('variation')) {
                $parent_product_id = $product->get_parent_id();
            }

            $old_numbers = $existing_nums;
            // Use existing helper to update/insert merged numbers for the kept cart key
            if (!empty($combined_nums)) {
                $this->update_limited_edition_in_database($existing_key, $old_numbers, $combined_nums, $parent_product_id, $actual_pro_id);
            }

            // Remove the DB record for the removed cart item (if any)
            $this->remove_limited_edition_from_database($cart_item_key, $cart_item);

            // Remove duplicate cart row
            unset($cart->cart_contents[$cart_item_key]);
        }

        // Recalculate totals and ensure cart state saved
        $cart->calculate_totals();
        if (method_exists($cart, 'maybe_set_cart_cookies')) {
            $cart->maybe_set_cart_cookies();
        }
    }

    /**
     * Append limited edition input to cart item name
     */
    public function append_limited_edition_input_to_name($cart_item, $cart_item_key)
    {

        // Only append if this cart item has limited edition number(s)
        if (!isset($cart_item['woo_limit']) || empty($cart_item['woo_limit'])) {
            return;
        }

        // Convert to array if it's a single value (backward compatibility)
        $limited_numbers = is_array($cart_item['woo_limit']) ? $cart_item['woo_limit'] : array($cart_item['woo_limit']);

        if (count($limited_numbers) === 1 && strpos($limited_numbers[0], ',') !== false) {
            $limited_numbers = array_map('trim', explode(',', $limited_numbers[0]));
        }

        // Get product ID
        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'];
        $actual_pro_id = isset($cart_item['woo_limit_pro_id']) ? $cart_item['woo_limit_pro_id'] : ($variation_id > 0 ? $variation_id : $product_id);

        // Get parent product ID for variable products
        $product = wc_get_product($actual_pro_id);
        $parent_product_id = $actual_pro_id;

        if ($product && $product->is_type('variation')) {
            $parent_product_id = $product->get_parent_id();
        }

        // Check if product is limited edition
        $is_limited = get_post_meta($parent_product_id, '_woo_limit_status', true);
        if ($is_limited !== 'yes') {
            return;
        }

        // Get available numbers
        $available_numbers = limitedNosAvailable($parent_product_id);
        $available_count = limitedNosAvailableCount($parent_product_id);

        // Get start and end values for range display
        $start = get_post_meta($parent_product_id, '_woo_limit_start_value', true);
        $end = get_post_meta($parent_product_id, '_woo_limit_end_value', true);

        // Get admin settings for label
        $limit_label_cart = IJWLP_Options::get_setting('limitlabelcart', __('Limited Edition Number(s)', 'woolimited'));

        // Output input fields after product name - one for each limited edition number
?>
        <?php $max_qty = get_post_meta($parent_product_id, '_woo_limit_max_quantity', true); ?>
        <div class="woo-limit-field-wrapper woo-limit-cart-item-wrapper" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>" data-product-id="<?php echo esc_attr($parent_product_id); ?>" data-start="<?php echo esc_attr($start); ?>" data-end="<?php echo esc_attr($end); ?>" <?php if (!empty($max_qty)) {
                                                                                                                                                                                                                                                                                                echo ' data-max-quantity="' . esc_attr($max_qty) . '"';
                                                                                                                                                                                                                                                                                            } ?>>
            <p class="woo-limit-field-label">
                <?php echo esc_html($limit_label_cart); ?>
            </p>
            <?php if ($start && $end): ?>
                <p class="woo-limit-range-info">
                    <?php esc_html_e('Enter a number between: ', 'woolimited'); ?>
                    <?php echo esc_html($start); ?> - <?php echo esc_html($end); ?>
                </p>
            <?php endif; ?>
            <?php foreach ($limited_numbers as $index => $limited_number): ?>
                <div class="woo-limit-cart-item">
                    <input
                        type="number"
                        id="woo-limit-cart-<?php echo esc_attr($cart_item_key); ?>-<?php echo esc_attr($index); ?>"
                        name="woo_limit[<?php echo esc_attr($cart_item_key); ?>][]"
                        class="woo-limit"
                        value="<?php echo esc_attr($limited_number); ?>"
                        data-cart-key="<?php echo esc_attr($cart_item_key); ?>"
                        data-index="<?php echo esc_attr($index); ?>"
                        data-old-value="<?php echo esc_attr($limited_number); ?>"
                        placeholder="<?php esc_attr_e('Enter edition number', 'woolimited'); ?>"
                        min="<?php echo esc_attr($start); ?>"
                        max="<?php echo esc_attr($end); ?>" />
                </div>
            <?php endforeach; ?>
            <input
                type="hidden"
                name="woo-limit-available[<?php echo esc_attr($cart_item_key); ?>]"
                class="woo-limit-available-numbers"
                value="<?php echo esc_attr($available_numbers); ?>" />
            <div class="woo-limit-message" style="display: none;"></div>
        </div>
    <?php
    }

    /**
     * Update limited edition number when changed in cart
     * @param mixed $cart_updated - Can be false for AJAX calls or existing cart_updated value
     * @return mixed - Returns errors array if AJAX call, otherwise cart_updated boolean
     */
    public function update_cart_item_limited_number($cart_updated)
    {
        if (!isset($_POST['woo_limit']) || !is_array($_POST['woo_limit'])) {
            return $cart_updated;
        }

        $cart = WC()->cart;
        if (!$cart) {
            return $cart_updated;
        }

        $errors = array();
        $is_ajax = ($cart_updated === false);

        foreach ($_POST['woo_limit'] as $cart_item_key => $new_numbers) {
            // Handle array of numbers
            if (!is_array($new_numbers)) {
                $new_numbers = array($new_numbers);
            }

            $new_numbers = array_map('sanitize_text_field', $new_numbers);
            $cart_item = $cart->get_cart_item($cart_item_key);

            if (!$cart_item) {
                continue;
            }

            // Get existing numbers
            $old_numbers = isset($cart_item['woo_limit']) ?
                (is_array($cart_item['woo_limit']) ? $cart_item['woo_limit'] : array($cart_item['woo_limit'])) :
                array();

            // Check if numbers actually changed
            if ($new_numbers === $old_numbers) {
                continue;
            }

            // Validate new number
            $actual_pro_id = isset($cart_item['woo_limit_pro_id']) ? $cart_item['woo_limit_pro_id'] : ($cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id']);

            $product = wc_get_product($actual_pro_id);
            $parent_product_id = $actual_pro_id;

            if ($product && $product->is_type('variation')) {
                $parent_product_id = $product->get_parent_id();
            }

            // Check if product is limited edition
            $is_limited = get_post_meta($parent_product_id, '_woo_limit_status', true);
            if ($is_limited !== 'yes') {
                continue;
            }

            // Validate each number is within range and available
            $start = get_post_meta($parent_product_id, '_woo_limit_start_value', true);
            $end = get_post_meta($parent_product_id, '_woo_limit_end_value', true);
            $available_numbers = limitedNosAvailable($parent_product_id);
            $available_array = array_map('trim', explode(',', $available_numbers));

            // Also consider numbers that are blocked for this current cart or current user as available
            global $wpdb, $table_prefix;
            $table = $table_prefix . 'woo_limit';

            $user_numbers = array();
            $user_id = get_current_user_id();

            // Numbers blocked for this user_id
            if ($user_id > 0) {
                $user_rows = $wpdb->get_col($wpdb->prepare(
                    "SELECT limit_no FROM $table WHERE parent_product_id = %s AND status = 'block' AND user_id = %s",
                    $parent_product_id,
                    $user_id
                ));
                if ($user_rows) {
                    foreach ($user_rows as $r) {
                        $r = trim($r);
                        if ($r === '') continue;
                        $parts = array_map('trim', explode(',', $r));
                        $user_numbers = array_merge($user_numbers, $parts);
                    }
                }
            }

            // Also include numbers blocked by matching cart_key(s) for the current cart (useful for guests)
            $cart = WC()->cart;
            if ($cart) {
                $cart_items = $cart->get_cart();
                if (!empty($cart_items)) {
                    $cart_keys = array_keys($cart_items);
                    $placeholders = implode(',', array_fill(0, count($cart_keys), '%s'));
                    $sql = $wpdb->prepare(
                        "SELECT limit_no FROM $table WHERE parent_product_id = %d AND status = 'block' AND cart_key IN ($placeholders)",
                        array_merge(array($parent_product_id), $cart_keys)
                    );
                    $guest_rows = $wpdb->get_col($sql);
                    if ($guest_rows) {
                        foreach ($guest_rows as $r) {
                            $r = trim($r);
                            if ($r === '') continue;
                            $parts = array_map('trim', explode(',', $r));
                            $user_numbers = array_merge($user_numbers, $parts);
                        }
                    }
                }
            }

            $user_numbers = array_filter(array_unique($user_numbers), 'strlen');

            $item_has_error = false;
            foreach ($new_numbers as $new_number) {
                // Validate number is within range
                if ($start && $end) {
                    $clean_number = intval($new_number);
                    if ($clean_number < intval($start) || $clean_number > intval($end)) {
                        $error_message = sprintf(
                            __('Limited Edition Number %s must be between %s and %s.', 'woolimited'),
                            $new_number,
                            $start,
                            $end
                        );

                        if ($is_ajax) {
                            $errors[$cart_item_key] = $error_message;
                        } else {
                            wc_add_notice($error_message, 'error');
                        }
                        $item_has_error = true;
                        break;
                    }
                }

                // Check if number is available
                if (!in_array($new_number, $available_array)) {
                    // Also check cleaned number
                    $clean_number = intval($new_number);

                    // If this number is already in this user's blocked set (or current cart), or is part of the current item's old numbers, treat as available
                    $in_user = in_array($new_number, $user_numbers) || in_array($clean_number, $user_numbers);
                    $in_old = in_array($new_number, $old_numbers) || in_array($clean_number, $old_numbers);

                    if ($in_user || $in_old) {
                        // allowed (number is already reserved by this cart/user)
                        continue;
                    }

                    if (!in_array($clean_number, $available_array)) {
                        $error_message = sprintf(__('Limited Edition Number %s is not available.', 'woolimited'), $new_number);

                        if ($is_ajax) {
                            $errors[$cart_item_key] = $error_message;
                        } else {
                            wc_add_notice($error_message, 'error');
                        }
                        $item_has_error = true;
                        break;
                    }
                }
            }

            // Only update if no errors
            if (!$item_has_error) {
                // Update cart item data
                $cart_item['woo_limit'] = $new_numbers;
                $cart->cart_contents[$cart_item_key] = $cart_item;

                // Update database - unblock old numbers, block new numbers
                $this->update_limited_edition_in_database($cart_item_key, $old_numbers, $new_numbers, $parent_product_id, $actual_pro_id);

                $cart_updated = true;
            }
        }

        // Return errors array for AJAX, or cart_updated boolean for regular form submission
        if ($is_ajax) {
            return $errors;
        }

        return $cart_updated;
    }

    /**
     * Update limited edition numbers in database
     * Stores all numbers as comma-separated in a single record
     */
    private function update_limited_edition_in_database($cart_item_key, $old_numbers, $new_numbers, $parent_product_id, $actual_pro_id)
    {
        global $wpdb, $table_prefix;
        $table = $table_prefix . 'woo_limit';

        // Ensure arrays
        $old_numbers = is_array($old_numbers) ? $old_numbers : (!empty($old_numbers) ? array($old_numbers) : array());
        $new_numbers = is_array($new_numbers) ? $new_numbers : array($new_numbers);

        // Get product type and user ID
        $product = wc_get_product($actual_pro_id);
        $product_type = $product ? $product->get_type() : 'simple';
        $user_id = get_current_user_id();

        // Store new numbers as comma-separated string
        $limit_no_string = implode(',', $new_numbers);

        // Check if record exists for this cart_key
        $existing_record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE cart_key = %s 
            AND parent_product_id = %s
            AND status = 'block'",
            $cart_item_key,
            $parent_product_id
        ));

        if ($existing_record) {
            // Update existing record with new comma-separated numbers
            $wpdb->update(
                $table,
                array(
                    'limit_no' => $limit_no_string,
                    'time' => current_time('mysql'),
                ),
                array(
                    'id' => $existing_record->id
                ),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            // Check if any of the new numbers are already blocked or ordered
            $numbers_to_check = $new_numbers;
            $blocked_numbers = array();

            foreach ($numbers_to_check as $number) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, status, limit_no FROM $table 
                    WHERE parent_product_id = %d 
                    AND (status = 'block' OR status = 'ordered')
                    AND (limit_no = %s OR limit_no LIKE %s OR limit_no LIKE %s OR limit_no LIKE %s)",
                    $parent_product_id,
                    $number,
                    $number . ',%',
                    '%,' . $number,
                    '%,' . $number . ',%'
                ));

                if ($existing) {
                    $blocked_numbers[] = $number;
                }
            }

            // Only insert if none of the numbers are already blocked/ordered
            if (empty($blocked_numbers)) {
                $wpdb->insert(
                    $table,
                    array(
                        'cart_key' => $cart_item_key,
                        'user_id' => $user_id,
                        'parent_product_id' => $parent_product_id,
                        'product_id' => $actual_pro_id,
                        'product_type' => $product_type,
                        'limit_no' => $limit_no_string,
                        'status' => 'block',
                        'time' => current_time('mysql'),
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
            }
        }
    }

    /**
     * Merge limited edition numbers when adding to existing cart item
     */
    public function merge_limited_edition_on_add($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        // Get the cart item from cart
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $cart_item = $cart->get_cart_item($cart_item_key);
        if (!$cart_item) {
            return;
        }

        // Check if this is a limited edition product
        if (!isset($cart_item['woo_limit']) || empty($cart_item['woo_limit'])) {
            return;
        }

        // Convert to array if needed
        $existing_numbers = is_array($cart_item['woo_limit']) ? $cart_item['woo_limit'] : array($cart_item['woo_limit']);

        // Get the new number from cart_item_data
        $new_number = isset($cart_item_data['woo_limit']) ?
            (is_array($cart_item_data['woo_limit']) ? $cart_item_data['woo_limit'][0] : $cart_item_data['woo_limit']) :
            null;

        if ($new_number && !in_array($new_number, $existing_numbers)) {
            // Add new number to the array
            $existing_numbers[] = $new_number;
            $cart_item['woo_limit'] = $existing_numbers;
            $cart->cart_contents[$cart_item_key] = $cart_item;
        }
    }

    /**
     * Block limited edition number in database when item is added to cart
     */
    public function block_limited_edition_number($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        // Get the cart item from cart to access all data including session data
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $cart_item = $cart->get_cart_item($cart_item_key);
        if (!$cart_item) {
            return;
        }

        // Check if limited edition number is in cart item data
        if (!isset($cart_item['woo_limit']) || empty($cart_item['woo_limit'])) {
            return;
        }

        // Handle array of limited edition numbers
        $limited_numbers = is_array($cart_item['woo_limit']) ? $cart_item['woo_limit'] : array($cart_item['woo_limit']);
        $actual_pro_id = isset($cart_item['woo_limit_pro_id']) ? $cart_item['woo_limit_pro_id'] : ($variation_id > 0 ? $variation_id : $product_id);

        // Get parent product ID for variable products
        $product = wc_get_product($actual_pro_id);
        $parent_product_id = $actual_pro_id;

        if ($product && $product->is_type('variation')) {
            $parent_product_id = $product->get_parent_id();
        }

        // Check if product is limited edition
        $is_limited = get_post_meta($parent_product_id, '_woo_limit_status', true);
        if ($is_limited !== 'yes') {
            return;
        }

        global $wpdb, $table_prefix;
        $table = $table_prefix . 'woo_limit';

        // Get current user ID
        $user_id = get_current_user_id();
        if (!$user_id) {
            $user_id = 0; // Guest user
        }

        // Get product type
        $product_type = $product ? $product->get_type() : 'simple';

        // Store all numbers as comma-separated string in a single record
        $limit_no_string = implode(',', $limited_numbers);

        // Check if a record already exists for this cart_key
        $existing_record = $wpdb->get_row($wpdb->prepare(
            "SELECT id, limit_no, status FROM $table 
            WHERE cart_key = %s 
            AND parent_product_id = %s 
            AND status = 'block'",
            $cart_item_key,
            $parent_product_id
        ));

        if ($existing_record) {
            // Update existing record with all numbers (comma-separated)
            $wpdb->update(
                $table,
                array(
                    'limit_no' => $limit_no_string,
                    'time' => current_time('mysql'),
                ),
                array(
                    'id' => $existing_record->id
                ),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            // Check if any of these numbers are already blocked or ordered by another cart
            $numbers_to_check = $limited_numbers;
            $blocked_numbers = array();

            foreach ($numbers_to_check as $number) {
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, status, limit_no FROM $table 
                    WHERE parent_product_id = %d 
                    AND (status = 'block' OR status = 'ordered')
                    AND (limit_no = %s OR limit_no LIKE %s OR limit_no LIKE %s OR limit_no LIKE %s)",
                    $parent_product_id,
                    $number,
                    $number . ',%',
                    '%,' . $number,
                    '%,' . $number . ',%'
                ));

                if ($existing) {
                    $blocked_numbers[] = $number;
                }
            }

            // Only insert if none of the numbers are already blocked/ordered
            if (empty($blocked_numbers)) {
                // Insert new record with all numbers comma-separated
                $wpdb->insert(
                    $table,
                    array(
                        'cart_key' => $cart_item_key,
                        'user_id' => $user_id,
                        'parent_product_id' => $parent_product_id,
                        'product_id' => $actual_pro_id,
                        'product_type' => $product_type,
                        'limit_no' => $limit_no_string,
                        'status' => 'block',
                        'time' => current_time('mysql'),
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
            }
        }
    }

    /**
     * Remove limited edition numbers from database when item is removed from cart
     * Handles both woocommerce_remove_cart_item and woocommerce_cart_item_removed hooks
     * 
     * Note: Always attempts deletion by cart_item_key, regardless of whether cart item data is available.
     * This ensures DB cleanup even when mini cart removal doesn't pass full cart item details.
     */
    public function remove_limited_edition_from_database($cart_item_key, $cart_or_item)
    {
        global $wpdb, $table_prefix;
        $table = $table_prefix . 'woo_limit';

        // Always attempt to delete by cart_item_key from the database
        // This ensures cleanup even when cart item data is not available (e.g., mini cart removals)
        $wpdb->delete(
            $table,
            array(
                'cart_key' => $cart_item_key,
                'status' => 'block'
            ),
            array('%s', '%s')
        );
    }

    /**
     * Clear the cart on logout if cart contains any limited edition products.
     * Removes associated blocked limited-number DB records before emptying the cart.
     */
    public function maybe_clear_cart_on_logout()
    {
        $cart = WC()->cart;
        if (!$cart) {
            return;
        }

        $cart_contents = $cart->get_cart();
        if (empty($cart_contents) || !is_array($cart_contents)) {
            return;
        }

        $has_limited = false;
        foreach ($cart_contents as $cart_item_key => $cart_item) {
            $actual_pro_id = isset($cart_item['woo_limit_pro_id']) ? $cart_item['woo_limit_pro_id'] : (isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0 ? $cart_item['variation_id'] : $cart_item['product_id']);
            $product = wc_get_product($actual_pro_id);
            $parent_product_id = $actual_pro_id;
            if ($product && $product->is_type('variation')) {
                $parent_product_id = $product->get_parent_id();
            }

            $is_limited = get_post_meta($parent_product_id, '_woo_limit_status', true);
            if ($is_limited === 'yes') {
                $has_limited = true;
                break;
            }
        }

        if ($has_limited) {
            // Remove DB records for each cart item (if present)
            foreach ($cart_contents as $cart_item_key => $cart_item) {
                $this->remove_limited_edition_from_database($cart_item_key, $cart_item);
            }

            // Empty WooCommerce cart and recalculate totals
            $cart->empty_cart();
            $cart->calculate_totals();
            if (method_exists($cart, 'maybe_set_cart_cookies')) {
                $cart->maybe_set_cart_cookies();
            }
        }
    }

    public function remove_modal()
    {
    ?>
        <div id="woo-limit-remove-modal" class="woo-limit-modal" style="display: none;">
            <div class="woo-limit-modal-content">
                <span class="woo-limit-close">&times;</span>
                <div class="woo-limit-modal-message">
                    <p><?php esc_html_e('Are you sure you want to remove this item from the cart?', 'woolimited'); ?></p>
                </div>
                <div class="woo-limit-modal-list-container" style="margin-top:10px;">
                    <!-- JS will inject list with checkboxes here -->
                </div>
                <div style="margin-top:12px;">
                    <button id="woo-limit-confirm-remove" class="button"><?php esc_html_e('Yes, Remove', 'woolimited'); ?></button>
                    <button id="woo-limit-cancel-remove" class="button"><?php esc_html_e('Cancel', 'woolimited'); ?></button>
                </div>
            </div>
        </div>
<?php
    }
}
