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

            // Product page specific script
            if (is_product()) {
                wp_enqueue_script(
                    'ijwlp-frontend-product',
                    $this->assets_url . 'js/frontend-product.js',
                    array('jquery', 'ijwlp-frontend-common'),
                    $this->_version,
                    true
                );
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
        }
    }

    /**
     * Restore cart item data from session
     */
    public function get_cart_item_from_session($cart_item, $values)
    {
        if (isset($values['woo_limit'])) {
            $cart_item['woo_limit'] = $values['woo_limit'];
        }
        if (isset($values['limited_edition_pro_id'])) {
            $cart_item['limited_edition_pro_id'] = $values['limited_edition_pro_id'];
        }
        return $cart_item;
    }
}
