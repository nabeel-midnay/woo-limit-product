<?php

/**
 * WooCommerce Limited Product Plugin - Options Class (Common Class)
 * 
 * This class handles functionality that needs to be available in both admin and frontend contexts
 * 
 * @package WooCommerce Limited Product
 * @version 1.0.0
 */

if (!defined('ABSPATH'))
	exit;


class IJWLP_Options
{
	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	public $product_lists = [];

	/**
	 * Constructor - Common class loaded in both admin and frontend
	 */
	public function __construct()
	{
		// Handle order status changes from admin or anywhere else
		// This hook fires whenever order status changes, regardless of what status it changes to/from
		add_action('woocommerce_order_status_changed', array($this, 'update_limited_edition_status_on_order_status'), 10, 4);

		// Legacy hooks for backwards compatibility (in case order is created before payment)
		add_action('woocommerce_order_status_processing', array($this, 'update_limited_edition_status_on_order_status'), 10, 1);
		add_action('woocommerce_order_status_completed', array($this, 'update_limited_edition_status_on_order_status'), 10, 1);

		// AJAX handlers - loaded in both admin and frontend contexts
		add_action('wp_ajax_ijwlp_add_to_cart', array($this, 'ajax_add_to_cart'));
		add_action('wp_ajax_nopriv_ijwlp_add_to_cart', array($this, 'ajax_add_to_cart'));

		add_action('wp_ajax_ijwlp_update_cart', array($this, 'ajax_update_cart'));
		add_action('wp_ajax_nopriv_ijwlp_update_cart', array($this, 'ajax_update_cart'));

		add_action('wp_ajax_ijwlp_clear_in_cart', array($this, 'ajax_clear_in_cart'));

		add_action('wp_ajax_ijwlp_check_number_availability', array($this, 'ajax_check_number_availability'));
		add_action('wp_ajax_nopriv_ijwlp_check_number_availability', array($this, 'ajax_check_number_availability'));

	}

	/**
	 * Get admin setting value
	 * 
	 * @param string $key Setting key (e.g., 'limitlabel', 'limitlabelcart', 'limittime', 'enablelimit', 'limitposition')
	 * @param mixed $default Default value if setting is not found or empty
	 * @return mixed Setting value or default
	 */
	public static function get_setting($key, $default = '')
	{
		$advanced_settings = get_option('ijwlp_advanced_settings', array());

		if (!is_array($advanced_settings)) {
			return $default;
		}

		// If key doesn't exist, return default
		if (!isset($advanced_settings[$key])) {
			return $default;
		}

		$value = $advanced_settings[$key];

		// If value is empty string and default is provided, return default
		// This allows intentional empty strings to be used, but provides fallback for unset values
		if ($value === '' && $default !== '') {
			return $default;
		}

		return $value;
	}

	/**
	 * Get unique user identifier for limit tracking
	 * 
	 * For logged-in users: returns WordPress user ID
	 * For guests: returns a unique identifier based on IP address
	 * 
	 * This ensures each guest user has their own limit tracking instead of 
	 * all guests sharing user ID 0.
	 * 
	 * @return string User identifier (user ID or IP-based hash for guests)
	 */
	public static function get_user_identifier()
	{
		$user_id = get_current_user_id();

		if ($user_id > 0) {
			// Logged-in user - use their WordPress user ID
			return (string) $user_id;
		}

		// Guest user - use IP address as identifier
		$ip = self::get_client_ip();

		// Create a consistent identifier from IP
		// Using 'guest_' prefix to distinguish from user IDs
		return 'guest_' . md5($ip . 'woo_limit_salt');
	}

