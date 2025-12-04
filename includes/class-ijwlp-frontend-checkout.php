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
    	
        add_action('woocommerce_after_checkout_billing_form', array($this, 'add_delivery_preference_field'), 10, 1);
		add_action('woocommerce_checkout_process', array($this, 'validate_delivery_preference_field'), 10, 1);
		add_action('woocommerce_checkout_update_order_meta', array($this, 'save_delivery_preference_field'), 10, 1);

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
                    'key'   => esc_html__('Limited Edition Number', 'woo-limit-product'),
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

    /**
     * Custom Order Summary for Checkout Page
     * Displays product details with limited edition numbers
     */
    public function custom_checkout_order_summary()
    {
        // Minimal checkout order summary: only HTML structure and limited edition numbers
        if (! is_checkout()) {
            return;
        }

        $cart = WC()->cart;
        if (! $cart || $cart->is_empty()) {
            return;
        }

        $cart_items = $cart->get_cart();
        $shipping_total = $cart->get_shipping_total();
        $total = $cart->get_total('raw');
        $coupons = $cart->get_coupons();

        if (!empty($coupons)) {
            $coupon_discount = $cart_items->get_discount_total();
        }
?>
        <div class="checkout-order-summary-container">
            <div id="custom-order-summary" class="custom-order-summary-wrapper">
                <div class="order-summary-container">
                    <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="view-cart-btn"><?php echo esc_html__('View cart', 'woo-limit-product'); ?></a>
                    <div class="order-summary-wrap">
                        <div class="order-summary-header">
                            <h3><?php echo esc_html__('ORDER SUMMARY', 'woo-limit-product'); ?></h3>
                        </div>

                        <div class="order-summary-totals">
                            <div class="summary-line">
                                <span class="label">Delivery:</span>
                                <span class="value"><?php echo $shipping_total > 0 ? wc_price($shipping_total) : 'FREE'; ?></span>
                            </div>
                            <?php if ($coupon_discount > 0): ?>
                                <div class="summary-line">
                                    <span class="label">Discount:</span>
                                    <span class="value"><?php echo wc_price($coupon_discount); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="summary-line total-line">
                                <span class="label">Total:</span>
                                <span class="value"><?php echo wc_price($total); ?></span>
                            </div>

                        </div>
                    </div>

                    <div class="order-summary-content">
                        <?php foreach ($cart_items as $ci_key => $ci_item) :
                            $product = isset($ci_item['data']) ? $ci_item['data'] : null;
                            if (! is_object($product)) {
                                continue;
                            }

                            $product_name = $product->get_name();
                            $image = wp_kses_post($product->get_image('thumbnail'));
                            $quantity = isset($ci_item['quantity']) ? (int) $ci_item['quantity'] : 1;

                            // Resolve product id (use cart item parent product id when available)
                            $product_id = isset($ci_item['product_id']) ? $ci_item['product_id'] : $product->get_id();

                            // Get limited edition numbers (preferred keys only)
                            $numbers = array();
                            if (! empty($ci_item['woo_limit'])) {
                                $numbers = IJWLP_Frontend_Common::normalize_limited_number_for_processing($ci_item['woo_limit']);
                            } elseif (! empty($ci_item['woo_limit_display'])) {
                                $numbers = IJWLP_Frontend_Common::normalize_limited_number_for_processing($ci_item['woo_limit_display']);
                            }

                            // Get limited edition range
                            $limited_range = '';
                            $start_range = get_post_meta($product_id, '_woo_limit_start_value', true);
                            $end_range = get_post_meta($product_id, '_woo_limit_end_value', true);
                            if ($start_range !== '' && $end_range !== '') {
                                $limited_range = $start_range . ' - ' . $end_range;
                            }

                        ?>
                            <div class="order-summary-item">
                                <div class="item-image"><?php echo $image; ?></div>
                                <div class="item-details">
                                    <div class="item-name"><?php echo esc_html($product_name); ?></div>
                                    <div class="item-quantity"><?php echo '&#215;' . esc_html($quantity); ?></div>

                                    <?php if ($limited_range) : ?>
                                        <div class="limited-edition-range"><?php echo esc_html__('Limited Edition', 'woo-limit-product'); ?>: <?php echo esc_html($limited_range); ?></div>
                                    <?php endif; ?>

                                    <?php if (! empty($numbers)) : ?>
                                        <div class="limited-edition-display">
                                            <span class="limited-edition-label"><?php echo esc_html__('Limited Edition Numbers', 'woo-limit-product'); ?></span>
                                            <div class="limited-number-list">
                                                <?php foreach ($numbers as $num) : ?>
                                                    <span class="limited-number"><?php echo esc_html((string) $num); ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    // Backorder notification (keep as-is)
                                    $backorder_status = $product->get_stock_status();
                                    if ($backorder_status === 'onbackorder') :
                                    ?>
                                        <div class="backorder_notification">
                                            <?php echo esc_html__('Available on backorder', 'woo-limit-product'); ?>
                                            <span class="backorder-help-icon help-icon" data-tooltip="<?php echo esc_attr__('Available on backorder means that this particular product/size is currently not in stock. However, it can be ordered and will be delivered as soon as available (usually 10 days).', 'woo-limit-product'); ?>">?</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
<?php
    }

    /**
	 * Add delivery preference field to checkout page
	 * 
	 * @param WC_Checkout $checkout
	 */
	public function add_delivery_preference_field($checkout)
	{
		// Check if there are any backordered items in the cart
		$has_backordered_items = $this->check_for_backordered_items();

		if (!$has_backordered_items) {
			return; // Don't show the field if no backordered items
		}

		echo '<div id="delivery_preference_field">';
		echo '<h3><span class="backorder-help-text">' . __('Backorder Delivery', 'woocommerce') . '</span><span class="required" aria-hidden="true">*</span><span class="help-icon" data-tooltip="' . esc_attr(__('Available on backorder means that this particular product/size is currently not in stock. However, it can be ordered and will be delivered as soon as available (usually 10 days).', 'woocommerce')) . '">?</span></h3>';
		echo '<p class="backorder-para">' . __('Choose how to deliver items with backorders:', 'woocommerce') . '</p>';
		// Custom HTML for radio buttons with individual div wrappers
		$field_value = $checkout->get_value('delivery_preference') ?: 'partial_delivery';

		echo '<div class="woocommerce-input-wrapper">';

		// Partial delivery option
		echo '<div class="radio-option-wrapper">';
		echo '<input type="radio" class="input-radio" value="partial_delivery" name="delivery_preference" aria-required="true" id="delivery_preference_partial_delivery"' .
			($field_value === 'partial_delivery' ? ' checked="checked"' : '') . '>';
		echo '<label for="delivery_preference_partial_delivery" class="radio required_field">' .
			__('Deliver available items now; backordered items later', 'woocommerce') .
			'</label>';
		echo '</div>';

		// Complete delivery option
		echo '<div class="radio-option-wrapper">';
		echo '<input type="radio" class="input-radio" value="complete_delivery" name="delivery_preference" aria-required="true" id="delivery_preference_complete_delivery"' .
			($field_value === 'complete_delivery' ? ' checked="checked"' : '') . '>';
		echo '<label for="delivery_preference_complete_delivery" class="radio required_field">' .
			__('Deliver everything together when all items are available', 'woocommerce') .
			'</label>';
		echo '</div>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Validate delivery preference field
	 */
	public function validate_delivery_preference_field()
	{
		// Check if there are any backordered items in the cart
		$has_backordered_items = $this->check_for_backordered_items();

		if (!$has_backordered_items) {
			return; // Don't validate if no backordered items
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
		if (!empty($_POST['delivery_preference'])) {
			$delivery_preference = sanitize_text_field($_POST['delivery_preference']);
			update_post_meta($order_id, '_delivery_preference', $delivery_preference);

			// Add a note to the order
			$order = wc_get_order($order_id);
			if ($order) {
				$preference_text = $delivery_preference === 'partial_delivery' ?
					__('Deliver available items now, backordered items when ready', 'woocommerce') :
					__('Wait for all items to be available before delivery', 'woocommerce');

				$order->add_order_note(sprintf(__('Customer delivery preference: %s', 'woocommerce'), $preference_text));
			}
		}
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

		foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
			$product = $cart_item['data'];
			if ($product && $product->is_on_backorder($cart_item['quantity'])) {
				return true;
			}
		}

		return false;
	}
}
