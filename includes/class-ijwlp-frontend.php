<?php

/**
 * WooCommerce Limited Product Plugin - Frontend Class
 * 
 * Main frontend class that initializes separate page-specific classes
 * 
 * @package WooCommerce Limited Product
 * @version 1.0.0
 */

if (!defined('ABSPATH'))
    exit;

class IJWLP_Frontend
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
     * Common frontend instance
     * @var     IJWLP_Frontend_Common
     * @access  public
     * @since   1.0.0
     */
    public $common;

    /**
     * Product page frontend instance
     * @var     IJWLP_Frontend_Product
     * @access  public
     * @since   1.0.0
     */
    public $product;

    /**
     * Cart page frontend instance
     * @var     IJWLP_Frontend_Cart
     * @access  public
     * @since   1.0.0
     */
    public $cart;

    /**
     * Checkout page frontend instance
     * @var     IJWLP_Frontend_Checkout
     * @access  public
     * @since   1.0.0
     */
    public $checkout;

    /**
     * Constructor
     * @param string $file Plugin file path
     * @param string $version Plugin version
     */
    public function __construct($file = '', $version = '1.0.0')
    {
        $this->_version = $version;
        $this->_file = $file;

        // Initialize common functionality (scripts, styles, session handling)
        $this->common = new IJWLP_Frontend_Common($file, $version);

        // Initialize product page functionality
        $this->product = new IJWLP_Frontend_Product();

        // Initialize cart page functionality
        $this->cart = new IJWLP_Frontend_Cart();

        // Initialize checkout page functionality
        $this->checkout = new IJWLP_Frontend_Checkout();
    }
}
