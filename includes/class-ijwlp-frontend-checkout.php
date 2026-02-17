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

        add_action('woocommerce_checkout_after_customer_details', array($this, 'custom_checkout_order_summary'), 5);

        add_action('woocommerce_after_checkout_shipping_form', array($this, 'add_delivery_preference_field'), 10, 1);
        add_action('woocommerce_checkout_process', array($this, 'validate_delivery_preference_field'), 10, 1);
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_delivery_preference_field'), 10, 1);

        add_action('wp_ajax_get_cart_totals', array($this, 'ajax_get_cart_totals'));
        add_action('wp_ajax_nopriv_get_cart_totals', array($this, 'ajax_get_cart_totals'));
    }

    /**
     * Add limited edition number to cart/checkout item display
     * This filter is used by WooCommerce to show item meta on cart and checkout pages.
     *
     * @param array $item_data Formatted item data array
     * @param array $cart_item The cart item array
     * @return array
     */
    public function add_limited_edition_to_item_display($item_data, $cart_item)
    {
        if (isset($cart_item['woo_limit']) && $cart_item['woo_limit'] !== '') {
            $numbers = IJWLP_Frontend_Common::normalize_limited_number_for_processing($cart_item['woo_limit']);
            if (!empty($numbers)) {
                $display = esc_html(implode(', ', $numbers));
                $item_data[] = array(
                    'key' => esc_html__('Limited Edition Number', 'woo-limit-product'),
                    'value' => $display,
                );
            }
        }
        return $item_data;
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
                $wpdb->update(
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

    /**
     * Custom Order Summary for Checkout Page
     * Displays product details with limited edition numbers
     */
    public function custom_checkout_order_summary()
    {
        if (!is_checkout()) {
            return;
        }

        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }

        $cart_items = $cart->get_cart();
        $totals = $this->calculate_cart_totals_with_tax();

        ?>
        <div class="checkout-order-summary-container">
            <div id="custom-order-summary" class="custom-order-summary-wrapper">
                <div class="order-summary-container">
                    <a href="<?php echo esc_url(wc_get_cart_url()); ?>"
                        class="view-cart-btn"><?php echo esc_html__('View cart', 'woo-limit-product'); ?></a>
                    <div class="order-summary-wrap">
                        <div class="order-summary-header">
                            <h3><?php echo esc_html__('ORDER SUMMARY', 'woo-limit-product'); ?></h3>
                        </div>

                        <div class="order-summary-totals">
                            <div class="summary-line">
                                <span class="label">Delivery:</span>
                                <span class="value"><?php echo $totals['shipping_formatted']; ?></span>
                            </div>
                            <?php if ($totals['discount'] > 0): ?>
                                <div class="summary-line">
                                    <span class="label">Coupon:</span>
                                    <span class="value"><?php echo wc_price($totals['discount']); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="summary-line total-line">
                                <span class="label">
                                    Total
                                    <?php if ($totals['tax_info'] && $totals['tax_info']['tax_amount'] > 0): ?>
                                        <small class="summary-line tax-line">
                                            <?php echo $totals['tax_display_suffix']; ?>
                                        </small>
                                    <?php endif; ?>
                                    :
                                </span>
                                <span class="value"><?php echo wc_price($totals['total']); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="order-summary-content">
                        <div class="summary-line items-count">
                            <span class="label"><?php echo $totals['item_count']; ?>
                                Item<?php echo $totals['item_count'] > 1 ? 's' : ''; ?></span>
                            <span class="value">Total: <?php echo wc_price($totals['total']); ?></span>
                        </div>
                        <?php foreach ($cart_items as $ci_key => $ci_item):
                            $this->render_cart_item($ci_item);
                        endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single cart item in the order summary
     * 
     * @param array $ci_item Cart item
     */
    private function render_cart_item($ci_item)
    {
        $product = isset($ci_item['data']) ? $ci_item['data'] : null;
        if (!is_object($product)) {
            return;
        }

        $product_name = $product->get_name();
        $image = wp_kses_post($product->get_image('thumbnail'));
        $quantity = isset($ci_item['quantity']) ? (int) $ci_item['quantity'] : 1;
        $product_id = isset($ci_item['product_id']) ? $ci_item['product_id'] : $product->get_id();

        // Get limited edition numbers
        $numbers = $this->get_limited_edition_numbers($ci_item);

        // Get limited edition range
        $limited_range = $this->get_limited_edition_range($product_id);

        ?>
        <div class="order-summary-item">
            <div class="item-image"><?php echo $image; ?></div>
            <div class="item-details">
                <div class="item-name"><?php echo esc_html($product_name); ?></div>

                <?php $this->render_product_attributes($ci_item); ?>

                <div class="item-quantity"><?php echo '&#215;' . esc_html($quantity); ?></div>

                <?php if ($limited_range): ?>
                    <div class="limited-edition-range">
                        <?php echo esc_html__('Limited Edition', 'woo-limit-product'); ?>:
                        <?php echo esc_html($limited_range); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($numbers)): ?>
                    <div class="limited-edition-display">
                        <span class="limited-edition-label"><?php echo esc_html__('Limited Edition', 'woo-limit-product'); ?></span>
                        <div class="limited-number-list">
                            <?php foreach ($numbers as $num): ?>
                                <span class="limited-number"><?php echo esc_html((string) $num); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php $this->render_backorder_notification($product); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render product attributes for variable products
     * 
     * @param array $ci_item Cart item
     */
    private function render_product_attributes($ci_item)
    {
        if (empty($ci_item['variation']) || !is_array($ci_item['variation'])) {
            return;
        }

        ?>
        <div class="item-attributes">
            <?php foreach ($ci_item['variation'] as $attr_key => $attr_value):
                if (empty($attr_value))
                    continue;

                $attr_name = wc_attribute_label(str_replace('attribute_', '', $attr_key));
                $taxonomy = str_replace('attribute_', '', $attr_key);
                
                if (taxonomy_exists($taxonomy)) {
                    $term = get_term_by('slug', $attr_value, $taxonomy);
                    if ($term && !is_wp_error($term)) {
                        $attr_value = $term->name;
                    }
                }
                ?>
                <span class="product-attribute">
                    <?php echo esc_html($attr_name); ?>: <?php echo esc_html($attr_value); ?>
                </span>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render backorder notification
     * 
     * @param WC_Product $product
     */
    private function render_backorder_notification($product)
    {
        if ($product->get_stock_status() !== 'onbackorder') {
            return;
        }

        ?>
        <div class="backorder_notification">
            <?php echo esc_html__('Available on backorder', 'woo-limit-product'); ?>
            <span class="backorder-help-icon help-icon"
                data-tooltip="<?php echo esc_attr__('Available on backorder means that this particular product/size is currently not in stock. However, it can be ordered and will be delivered as soon as available (usually 10 days).', 'woo-limit-product'); ?>">?</span>
        </div>
        <?php
    }

    /**
     * Get limited edition numbers from cart item
     * 
     * @param array $ci_item Cart item
     * @return array
     */
    private function get_limited_edition_numbers($ci_item)
    {
        $numbers = array();
        
        if (!empty($ci_item['woo_limit'])) {
            $numbers = IJWLP_Frontend_Common::normalize_limited_number_for_processing($ci_item['woo_limit']);
        } elseif (!empty($ci_item['woo_limit_display'])) {
            $numbers = IJWLP_Frontend_Common::normalize_limited_number_for_processing($ci_item['woo_limit_display']);
        }
        
        return $numbers;
    }

    /**
     * Get limited edition range for product
     * 
     * @param int $product_id
     * @return string
     */
    private function get_limited_edition_range($product_id)
    {
        $start_range = get_post_meta($product_id, '_woo_limit_start_value', true);
        $end_range = get_post_meta($product_id, '_woo_limit_end_value', true);
        
        if ($start_range !== '' && $end_range !== '') {
            return $start_range . ' - ' . $end_range;
        }
        
        return '';
    }

    /**
     * Calculate cart totals with geo-based tax
     * 
     * @return array
     */
    private function calculate_cart_totals_with_tax()
    {
        $cart = WC()->cart;
        $subtotal_with_tax = 0;
        
        // Calculate subtotal with tax
		foreach ($cart->get_cart() as $cart_item) {
			$product = $cart_item['data'];
			$line_total = $product->get_price() * $cart_item['quantity'];
			$tax_data = $this->calculate_geo_tax_for_price($line_total, $product->get_tax_class());

			$subtotal_with_tax += $tax_data ? round($tax_data['price_with_tax']) : $line_total;
		}

        // Calculate total
        $shipping_total = $cart->get_shipping_total();
        $shipping_tax_class = get_option('woocommerce_shipping_tax_class');
        $shipping_tax_data = $this->calculate_geo_tax_for_price($shipping_total, $shipping_tax_class);
        $shipping_total_with_tax = $shipping_tax_data ? $shipping_tax_data['price_with_tax'] : $shipping_total;

        $discount_total = $cart->get_discount_total();
        $total = $subtotal_with_tax + $shipping_total_with_tax - $discount_total;
        
        // Add fees
        foreach ($cart->get_fees() as $fee) {
            $total += $fee->total + $fee->tax;
        }
		
		$total = round($total);

        // Get tax information
        $tax_info = $this->get_cart_tax_info();

        // Construct custom tax display suffix: (Prefix. Tax Percentage Tax Label, Tax Amount)
        $tax_display_suffix = '';
        if ($tax_info && $tax_info['tax_amount'] > 0) {            
            // Try to get prefix from WC settings if available, but default to 'Incl.' as it's the standard for this plugin
            $wc_suffix = get_option('woocommerce_price_display_suffix');

            $prefix = $wc_suffix ? $wc_suffix : 'Incl.';

            $tax_display_suffix = sprintf(
                '(%s %s%s, %s)',
                $prefix,
                trim($tax_info['tax_percentage']),
                $tax_info['tax_label_only'] ? ' ' . $tax_info['tax_label_only'] : '',
                wc_price($tax_info['tax_amount'])
            );
        }

        return array(
            'subtotal' => $subtotal_with_tax,
            'shipping' => $shipping_total_with_tax,
            'shipping_formatted' => $shipping_total_with_tax > 0 ? wc_price($shipping_total_with_tax) : 'FREE',
            'discount' => $discount_total,
            'total' => $total,
            'total_formatted' => wc_price($total),
            'tax_info' => $tax_info,
            'tax_display_suffix' => $tax_display_suffix,
            'item_count' => $cart->get_cart_contents_count()
        );
    }

    /**
     * Get cart tax information (label with percentage)
     * 
     * @return array|false Array with 'tax_label', 'tax_amount', 'tax_percentage' or false if no tax
     */
    private function get_cart_tax_info()
    {
        $cart = WC()->cart;
        if (!$cart) {
            return false;
        }

        $total_tax = 0;
        $tax_rates = array();

        // Calculate total tax from all cart items
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            $line_total = $product->get_price() * $cart_item['quantity'];
            $tax_data = $this->calculate_geo_tax_for_price($line_total, $product->get_tax_class());
            
            if ($tax_data) {
                $total_tax += $tax_data['tax_amount'];
                if (!empty($tax_data['tax_rates'])) {
                    $tax_rates = $tax_data['tax_rates'];
                }
            }
        }

        // Add shipping tax
        $shipping_total = $cart->get_shipping_total();
        $shipping_tax_class = get_option('woocommerce_shipping_tax_class', '');
        $shipping_tax_data = $this->calculate_geo_tax_for_price($shipping_total, $shipping_tax_class);
        if ($shipping_tax_data) {
            $total_tax += $shipping_tax_data['tax_amount'];
            if (empty($tax_rates) && !empty($shipping_tax_data['tax_rates'])) {
                $tax_rates = $shipping_tax_data['tax_rates'];
            }
        }

        if ($total_tax <= 0) {
            return false;
        }

        // Get clean tax label
        $tax_label_only = $this->get_clean_tax_label($tax_rates);
        
        // Get tax percentage
        $tax_percentage = '';
        if (!empty($tax_rates)) {
            $tax_rate = reset($tax_rates);
            if (isset($tax_rate['rate'])) {
                $tax_percentage = floatval($tax_rate['rate']) . '%';
            }
        }

        return array(
            'tax_label' => $tax_label_only . ($tax_percentage ? ' ' . $tax_percentage : ''),
            'tax_label_only' => $tax_label_only,
            'tax_amount' => $total_tax,
            'tax_percentage' => $tax_percentage
        );
    }

    /**
     * Get clean tax label from tax rates
     */
    private function get_clean_tax_label($tax_rates)
    {
        if (empty($tax_rates)) {
            return 'Tax';
        }

        $tax_rate = reset($tax_rates);

        if (!empty($tax_rate['label'])) {
            $label = $tax_rate['label'];
            $label = preg_replace('/\s*\(.*?\)\s*/', '', $label);
            $label = preg_replace('/\s*\d+%?\s*/', '', $label);
            $label = trim($label);

            if (!empty($label)) {
                return $label;
            }
        }

        $country = $this->get_customer_country_for_tax();
        return $this->get_default_tax_label_by_country($country);
    }

    /**
     * Get default tax label by country
     */
    private function get_default_tax_label_by_country($country)
    {
        $default_labels = array(
            'VAT' => array('GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'IE', 'PT', 'FI', 'SE', 'DK', 'PL', 'CZ', 'HU', 'RO', 'BG', 'HR', 'GR', 'SK', 'SI', 'LT', 'LV', 'EE', 'CY', 'MT', 'LU'),
            'GST' => array('AU', 'NZ', 'IN', 'SG', 'CA'),
            'Tax' => array('US', 'JP', 'CN', 'KR', 'TW', 'HK', 'TH', 'MY', 'PH', 'ID', 'VN')
        );

        foreach ($default_labels as $label => $countries) {
            if (in_array($country, $countries)) {
                return $label;
            }
        }

        return 'Tax';
    }

    /**
     * Helper function to calculate price with geo-based tax
     * 
     * @param float $price The base price
     * @param string $tax_class The product tax class
     * @return array|false Array with 'price_with_tax', 'tax_amount', 'tax_rates' or false if no tax
     */
    private function calculate_geo_tax_for_price($price, $tax_class = '')
    {
        $country = $this->get_customer_country_for_tax();
        if (!$country) {
            return false;
        }

        $tax_rates = WC_Tax::find_rates(array(
            'country' => $country,
            'state' => '',
            'postcode' => '',
            'city' => '',
            'tax_class' => $tax_class
        ));

        if (empty($tax_rates)) {
            return false;
        }

        $taxes = WC_Tax::calc_tax($price, $tax_rates, false);
        $tax_amount = array_sum($taxes);

        return array(
            'price_with_tax' => $price + $tax_amount,
            'tax_amount' => $tax_amount,
            'tax_rates' => $tax_rates
        );
    }

    /**
     * Get customer country for tax calculation
     * Priority: Shipping address → Geolocation
     */
    private function get_customer_country_for_tax()
    {
        // Check shipping address from logged in customer
        if (is_user_logged_in() && WC()->customer) {
            $shipping_country = WC()->customer->get_shipping_country();
            if (!empty($shipping_country)) {
                return $shipping_country;
            }
        }

        // Check shipping address from session
        if (WC()->session) {
            $customer_data = WC()->session->get('customer');
            if (!empty($customer_data['shipping_country'])) {
                return $customer_data['shipping_country'];
            }
        }

        // Fallback to geolocation
        $geolocation = WC_Geolocation::geolocate_ip();
        if (!empty($geolocation['country'])) {
            return $geolocation['country'];
        }

        return WC()->countries->get_base_country();
    }

    /**
     * Add delivery preference field to checkout page
     * 
     * @param WC_Checkout $checkout
     */
    public function add_delivery_preference_field($checkout)
    {
        if (!$this->check_for_backordered_items()) {
            return;
        }

        $field_value = $checkout->get_value('delivery_preference') ?: 'partial_delivery';

        echo '<div id="delivery_preference_field">';
        echo '<h3><span class="backorder-help-text">' . __('Backorder Delivery', 'woocommerce') . '</span><span class="required" aria-hidden="true">*</span><span class="help-icon" data-tooltip="' . esc_attr(__('Available on backorder means that this particular product/size is currently not in stock. However, it can be ordered and will be delivered as soon as available (usually 10 days).', 'woocommerce')) . '">?</span></h3>';
        echo '<p class="backorder-para">' . __('Choose how to deliver items with backorders:', 'woocommerce') . '</p>';

        echo '<div class="woocommerce-input-wrapper">';

        $options = array(
            'partial_delivery' => __('Deliver available items now; backordered items later', 'woocommerce'),
            'complete_delivery' => __('Deliver everything together when all items are available', 'woocommerce')
        );

        foreach ($options as $value => $label) {
            echo '<div class="radio-option-wrapper">';
            echo '<input type="radio" class="input-radio" value="' . esc_attr($value) . '" name="delivery_preference" aria-required="true" id="delivery_preference_' . esc_attr($value) . '"' .
                ($field_value === $value ? ' checked="checked"' : '') . '>';
            echo '<label for="delivery_preference_' . esc_attr($value) . '" class="radio required_field">' . esc_html($label) . '</label>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Validate delivery preference field
     */
    public function validate_delivery_preference_field()
    {
        if (!$this->check_for_backordered_items()) {
            return;
        }

        if (empty($_POST['delivery_preference'])) {
            wc_add_notice(__('Please select a delivery preference for your backordered items.', 'woocommerce'), 'error');
        }
    }

    /**
     * Save delivery preference to order meta
     * 
     * @param int $order_id
     */
    public function save_delivery_preference_field($order_id)
    {
        if (empty($_POST['delivery_preference'])) {
            return;
        }

        $delivery_preference = sanitize_text_field($_POST['delivery_preference']);
        update_post_meta($order_id, '_delivery_preference', $delivery_preference);

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $preference_text = $delivery_preference === 'partial_delivery' ?
            __('Deliver available items now, backordered items when ready', 'woocommerce') :
            __('Wait for all items to be available before delivery', 'woocommerce');

        $order->add_order_note(sprintf(__('Customer delivery preference: %s', 'woocommerce'), $preference_text));
    }

    /**
     * Check if cart has backordered items
     * 
     * @return bool
     */
    private function check_for_backordered_items()
    {
        $cart = WC()->cart;
        if (!$cart) {
            return false;
        }

        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product && $product->is_on_backorder($cart_item['quantity'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * AJAX handler to get updated cart totals
     * Called when checkout form changes (e.g., country selection)
     * 
     * @return void
     */
    public function ajax_get_cart_totals()
    {
        // Verify nonce for security
        $nonce = isset($_POST['security']) ? $_POST['security'] : (isset($_POST['nonce']) ? $_POST['nonce'] : '');
        if (!$nonce || !wp_verify_nonce($nonce, 'ijwlp_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }

        // Update customer location if address data is provided
        $this->update_customer_location_from_post();

        // Get cart instance
        $cart = WC()->cart;
        if (!$cart) {
            wp_send_json_error(array('message' => 'Cart not available'));
            return;
        }

        // Calculate cart totals
        $cart->calculate_totals();

        // Get calculated totals
        $totals = $this->calculate_cart_totals_with_tax();

        // Prepare response
        $response = array(
            'totals' => array(
                'shipping_total' => $totals['shipping'],
                'shipping_total_formatted' => $totals['shipping_formatted'],
                'subtotal' => $totals['subtotal'],
                'total' => $totals['total'],
                'total_formatted' => $totals['total_formatted'],
                'discount_total' => $totals['discount'],
                'cart_contents_total' => $totals['subtotal'],
                'tax_info' => $totals['tax_info'],
                'tax_label' => $totals['tax_info'] ? $totals['tax_info']['tax_label'] : '',
                'tax_amount' => $totals['tax_info'] ? $totals['tax_info']['tax_amount'] : 0,
                'tax_display_suffix' => $totals['tax_display_suffix'],
            )
        );

        wp_send_json_success($response);
    }

    /**
     * Update customer location from POST data
     */
    private function update_customer_location_from_post()
    {
        if (!WC()->customer) {
            return;
        }

        $address_fields = array(
            'billing_country' => 'set_billing_country',
            'billing_state' => 'set_billing_state',
            'billing_postcode' => 'set_billing_postcode',
            'shipping_country' => 'set_shipping_country',
            'shipping_state' => 'set_shipping_state',
            'shipping_postcode' => 'set_shipping_postcode',
        );

        foreach ($address_fields as $field => $method) {
            if (isset($_POST[$field])) {
                WC()->customer->$method(sanitize_text_field($_POST[$field]));
            }
        }
    }
}