	/**
	 * Get client IP address
	 * 
	 * Checks various server variables to get the real client IP,
	 * accounting for proxies and load balancers.
	 * 
	 * @return string Client IP address
	 */
	public static function get_client_ip()
	{
		$ip = '';

		// Check for forwarded IP (behind proxy/load balancer)
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			// Can contain multiple IPs (client, proxy1, proxy2...)
			$ips = explode(',', sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']));
			$ip = trim($ips[0]);
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
			$ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED']);
		} elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
			$ip = sanitize_text_field($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']);
		} elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
			$ip = sanitize_text_field($_SERVER['HTTP_FORWARDED_FOR']);
		} elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
			$ip = sanitize_text_field($_SERVER['HTTP_FORWARDED']);
		} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
			$ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
		}

		// Validate IP address
		if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
			return $ip;
		}

		// Fallback to REMOTE_ADDR if validation fails
		return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : '0.0.0.0';
	}

	/**
	 * Clean up stale block records for the current user
	 * 
	 * When a user's session expires (browser closed, cookies cleared, etc.),
	 * their "blocked" records in the database become stale because the cart_key
	 * no longer exists in any active WooCommerce session.
	 * 
	 * This method finds all block records for the current user and removes any
	 * whose cart_key is not in their current WooCommerce cart.
	 * 
	 * @param int|null $product_id Optional - limit cleanup to specific product
	 * @return int Number of stale records cleaned up
	 */
	public static function cleanup_stale_user_blocks($product_id = null)
	{
		global $wpdb, $table_prefix;
		$table = $table_prefix . 'woo_limit';

		$user_id = self::get_user_identifier();
		$cart = WC()->cart;

		// Get all current cart keys for this session
		$current_cart_keys = array();
		if ($cart) {
			$cart_items = $cart->get_cart();
			if (!empty($cart_items)) {
				$current_cart_keys = array_keys($cart_items);
			}
		}

		// Build query to find blocked records for this user
		$sql = $wpdb->prepare(
			"SELECT id, cart_key FROM $table WHERE user_id = %s AND status = 'block'",
			$user_id
		);

		if ($product_id) {
			$sql .= $wpdb->prepare(" AND parent_product_id = %s", $product_id);
		}

		$blocked_records = $wpdb->get_results($sql);

		$cleaned_count = 0;
		if ($blocked_records) {
			foreach ($blocked_records as $record) {
				// If cart_key is not in current cart, it's stale
				if (!in_array($record->cart_key, $current_cart_keys)) {
					$wpdb->delete(
						$table,
						array('id' => $record->id),
						array('%d')
					);
					$cleaned_count++;
				}
			}
		}

		return $cleaned_count;
	}

	/**
	 * Update limited edition status when order status changes
	 * 
	 * @param int $order_id Order ID
	 * @param string $old_status Old order status (optional, for woocommerce_order_status_changed hook)
	 * @param string $new_status New order status (optional, for woocommerce_order_status_changed hook)
	 * @param WC_Order $order Order object (optional, for woocommerce_order_status_changed hook)
	 */
	public function update_limited_edition_status_on_order_status($order_id, $old_status = null, $new_status = null, $order = null)
	{

		if (!$order_id) {
			return;
		}

		// Get order object - use provided one or fetch it
		if (!$order) {
			$order = wc_get_order($order_id);
		}

		if (!$order) {
			return;
		}

		global $wpdb, $table_prefix;
		$table = $table_prefix . 'woo_limit';

		// Use new status if provided (from woocommerce_order_status_changed), otherwise get from order
		$order_status = $new_status ? $new_status : $order->get_status();

		// If order is cancelled, delete all records from the table for this order
		if ($order_status === 'cancelled') {
			// Delete records matching order_id (handle both integer and string formats)
			$deleted = $wpdb->query($wpdb->prepare(
				"DELETE FROM $table WHERE order_id = %s OR order_id = %s",
				$order_id,
				(string) $order_id
			));

			// Return early since we've deleted the records
			return;
		}

		// Process each order item to ensure records are properly updated
		foreach ($order->get_items() as $item_id => $item) {
			$cart_item_key = $item->get_meta('_cart_item_key');
			$limited_number = $item->get_meta('Limited Edition Number');

			// Ensure limited_number is a string, not an array
			if (is_array($limited_number)) {
				$limited_number = implode(',', $limited_number);
			}

			if (empty($limited_number)) {
				continue;
			}

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

			// Update records - handle both cases: records with order_id and records still in 'block' status
			if (!empty($cart_item_key)) {
				// Try to update by cart_key first
				$updated = $wpdb->update(
					$table,
					array(
						'order_id' => (string) $order_id,
						'order_item_id' => (string) $item_id,
						'status' => 'ordered',
						'order_status' => $order_status
					),
					array(
						'cart_key' => (string) $cart_item_key,
						'limit_no' => (string) $limited_number
					),
					array('%s', '%s', '%s', '%s'),
					array('%s', '%s')
				);
			}

			// Also update records that might not have cart_key - only update 'block' status records or records already linked to this order
			$wpdb->query($wpdb->prepare(
				"UPDATE $table 
				SET order_id = %s, order_item_id = %s, status = 'ordered', order_status = %s 
				WHERE parent_product_id = %s 
				AND limit_no = %s 
				AND (status = 'block' OR order_id = %s)",
				(string) $order_id,
				(string) $item_id,
				$order_status,
				(string) $parent_product_id,
				(string) $limited_number,
				(string) $order_id
			));
		}

		// Also update order status for any records already linked to this order (for backwards compatibility)
		$wpdb->update(
			$table,
			array('order_status' => $order_status),
			array('order_id' => (string) $order_id),
			array('%s'),
			array('%s')
		);
	}

	/**
	 * AJAX handler for adding product to cart
	 */
	public function ajax_add_to_cart()
	{
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ijwlp_frontend_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'woolimited')));
		}

		// Get product ID
		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
		$quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
		$limited_number = isset($_POST['woo_limit']) ? sanitize_text_field($_POST['woo_limit']) : '';

		if (!$product_id) {
			wp_send_json_error(array('message' => __('Product ID is required.', 'woolimited')));
		}

		// Set POST data for validation and cart item data filters
		$_POST['woo-limit'] = $limited_number;
		if ($variation_id > 0) {
			$_POST['variation_id'] = $variation_id;
		}

		// Validate limited edition number
		$check_pro_id = $variation_id > 0 ? $variation_id : $product_id;
		$product = wc_get_product($check_pro_id);

		if ($product && $product->is_type('variation')) {
			$parent_product_id = $product->get_parent_id();
		} else {
			$parent_product_id = $product_id;
		}

		$is_limited = get_post_meta($parent_product_id, '_woo_limit_status', true);

		if ($is_limited === 'yes') {
			if (empty($limited_number)) {
				wp_send_json_error(array('message' => __('Please enter a Limited Edition Number.', 'woolimited')));
			}

			// Validate number
			$start = get_post_meta($parent_product_id, '_woo_limit_start_value', true);
			$end = get_post_meta($parent_product_id, '_woo_limit_end_value', true);

			if ($start && $end) {
				$clean_number = intval($limited_number);
				if ($clean_number < intval($start) || $clean_number > intval($end)) {
					wp_send_json_error(array(
						'message' => sprintf(
							__('Limited Edition Number must be between %s and %s.', 'woolimited'),
							$start,
							$end
						)
					));
				}
			}

			// Check availability
			$available_numbers = limitedNosAvailable($parent_product_id);
			$available_array = array_map('trim', explode(',', $available_numbers));

			if (!in_array($limited_number, $available_array) && !in_array(intval($limited_number), $available_array)) {
				wp_send_json_error(array('message' => __('This Limited Edition Number is not available.', 'woolimited')));
			}
		}

		// Determine actual product ID (variation or product)
		$actual_pro_id = $variation_id > 0 ? $variation_id : $product_id;

		// Prepare cart item data with limited edition number
		$cart_item_data = array();
		if (!empty($limited_number)) {
			$cart_item_data['woo_limit'] = array($limited_number);
			$cart_item_data['woo_limit_pro_id'] = $actual_pro_id;
		}

		// Add to cart with cart item data
		$cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, array(), $cart_item_data);

		if ($cart_item_key) {
			// Manually ensure limited edition number is blocked in database
			// This ensures the number is added to the table even when adding via AJAX
			if (!empty($limited_number) && $is_limited === 'yes') {
				$this->block_limited_edition_number_in_db($cart_item_key, $parent_product_id, $actual_pro_id, array($limited_number));
			}

			// Get cart fragments for AJAX response
			ob_start();
			woocommerce_mini_cart();
			$mini_cart = ob_get_clean();

			$data = array(
				'fragments' => apply_filters('woocommerce_add_to_cart_fragments', array(
					'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
				)),
				'cart_hash' => WC()->cart->get_cart_hash(),
				'cart_item_key' => $cart_item_key,
				'message' => __('Product added to cart successfully.', 'woolimited')
			);

			wp_send_json_success($data);
		} else {
			// Check for any WooCommerce error notices
			$notices = wc_get_notices('error');
			$message = !empty($notices) ? $notices[0]['notice'] : __('Failed to add product to cart.', 'woolimited');

			// Clear notices so they don't show as regular notices
			wc_clear_notices();

			wp_send_json_error(array('message' => $message));
		}
	}

	/**
	 * AJAX handler for updating cart
	 */
	public function ajax_update_cart()
	{
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ijwlp_frontend_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'woolimited')));
		}

		// Check if cart update data is provided
		if (!isset($_POST['woo_limit']) || !is_array($_POST['woo_limit'])) {
			wp_send_json_error(array('message' => __('No data to update.', 'woolimited')));
		}

		// Process cart quantities if provided
		if (isset($_POST['cart']) && is_array($_POST['cart'])) {
			foreach ($_POST['cart'] as $cart_item_key => $cart_item_data) {
				if (isset($cart_item_data['qty'])) {
					$quantity = absint($cart_item_data['qty']);
					if ($quantity > 0) {
						WC()->cart->set_quantity($cart_item_key, $quantity, false);
					} else {
						WC()->cart->remove_cart_item($cart_item_key);
					}
				}
			}
		}

		// Set POST data for the update filter to process limited edition numbers
		$woo_limit_data = array();
		foreach ($_POST['woo_limit'] as $cart_item_key => $numbers) {
			if (is_array($numbers)) {
				$woo_limit_data[$cart_item_key] = array_map('sanitize_text_field', $numbers);
			} else {
				$woo_limit_data[$cart_item_key] = array(sanitize_text_field($numbers));
			}
		}
		$_POST['woo_limit'] = $woo_limit_data;

		// Manually trigger the update (pass false to indicate AJAX call)
		$errors = $this->update_cart_item_limited_number_for_ajax();

		// Calculate totals
		WC()->cart->calculate_totals();

		// Check if there are errors
		if (!empty($errors) && is_array($errors)) {
			// Return errors for each cart item
			wp_send_json_error(array(
				'errors' => $errors,
				'message' => __('Please fix the errors below.', 'woolimited')
			));
		}

		// Get updated cart fragments
		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		$data = array(
			'fragments' => apply_filters('woocommerce_add_to_cart_fragments', array(
				'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
			)),
			'cart_hash' => WC()->cart->get_cart_hash(),
			'message' => __('Cart updated successfully.', 'woolimited')
		);

		wp_send_json_success($data);
	}

	/**
	 * Update limited edition number when changed in cart (for AJAX calls)
	 * @return array - Returns errors array
	 */
	private function update_cart_item_limited_number_for_ajax()
	{
		if (!isset($_POST['woo_limit']) || !is_array($_POST['woo_limit'])) {
			return array();
		}

		$cart = WC()->cart;
		if (!$cart) {
			return array();
		}

		$errors = array();

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

						$errors[$cart_item_key] = $error_message;
						$item_has_error = true;
						break;
					}
				}

				// Check if number is available
				if (!in_array($new_number, $available_array)) {
					// Also check cleaned number
					$clean_number = intval($new_number);
					if (!in_array($clean_number, $available_array)) {
						$error_message = sprintf(__('Limited Edition Number %s is not available.', 'woolimited'), $new_number);

						$errors[$cart_item_key] = $error_message;
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
			}
		}

		return $errors;
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

		// Get product type and user identifier
		$product = wc_get_product($actual_pro_id);
		$product_type = $product ? $product->get_type() : 'simple';
		$user_id = self::get_user_identifier();

		// Store new numbers as comma-separated string
		$limit_no_string = implode(',', $new_numbers);

		// Check if record exists for this cart_key
		$existing_record = $wpdb->get_row($wpdb->prepare(
			"SELECT id FROM $table 
			WHERE cart_key = %s 
			AND parent_product_id = %s 
			AND status = 'block'",
			$cart_item_key,
			$parent_product_id
		));

		// Calculate expiry time from settings
		$limit_minutes = self::get_setting('limittime', 15);
		$expiry_time = date('Y-m-d H:i:s', time() + ($limit_minutes * 60));

		if ($existing_record) {
			// Update existing record with new comma-separated numbers
			$wpdb->update(
				$table,
				array(
					'limit_no' => $limit_no_string,
					'time' => current_time('mysql'),
					'expiry_time' => $expiry_time,
				),
				array(
					'id' => $existing_record->id
				),
				array('%s', '%s', '%s'),
				array('%d')
			);
		} else {
			// Check if any of the new numbers are already blocked or ordered
			$numbers_to_check = $new_numbers;
			$blocked_numbers = array();

			foreach ($numbers_to_check as $number) {
				$existing = $wpdb->get_row($wpdb->prepare(
					"SELECT id, status, limit_no FROM $table 
				WHERE parent_product_id = %s 
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
						'expiry_time' => $expiry_time,
					),
					array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
				);
			}
		}

		// IMPORTANT: Also update persistent cart for logged-in users
		$wp_user_id = get_current_user_id();
		if ($wp_user_id > 0) {
			$persistent_cart = get_user_meta($wp_user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);
			if (!empty($persistent_cart) && isset($persistent_cart['cart'][$cart_item_key])) {
				$persistent_cart['cart'][$cart_item_key]['woo_limit'] = $new_numbers;
				$persistent_cart['cart'][$cart_item_key]['quantity'] = count($new_numbers);
				update_user_meta($wp_user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), $persistent_cart);
			}
		}
	}


	/**
	 * AJAX handler for clearing in-cart (blocked) limited numbers
	 */
	public function ajax_clear_in_cart()
	{
		if (!current_user_can('manage_options')) {
			wp_send_json_error(array('message' => 'forbidden'), 403);
		}

		check_ajax_referer('ijwlp_clear_in_cart');

		global $table_prefix, $wpdb;
		$table = $table_prefix . 'woo_limit';

		$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

		if ($product_id > 0) {
			$where = $wpdb->prepare("WHERE status = 'block' AND parent_product_id = %s", $product_id);
		} else {
			$where = "WHERE status = 'block'";
		}

		$sql = "DELETE FROM {$table} {$where}";
		$result = $wpdb->query($sql);

		if ($result === false) {
			wp_send_json_error(array('message' => 'db_error'));
		}

		wp_send_json_success(array('cleared' => intval($result)));
	}

	/**
	 * AJAX handler for checking number availability
	 */
	public function ajax_check_number_availability()
	{
		// Verify nonce
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ijwlp_frontend_nonce')) {
			wp_send_json_error(array('message' => __('Security check failed.', 'woolimited')));
		}

		// Get parameters
		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		$variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
		$limited_number = isset($_POST['woo_limit']) ? sanitize_text_field($_POST['woo_limit']) : '';
		$cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';

		if (!$product_id || empty($limited_number)) {
			wp_send_json_error(array('message' => __('Invalid parameters.', 'woolimited')));
		}

		// Determine parent product ID
		$check_pro_id = $variation_id > 0 ? $variation_id : $product_id;
		$product = wc_get_product($check_pro_id);

		if ($product && $product->is_type('variation')) {
			$parent_product_id = $product->get_parent_id();
		} else {
			$parent_product_id = $product_id;
		}

		// Check if product is limited edition
		$is_limited = get_post_meta($parent_product_id, '_woo_limit_status', true);
		if ($is_limited !== 'yes') {
			wp_send_json_success(array(
				'available' => true,
				'message' => __('Number is available.', 'woolimited'),
				'status' => 'available'
			));
		}

		global $wpdb, $table_prefix;
		$table = $table_prefix . 'woo_limit';
		$user_id = self::get_user_identifier();

		// Clean up any stale block records for this user before checking availability
		// This handles cases where user's session expired (closed browser, cleared cookies, etc.)
		self::cleanup_stale_user_blocks($parent_product_id);

		// Check number status in database
		$clean_number = intval($limited_number);

		// Check if number is ordered (sold)
		$ordered = $wpdb->get_row($wpdb->prepare(
			"SELECT id, status, order_id, user_id, cart_key FROM $table 
			WHERE parent_product_id = %s 
			AND status = 'ordered'
			AND (limit_no = %s OR limit_no LIKE %s OR limit_no LIKE %s OR limit_no LIKE %s)",
			$parent_product_id,
			$limited_number,
			$limited_number . ',%',
			'%,' . $limited_number,
			'%,' . $limited_number . ',%'
		));

		if ($ordered) {
			wp_send_json_success(array(
				'available' => false,
				'message' => __('Sold, try another one', 'woolimited'),
				'status' => 'sold'
			));
		}

		// Check if number is blocked (in someone's cart)
		$blocked = $wpdb->get_row($wpdb->prepare(
			"SELECT id, status, user_id, cart_key FROM $table 
			WHERE parent_product_id = %s 
			AND status = 'block'
			AND (limit_no = %s OR limit_no LIKE %s OR limit_no LIKE %s OR limit_no LIKE %s)",
			$parent_product_id,
			$limited_number,
			$limited_number . ',%',
			'%,' . $limited_number,
			'%,' . $limited_number . ',%'
		));

		if ($blocked) {
			// Check if the blocked cart_key exists in the current user's WooCommerce cart
			// This works for both logged-in users AND guest users
			$cart = WC()->cart;
			$is_in_current_cart = false;

			if ($cart) {
				$cart_item = $cart->get_cart_item($blocked->cart_key);
				if ($cart_item) {
					$is_in_current_cart = true;
				}
			}

			// Check if this block record belongs to the current user
			$is_current_user_record = ($blocked->user_id == $user_id);

			// If block record belongs to current user but cart_key is NOT in their current cart,
			// this is a stale record (session expired, browser closed, etc.)
			// We should clean it up and treat the number as available
			if ($is_current_user_record && !$is_in_current_cart) {
				// Delete the stale block record
				$wpdb->delete(
					$table,
					array(
						'id' => $blocked->id
					),
					array('%d')
				);

				// Number is now available (stale record cleaned up)
				// Continue to range check below instead of returning here
				$blocked = null;
			} elseif ($is_in_current_cart) {
				// Number is in current user's cart (works for both logged-in and guest users)
				wp_send_json_success(array(
					'available' => true,
					'message' => __('Already in your cart', 'woolimited'),
					'status' => 'in_your_cart'
				));
			}
		}

		// If still blocked by someone else
		if ($blocked) {
			// It's in someone else's cart (not in current user's cart)
			wp_send_json_success(array(
				'available' => false,
				'message' => __("Number currently in someone else's cart", 'woolimited'),
				'status' => 'in_other_cart'
			));
		}

		// Check if number is within valid range
		$start = get_post_meta($parent_product_id, '_woo_limit_start_value', true);
		$end = get_post_meta($parent_product_id, '_woo_limit_end_value', true);

		// Check maximum quantity limit (if configured) and compute counts
		$max_quantity = get_post_meta($parent_product_id, '_woo_limit_max_quantity', true);
		if (!empty($max_quantity)) {
			// Get user identifier for limit tracking
			$user_identifier = self::get_user_identifier();

			// Gather all existing numbers (blocked) and count them
			// If cart_item_key is provided (editing from cart page), exclude that cart item's numbers
			// to avoid false "max reached" when user is just changing their existing number
			if (!empty($cart_item_key)) {
				$rows = $wpdb->get_col($wpdb->prepare(
					"SELECT limit_no FROM $table WHERE parent_product_id = %s AND status = 'block' AND user_id = %s AND cart_key != %s",
					$parent_product_id,
					$user_identifier,
					$cart_item_key
				));
			} else {
				$rows = $wpdb->get_col($wpdb->prepare(
					"SELECT limit_no FROM $table WHERE parent_product_id = %s AND status = 'block' AND user_id = %s",
					$parent_product_id,
					$user_identifier
				));
			}
			$all_numbers = array();
			if ($rows) {
				foreach ($rows as $r) {
					$r = trim($r);
					if ($r === '') {
						continue;
					}
					$parts = array_map('trim', explode(',', $r));
					$all_numbers = array_merge($all_numbers, $parts);
				}
			}
			$all_numbers = array_filter($all_numbers, 'strlen');
			$existing_count = count($all_numbers);

			// Count how many of those numbers are in the current user's cart (or their cart_key)
			$user_numbers = array();

			// Numbers blocked for this user (works for both logged-in and guests via IP)
			$user_rows = $wpdb->get_col($wpdb->prepare(
				"SELECT limit_no FROM $table WHERE parent_product_id = %s AND status = 'block' AND user_id = %s",
				$parent_product_id,
				$user_identifier
			));
			if ($user_rows) {
				foreach ($user_rows as $r) {
					$r = trim($r);
					if ($r === '') {
						continue;
					}
					$parts = array_map('trim', explode(',', $r));
					$user_numbers = array_merge($user_numbers, $parts);
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
						"SELECT limit_no FROM $table WHERE parent_product_id = %s AND status = 'block' AND cart_key IN ($placeholders)",
						array_merge(array($parent_product_id), $cart_keys)
					);
					$guest_rows = $wpdb->get_col($sql);
					if ($guest_rows) {
						foreach ($guest_rows as $r) {
							$r = trim($r);
							if ($r === '') {
								continue;
							}
							$parts = array_map('trim', explode(',', $r));
							$user_numbers = array_merge($user_numbers, $parts);
						}
					}
				}
			}

			$user_numbers = array_filter($user_numbers, 'strlen');
			$user_numbers = array_unique($user_numbers);
			$user_count = count($user_numbers);

			if (intval($existing_count) >= intval($max_quantity)) {
				// Craft a helpful message including how many are already in this user's cart
				if ($user_count > 0) {
					$message = sprintf(
						__('%s Maximum quantity reached for this product. You already have %d in your cart.', 'woolimited'),
						'',
						$user_count
					);
				} else {
					$message = __('Maximum quantity reached for this product.', 'woolimited');
				}

				wp_send_json_success(array(
					'available' => false,
					'message' => $message,
					'status' => 'max_quantity',
					'max_quantity' => intval($max_quantity),
					'existing_count' => intval($existing_count),
					'user_count' => intval($user_count),
				));
			}
		}

		if ($start && $end) {
			if ($clean_number < intval($start) || $clean_number > intval($end)) {
				wp_send_json_success(array(
					'available' => false,
					'message' => sprintf(
						__('Limited Edition Number must be between %s and %s.', 'woolimited'),
						$start,
						$end
					),
					'status' => 'out_of_range'
				));
			}
		}

		// Number is available
		wp_send_json_success(array(
			'available' => true,
			'message' => __('This Limited Edition Number is available.', 'woolimited'),
			'status' => 'available'
		));
	}

	/**
	 * Block limited edition number in database
	 * Helper method to ensure numbers are blocked when adding via AJAX
	 * 
	 * @param string $cart_item_key - Cart item key
	 * @param int $parent_product_id - Parent product ID
	 * @param int $actual_pro_id - Actual product/variation ID
	 * @param array $limited_numbers - Array of limited edition numbers
	 */
	private function block_limited_edition_number_in_db($cart_item_key, $parent_product_id, $actual_pro_id, $limited_numbers)
	{
		global $wpdb, $table_prefix;
		$table = $table_prefix . 'woo_limit';

		// Get user identifier (user ID for logged-in, IP-based for guests)
		$user_id = self::get_user_identifier();

		// Get product type
		$product = wc_get_product($actual_pro_id);
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

		// Calculate expiry time from settings
		$limit_minutes = self::get_setting('limittime', 15);
		$expiry_time = date('Y-m-d H:i:s', time() + ($limit_minutes * 60));

		if ($existing_record) {
			// Merge existing numbers with the new ones (avoid duplicates) and update
			$existing_limit_no = trim($existing_record->limit_no);
			$existing_numbers = [];
			if ($existing_limit_no !== '') {
				$existing_numbers = array_map('trim', explode(',', $existing_limit_no));
			}
			// Merge and keep unique values
			$merged = array_unique(array_merge($existing_numbers, $limited_numbers));
			$merged = array_filter($merged, 'strlen');
			$merged_limit_no_string = implode(',', $merged);

			$wpdb->update(
				$table,
				array(
					'limit_no' => $merged_limit_no_string,
					'time' => current_time('mysql'),
					'expiry_time' => $expiry_time,
				),
				array(
					'id' => $existing_record->id
				),
				array('%s', '%s', '%s'),
				array('%d')
			);
		} else {
			// Check if any of these numbers are already blocked or ordered by another cart
			$numbers_to_check = $limited_numbers;
			$blocked_numbers = array();

			foreach ($numbers_to_check as $number) {
				$existing = $wpdb->get_row($wpdb->prepare(
					"SELECT id, status, limit_no FROM $table 
				WHERE parent_product_id = %s 
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
			}			// Only insert if none of the numbers are already blocked/ordered
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
						'order_id' => 'block',
						'time' => current_time('mysql'),
						'expiry_time' => $expiry_time,
					),
					array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
				);
			}
		}
	}

}
