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
        // Register limited timer shortcode
        add_shortcode('limited_timer', array($this, 'render_limited_timer_shortcode'));

        // AJAX handler to remove limited products when timer expires
        add_action('wp_ajax_ijwlp_remove_expired_limited_products', array($this, 'ajax_remove_expired_limited_products'));
        add_action('wp_ajax_nopriv_ijwlp_remove_expired_limited_products', array($this, 'ajax_remove_expired_limited_products'));

        // AJAX handler to check if cart has limited products
        add_action('wp_ajax_ijwlp_cart_has_limited_products', array($this, 'ajax_cart_has_limited_products'));
        add_action('wp_ajax_nopriv_ijwlp_cart_has_limited_products', array($this, 'ajax_cart_has_limited_products'));

        // Hook into cart item removal to clear timer if no limited items remain
        add_action('woocommerce_cart_item_removed', array($this, 'check_and_clear_timer_if_needed'), 10, 2);

        // AJAX handler to get timer data from backend (user meta or session)
        add_action('wp_ajax_ijwlp_get_timer_data', array($this, 'ajax_get_timer_data'));
        add_action('wp_ajax_nopriv_ijwlp_get_timer_data', array($this, 'ajax_get_timer_data'));

        // AJAX handler to set timer data to backend (user meta or session)
        add_action('wp_ajax_ijwlp_set_timer_data', array($this, 'ajax_set_timer_data'));
        add_action('wp_ajax_nopriv_ijwlp_set_timer_data', array($this, 'ajax_set_timer_data'));

        // Restore timer from user meta on login
        add_action('wp_login', array($this, 'restore_timer_on_login'), 10, 2);

        // Clear timer from user meta on logout (keep session for guest)
        add_action('wp_logout', array($this, 'handle_logout_timer'), 10);
    }

    /**
     * Render [limited_timer] shortcode
     * 
     * Shows countdown timer for limited-edition products
     * - Shows on product page if it's a limited edition product
     * - Shows on other pages if cart contains limited items
     * - Hidden on checkout page
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_limited_timer_shortcode($atts = array())
    {
        // Skip on checkout page
        if (is_checkout()) {
            return '';
        }

        // Cart has limited products - show timer
        $limitTime = IJWLP_Options::get_setting('limittime', 15);

        if ($limitTime < 1) {
            return '';
        }

        return $this->get_timer_html($limitTime);
    }

    /**
     * Get HTML markup for timer display
     * 
     * @param int $limitTime Time limit in minutes
     * @return string HTML output
     */
    private function get_timer_html($limitTime)
    { ?>
        <div id="woo-limit-timer" data-limit-time="<?php echo esc_attr($limitTime); ?>">
            <div class="timer-container">
                <div class="time-box">
                    <div class="time-number" id="timer-minutes">00</div>
                    <div class="time-label">MINUTES</div>
                </div>
                <div class="time-box">
                    <div class="time-number" id="timer-seconds">00</div>
                    <div class="time-label">SECONDS</div>
                </div>
            </div>
        </div>
        <?php
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
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ijwlp_frontend_nonce')) {
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

        global $wpdb, $table_prefix;
        $table = $table_prefix . 'woo_limit';

        // Iterate through cart items and remove limited products
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (self::is_limited_product($cart_item)) {

                // Remove from database first
                $wpdb->delete(
                    $table,
                    array(
                        'cart_key' => $cart_item_key,
                        'status' => 'block'
                    ),
                    array('%s', '%s')
                );

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
        // Also clear from backend storage
        if (!self::cart_has_limited_products()) {
            $this->clear_timer_storage();
        }
    }

    /**
     * AJAX: Get timer data from backend storage
     * Returns timer expiry from user meta (logged-in) or WC session (guest)
     */
    public function ajax_get_timer_data()
    {
        $timer_data = $this->get_timer_from_storage();

        wp_send_json_success(array(
            'expiry' => $timer_data['expiry'],
            'is_active' => $timer_data['is_active'],
            'has_limited_products' => self::cart_has_limited_products()
        ));
    }

    /**
     * AJAX: Set timer data to backend storage
     * Saves timer expiry to user meta (logged-in) or WC session (guest)
     */
    public function ajax_set_timer_data()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ijwlp_frontend_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed', 'woolimited')
            ));
        }

        $expiry = isset($_POST['expiry']) ? intval($_POST['expiry']) : 0;

        if ($expiry <= 0) {
            wp_send_json_error(array(
                'message' => __('Invalid expiry time', 'woolimited')
            ));
        }

        $this->save_timer_to_storage($expiry);

        wp_send_json_success(array(
            'message' => __('Timer saved', 'woolimited'),
            'expiry' => $expiry
        ));
    }

    /**
     * Get timer data from storage (user meta or WC session)
     * 
     * @return array ['expiry' => int, 'is_active' => bool]
     */
    public function get_timer_from_storage()
    {
        $expiry = 0;
        $is_active = false;
        $current_time = time();

        $user_id = get_current_user_id();

        if ($user_id > 0) {
            // Logged-in user: get from user meta
            $expiry = get_user_meta($user_id, '_ijwlp_timer_expiry', true);
            $expiry = $expiry ? intval($expiry) : 0;
        } else {
            // Guest: get from WC session
            if (function_exists('WC') && WC()->session) {
                $expiry = WC()->session->get('ijwlp_timer_expiry');
                $expiry = $expiry ? intval($expiry) : 0;
            }
        }

        // Check if timer is still valid (not expired)
        if ($expiry > 0 && $expiry > $current_time) {
            $is_active = true;
        } else {
            $expiry = 0;
        }

        return array(
            'expiry' => $expiry,
            'is_active' => $is_active
        );
    }

    /**
     * Save timer expiry to storage (user meta or WC session)
     * 
     * @param int $expiry Unix timestamp when timer expires
     */
    public function save_timer_to_storage($expiry)
    {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            // Logged-in user: save to user meta
            update_user_meta($user_id, '_ijwlp_timer_expiry', $expiry);
        }

        // Always save to WC session (works for both guests and logged-in)
        // This ensures continuity if user logs out
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('ijwlp_timer_expiry', $expiry);
        }
    }

    /**
     * Clear timer from storage
     */
    public function clear_timer_storage()
    {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            delete_user_meta($user_id, '_ijwlp_timer_expiry');
        }

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('ijwlp_timer_expiry', null);
        }
    }

    /**
     * Restore timer from user meta on login
     * Handles expired products removal and guest product transfer
     * 
     * @param string $user_login Username
     * @param WP_User $user User object
     */
    public function restore_timer_on_login($user_login, $user)
    {
        if (!$user || !$user->ID) {
            return;
        }

        global $wpdb, $table_prefix;
        $table = $table_prefix . 'woo_limit';

        $current_time = time();
        $user_id = (string) $user->ID;

        // Get timer from user meta (logged-in user's timer from before logout)
        $user_expiry = get_user_meta($user->ID, '_ijwlp_timer_expiry', true);
        $user_expiry = $user_expiry ? intval($user_expiry) : 0;

        // Get timer from current session (guest session before login)
        $session_expiry = 0;
        if (function_exists('WC') && WC()->session) {
            $session_expiry = WC()->session->get('ijwlp_timer_expiry');
            $session_expiry = $session_expiry ? intval($session_expiry) : 0;
        }

        // Get guest identifier (the guest session before login)
        $guest_identifier = IJWLP_Options::get_user_identifier();
        // Note: At this point, get_current_user_id() returns 0 since login is in progress
        // So guest_identifier would still be the guest hash

        $user_valid = ($user_expiry > 0 && $user_expiry > $current_time);
        $session_valid = ($session_expiry > 0 && $session_expiry > $current_time);

        // Check if user has blocked products in woo_limit table
        $user_has_products = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE user_id = %s AND status = 'block'",
            $user_id
        ));
        $user_has_products = intval($user_has_products) > 0;

        // IMPORTANT: Sync persistent cart with woo_limit table
        // This ensures the persistent cart's woo_limit field matches the actual DB state
        $this->sync_persistent_cart_with_db($user->ID, $user_id);

        // CASE 1: User has valid timer AND has products
        // → Keep user products, DELETE guest products (don't transfer)
        if ($user_valid && $user_has_products) {
            // Get guest cart keys before deleting
            $guest_cart_keys = array();
            if (strpos($guest_identifier, 'guest_') === 0) {
                $guest_cart_keys = $wpdb->get_col($wpdb->prepare(
                    "SELECT cart_key FROM $table WHERE user_id = %s AND status = 'block'",
                    $guest_identifier
                ));

                // Delete guest products from woo_limit table
                $wpdb->delete(
                    $table,
                    array(
                        'user_id' => $guest_identifier,
                        'status' => 'block'
                    ),
                    array('%s', '%s')
                );
            }

            // Remove guest products from WooCommerce cart (if cart exists)
            if (!empty($guest_cart_keys) && function_exists('WC') && WC()->cart) {
                foreach ($guest_cart_keys as $cart_key) {
                    WC()->cart->remove_cart_item($cart_key);
                }
            }

            // Also clear from session cart data directly
            if (function_exists('WC') && WC()->session) {
                $session_cart = WC()->session->get('cart', array());
                if (!empty($session_cart) && !empty($guest_cart_keys)) {
                    foreach ($guest_cart_keys as $cart_key) {
                        if (isset($session_cart[$cart_key])) {
                            unset($session_cart[$cart_key]);
                        }
                    }
                    WC()->session->set('cart', $session_cart);
                }

                // Clear guest session timer
                WC()->session->set('ijwlp_timer_expiry', null);
            }

            // Also remove from user's persistent cart (in case WooCommerce merged them)
            if (!empty($guest_cart_keys)) {
                $persistent_cart = get_user_meta($user->ID, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);
                if (!empty($persistent_cart) && isset($persistent_cart['cart'])) {
                    $cart_changed = false;
                    foreach ($guest_cart_keys as $cart_key) {
                        if (isset($persistent_cart['cart'][$cart_key])) {
                            unset($persistent_cart['cart'][$cart_key]);
                            $cart_changed = true;
                        }
                    }
                    if ($cart_changed) {
                        update_user_meta($user->ID, '_woocommerce_persistent_cart_' . get_current_blog_id(), $persistent_cart);
                    }
                }
            }

            // Use user's timer
            update_user_meta($user->ID, '_ijwlp_timer_expiry', $user_expiry);
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('ijwlp_timer_expiry', $user_expiry);
            }

            return;
        }



        // CASE 2: User timer EXPIRED or no products
        // → Remove expired user products, keep/use guest products
        if ($user_expiry > 0 && !$user_valid) {
            // User had a timer but it expired - remove their limited products
            
            // Get cart keys for user's blocked products
            $user_blocked = $wpdb->get_col($wpdb->prepare(
                "SELECT cart_key FROM $table WHERE user_id = %s AND status = 'block'",
                $user_id
            ));

            // Delete from woo_limit table
            $wpdb->delete(
                $table,
                array(
                    'user_id' => $user_id,
                    'status' => 'block'
                ),
                array('%s', '%s')
            );

            // Remove from persistent cart (user meta)
            $persistent_cart = get_user_meta($user->ID, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);
            if (!empty($persistent_cart) && isset($persistent_cart['cart']) && !empty($user_blocked)) {
                $cart_changed = false;
                foreach ($user_blocked as $cart_key) {
                    if (isset($persistent_cart['cart'][$cart_key])) {
                        unset($persistent_cart['cart'][$cart_key]);
                        $cart_changed = true;
                    }
                }
                if ($cart_changed) {
                    update_user_meta($user->ID, '_woocommerce_persistent_cart_' . get_current_blog_id(), $persistent_cart);
                }
            }

            // Clear expired user timer
            delete_user_meta($user->ID, '_ijwlp_timer_expiry');
        }

        // Transfer guest products to logged-in user (update user_id in table)
        // Only happens when user has no valid products
        if (strpos($guest_identifier, 'guest_') === 0) {
            $wpdb->update(
                $table,
                array('user_id' => $user_id),  // New user_id
                array(
                    'user_id' => $guest_identifier,
                    'status' => 'block'
                ),
                array('%s'),
                array('%s', '%s')
            );
        }

        // Determine final timer (user expired, so use guest timer if valid)
        $final_expiry = 0;

        if ($session_valid) {
            $final_expiry = $session_expiry;
        }

        // Save the final timer
        if ($final_expiry > 0) {
            update_user_meta($user->ID, '_ijwlp_timer_expiry', $final_expiry);
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('ijwlp_timer_expiry', $final_expiry);
            }
        } else {
            // No valid timer - clear both and products will be removed by frontend
            delete_user_meta($user->ID, '_ijwlp_timer_expiry');
            if (function_exists('WC') && WC()->session) {
                WC()->session->set('ijwlp_timer_expiry', null);
            }
        }
    }



    /**
     * Handle timer on logout
     * Clear session timer so guest gets their own fresh timer when adding products
     * User's timer remains in user meta for when they log back in
     */
    public function handle_logout_timer()
    {
        // Clear session timer - guest should get their own fresh timer
        // User's timer stays in user meta and will be restored on next login
        if (function_exists('WC') && WC()->session) {
            WC()->session->set('ijwlp_timer_expiry', null);
        }
    }

    /**
     * Sync persistent cart's woo_limit field with the actual data in woo_limit table
     * This ensures the persistent cart doesn't have stale limited edition numbers
     * 
     * @param int $wp_user_id WordPress user ID
     * @param string $user_id User identifier string
     */
    public function sync_persistent_cart_with_db($wp_user_id, $user_id)
    {
        global $wpdb, $table_prefix;
        $table = $table_prefix . 'woo_limit';

        // Get persistent cart
        $persistent_cart = get_user_meta($wp_user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), true);
        
        if (empty($persistent_cart) || !isset($persistent_cart['cart']) || empty($persistent_cart['cart'])) {
            return;
        }

        // Get all cart_key => limit_no mappings from woo_limit table for this user
        $db_records = $wpdb->get_results($wpdb->prepare(
            "SELECT cart_key, limit_no FROM $table WHERE user_id = %s AND status = 'block'",
            $user_id
        ), OBJECT_K);

        $cart_changed = false;

        foreach ($persistent_cart['cart'] as $cart_key => &$cart_item) {
            // Check if this cart item has woo_limit data
            if (!isset($cart_item['woo_limit'])) {
                continue;
            }

            // Check if this cart_key exists in the DB
            if (isset($db_records[$cart_key])) {
                // Get the actual numbers from DB
                $db_numbers = $db_records[$cart_key]->limit_no;
                $db_numbers_array = array_map('trim', explode(',', $db_numbers));
                $db_numbers_array = array_filter($db_numbers_array, 'strlen');

                // Get current numbers in cart
                $cart_numbers = is_array($cart_item['woo_limit']) 
                    ? $cart_item['woo_limit'] 
                    : array_map('trim', explode(',', $cart_item['woo_limit']));

                // If they don't match, update cart to match DB (source of truth)
                if ($cart_numbers != $db_numbers_array) {
                    $cart_item['woo_limit'] = $db_numbers_array;
                    
                    // Also update quantity to match number of limited edition numbers
                    $cart_item['quantity'] = count($db_numbers_array);
                    
                    $cart_changed = true;
                }
            } else {
                // Cart item has woo_limit but no record in DB - remove the woo_limit
                // This means the limited edition numbers were released
                unset($cart_item['woo_limit']);
                $cart_changed = true;
            }
        }
        unset($cart_item); // Break reference

        // Save updated persistent cart
        if ($cart_changed) {
            update_user_meta($wp_user_id, '_woocommerce_persistent_cart_' . get_current_blog_id(), $persistent_cart);
        }
    }
}
