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

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // Restore cart item data from session
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 10, 2);
        add_action('woocommerce_after_shop_loop_stock_labels', array($this, 'limit_display_shop_loop'), 15, 0);
        add_action('woocommerce_after_shop_loop_stock_labels', array($this, 'out_of_stock_display_shop_loop'), 20, 0);
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
        // Common script - loaded on all relevant pages
        if (is_product() || is_cart() || is_checkout()) {
            wp_enqueue_script(
                'jQuery-autocomplete',
                $this->assets_url . 'js/autocomplete.js',
                array(),
                $this->_version,
                true
            );


            // Enqueue common script first
            wp_enqueue_script(
                'ijwlp-frontend-common',
                $this->assets_url . 'js/frontend-common.js',
                array('jquery'),
                $this->_version,
                true
            );

            // Prepare AJAX data for all scripts
            $ajax_data = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ijwlp_frontend_nonce')
            );

            // Localize script data to common script
            wp_localize_script('ijwlp-frontend-common', 'ijwlp_frontend', $ajax_data);

            // Enqueue timer script
            wp_enqueue_script(
                'ijwlp-frontend-timer',
                $this->assets_url . 'js/frontend-timer.js',
                array('jquery'),
                $this->_version,
                true
            );

            // Product page specific script
            if (is_product()) {
                wp_enqueue_script(
                    'ijwlp-frontend-product',
                    $this->assets_url . 'js/frontend-product.js',
                    array('jquery', 'ijwlp-frontend-common'),
                    $this->_version,
                    true
                );

                // Localize limit time setting for product page
                $limit_time = IJWLP_Options::get_setting('limittime', 15);
                wp_localize_script('ijwlp-frontend-product', 'ijwlp_limit_time', intval($limit_time));
            }

            // Cart page specific script
            if (is_cart()) {
                wp_enqueue_script(
                    'ijwlp-frontend-cart',
                    $this->assets_url . 'js/frontend-cart.js',
                    array('jquery', 'ijwlp-frontend-common'),
                    $this->_version,
                    true
                );
            }

            // Checkout page specific script
            if (is_checkout()) {
                wp_enqueue_script(
                    'ijwlp-frontend-checkout',
                    $this->assets_url . 'js/frontend-checkout.js',
                    array('jquery', 'ijwlp-frontend-common'),
                    $this->_version,
                    true
                );
            }
        }
    }

    /**
     * Enqueue frontend styles
     */
    public function enqueue_styles()
    {
        if (is_product() || is_cart() || is_checkout()) {
            wp_enqueue_style(
                'ijwlp-frontend-style',
                $this->assets_url . 'css/frontend.css',
                array(),
                $this->_version
            );

            // Enqueue timer styles
            wp_enqueue_style(
                'ijwlp-timer-style',
                $this->assets_url . 'css/timer.css',
                array(),
                $this->_version
            );
        }
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
}
