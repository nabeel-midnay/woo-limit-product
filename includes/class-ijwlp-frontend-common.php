<?php

/**
 * WooCommerce Limited Product Plugin - Frontend Common Class
 * 
 * Handles common frontend functionality shared across pages
 * 
 * @package WooCommerce Limited Product
 * @version 1.0.0
 */

if (!defined('ABSPATH'))
    exit;


class IJWLP_Frontend_Common
{
    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;

    /**
     * The plugin file path.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_file;

    /**
     * The assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;

    /**
     * Constructor
     * @param string $file Plugin file path
     * @param string $version Plugin version
     */
    public function __construct($file = '', $version = '1.0.0')
    {
        $this->_version = $version;
        $this->_file = $file;
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->_file)));
        $this->assets_path = IJWLP_PATH . '/assets/';

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // Restore cart item data from session
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        add_action('woocommerce_after_shop_loop_stock_labels', array($this, 'limit_display_shop_loop'), 15, 0);
        add_action('woocommerce_after_shop_loop_stock_labels', array($this, 'out_of_stock_display_shop_loop'), 20, 0);

        // Add backorder help icon
        add_filter('woocommerce_get_stock_html', array($this, 'add_backorder_help_icon'), 10, 2);

        // Add stock status classes to product list items in shop loop
        add_filter('post_class', array($this, 'woo_add_stock_status_post_class'), 20, 3);

        // Render logout modal in footer
        add_action('wp_footer', array($this, 'render_logout_modal'), 10);
    }

    /**
     * Normalize limited number for storage
     * - If array, join into comma-separated string
     * - If scalar, cast to string
     * - If empty, return empty string
     *
     * @param mixed $value
     * @return string
     */
    public static function normalize_limited_number_for_storage($value)
    {
        if (is_array($value)) {
            $parts = array_filter(array_map('strval', $value), function ($v) {
                return $v !== '' && $v !== null;
            });
            return implode(',', $parts);
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Normalize limited number for processing/display
     * - If string, split on commas and trim
     * - If array, return cleaned array
     * - If empty, return empty array
     *
     * @param mixed $value
     * @return array
     */
    public static function normalize_limited_number_for_processing($value)
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value), function ($v) {
                return $v !== '' && $v !== null;
            }));
        }

        if ($value === null || $value === '') {
            return array();
        }

        $parts = array_map('trim', explode(',', (string) $value));
        return array_values(array_filter($parts, function ($v) {
            return $v !== '' && $v !== null;
        }));
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts()
    {

        wp_enqueue_script(
            'jQuery-autocomplete',
            $this->assets_url . 'js/autocomplete.js',
            array(),
            $this->_version,
            true
        );

        $common_js = $this->assets_url . 'js/frontend-common.js';
        $common_js_path = $this->assets_path . 'js/frontend-common.js';

        // Enqueue common script first
        wp_enqueue_script(
            'ijwlp-frontend-common',
            $common_js,
            array('jquery'),
            filemtime($common_js_path),
            true
        );

        // Prepare AJAX data for all scripts
        $ajax_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ijwlp_frontend_nonce')
        );

        // Localize script data to common script
        wp_localize_script('ijwlp-frontend-common', 'ijwlp_frontend', $ajax_data);


        // Product page specific script
        if (is_product()) {
            $product_js = $this->assets_url . 'js/frontend-product.js';
            $product_js_path = $this->assets_path . 'js/frontend-product.js';

            wp_enqueue_script(
                'ijwlp-frontend-product',
                $product_js,
                array('jquery', 'ijwlp-frontend-common'),
                filemtime($product_js_path),
                true
            );

            // Localize limit time setting for product page
            $limit_time = IJWLP_Options::get_setting('limittime', 15);
            wp_localize_script('ijwlp-frontend-product', 'ijwlp_limit_time', intval($limit_time));
        }

        // Cart page specific script
        if (is_cart()) {
            $cart_js = $this->assets_url . 'js/frontend-cart.js';
            $cart_js_path = $this->assets_path . 'js/frontend-cart.js';

            wp_enqueue_script(
                'ijwlp-frontend-cart',
                $cart_js,
                array('jquery', 'ijwlp-frontend-common'),
                filemtime($cart_js_path),
                true
            );
        }

        // Checkout page specific script
        if (is_checkout()) {
            $checkout_js = $this->assets_url . 'js/frontend-checkout.js';
            $checkout_js_path = $this->assets_path . 'js/frontend-checkout.js';

            wp_enqueue_script(
                'ijwlp-frontend-checkout',
                $checkout_js,
                array('jquery', 'ijwlp-frontend-common'),
                filemtime($checkout_js_path),
                true
            );
        } else {
            // Enqueue timer script
            $timer_js = $this->assets_url . 'js/frontend-timer.js';
            $timer_js_path = $this->assets_path . 'js/frontend-timer.js';
            wp_enqueue_script(
                'ijwlp-frontend-timer',
                $timer_js,
                array('jquery'),
                filemtime($timer_js_path),
                true
            );
        }

    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles()
    {

        $frontend = $this->assets_url . 'css/frontend.css';
        $frontend_path = $this->assets_path . 'css/frontend.css';

        wp_enqueue_style(
            'ijwlp-frontend-style',
            $frontend,
            array(),
            filemtime($frontend_path)
        );

        // Enqueue timer styles
        wp_enqueue_style(
            'ijwlp-timer-style',
            $this->assets_url . 'css/timer.css',
            array(),
            $this->_version
        );

    }

    /**
     * Restore cart item data from session
     */
    public function get_cart_item_from_session($cart_item, $values)
    {
        if (isset($values['woo_limit'])) {
            // Ensure we restore a normalized string for storage/processing
            $cart_item['woo_limit'] = self::normalize_limited_number_for_storage($values['woo_limit']);
        }
        // Restore display variant if available (keeps UI consistent)
        if (isset($values['woo_limit_display'])) {
            $cart_item['woo_limit_display'] = self::normalize_limited_number_for_storage($values['woo_limit_display']);
        }
        // Restore stored product id key used by add-to-cart
        if (isset($values['woo_limit_pro_id'])) {
            $cart_item['woo_limit_pro_id'] = $values['woo_limit_pro_id'];
        }
        return $cart_item;
    }

    public function limit_display_shop_loop()
    {
        global $product;
        $product_id = get_the_ID();

        // Check if this product has limited edition feature enabled
        $status = get_post_meta($product_id, '_woo_limit_status', true);

        // Only check for limited edition availability if the product has limited edition enabled
        if ($status == 'yes') {
            $limitedNosAvailableCount = limitedNosAvailableCount($product_id);
            if ($limitedNosAvailableCount == 0) {
                echo '<div class="soldout_wrapper shop-loop-soldout"><span class="soldout-label">' . esc_html__('Sold Out', 'woolimit') . '</span></div>';
            }
        }
    }

    public function out_of_stock_display_shop_loop()
    {
        global $product;

        // Check if product is out of stock
        if (!$product->is_in_stock()) {
            echo '<div class="outofstock_wrapper shop-loop-outofstock"><span class="outofstock-label">' . esc_html__('Out of Stock', 'woolimit') . '</span></div>';
        }
    }

    /**
     * Add backorder help icon to stock display on product single page and cart page
     * 
     * @param string $html The stock HTML
     * @param WC_Product $product The product object
     * @return string Modified stock HTML
     */
    public function add_backorder_help_icon($html, $product)
    {
        // Add on product single page and cart page
        if (!is_product() && !is_cart()) {
            return $html;
        }

        // Check if this is a backorder message (both product page and cart page formats)
        if (strpos($html, 'available-on-backorder') !== false || strpos($html, 'backorder_notification') !== false) {
            // Wrap the text with backorder-help-text and add help icon
            $html = str_replace(
                'Available on backorder',
                '<span class="backorder-help-text">Available on backorder</span><span class="help-icon" data-tooltip="' . esc_attr__('Available on backorder means that this particular product/size is currently not in stock. However, it can be ordered and will be delivered as soon as available (usually 10 days).', 'woocommerce') . '">?</span>',
                $html
            );
        }

        return $html;
    }

    /**
     * Add stock status classes to product <li> elements in shop/catalog loops.
     * - Adds 'product-soldout' when limited numbers are enabled and none are available
     * - Adds 'product-outofstock' when WooCommerce product stock is out
     */
    public function woo_add_stock_status_post_class($classes, $class, $post_id)
    {
        // Only target products in loops
        if (get_post_type($post_id) !== 'product') {
            return $classes;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return $classes;
        }

        // Limited edition sold-out (custom logic in this plugin)
        $status = get_post_meta($post_id, '_woo_limit_status', true);
        if ($status === 'yes') {
            $limitedNosAvailableCount = function_exists('limitedNosAvailableCount') ? limitedNosAvailableCount($post_id) : null;
            if ($limitedNosAvailableCount !== null && (int) $limitedNosAvailableCount === 0) {
                $classes[] = 'product-soldout';
            }
        }

        // Standard WooCommerce out-of-stock
        if (!$product->is_in_stock()) {
            $classes[] = 'product-outofstock';
        }

        return array_values(array_unique($classes));
    }



    /**
     * Render logout confirmation modal
     */
    public function render_logout_modal()
    {
        ?>
        <div id="woo-limit-logout-modal" class="field-selection-modal" style="display:none;">
            <div class="field-selection-modal-content" style="max-width: 400px; text-align: center;">
                <h3 class="field-selection-title" style="margin-top: 0; color: #333; margin-bottom: 20px;">Leaving so soon?</h3>
                <div class="field-selection-buttons" style="display: flex; justify-content: center; gap: 10px;">
                    <button class="remove-selected-field" id="woo-limit-logout-confirm" style="margin-right: 0;">Yes</button>
                    <button class="cancel-field-selection" id="woo-limit-logout-cancel" style="margin-right: 0;">No</button>
                </div>
            </div>
        </div>
        <?php
    }

}
