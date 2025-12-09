<?php

/**
 * Plugin Name: Woo Limited Products
 * Plugin URI: https://pinetree.ae/
 * Description: Plugin to set limited product numbers for woocommerce.
 * Version: 3.0.2
 * Author: PineTree
 * Developer: Ijas
 * Author URI: https://pinetree.ae/
 * License: GPL2
 * Requires Plugins: woocommerce
 */
if (!defined('ABSPATH'))
    exit;

define('IJWLP_TOKEN', 'ijwlp');
define('IJWLP_VERSION', '3.0.2');
define('IJWLP_FILE', __FILE__);
define('IJWLP_PATH', __DIR__);
define('IJWLP_PLUGIN_NAME', 'Woo Limited Products');
define('IJWLP_PRODUCTS_TRANSIENT_KEY', 'ijwlp_key');
define('IJWLP_WPV', get_bloginfo('version'));

if (!class_exists('Link_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if (!function_exists('ijwlp_autoloader')) {
    function ijwlp_autoloader($class_name)
    {
        if (0 === strpos($class_name, 'IJWLP')) {
            $classes_dir = realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR;
            $class_file = 'class-' . str_replace('_', '-', strtolower($class_name)) . '.php';
            require_once $classes_dir . $class_file;
        }
    }
}

spl_autoload_register('ijwlp_autoloader');

if (!function_exists('woo_limit_activate_plugin')) {
    function woo_limit_activate_plugin()
    {
        global $table_prefix, $wpdb;

        $table_name = $table_prefix . 'woo_limit';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        if (
            $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )) !== $table_name
        ) {

            // SQL to create the table
            $sql = "CREATE TABLE `$table_name` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `cart_key` VARCHAR(500) NOT NULL,
                `user_id` VARCHAR(500) NOT NULL,
                `parent_product_id` VARCHAR(500) NOT NULL,
                `product_id` VARCHAR(500) NOT NULL,
                `product_type` VARCHAR(500) NOT NULL,
                `limit_no` VARCHAR(500),
                `status` VARCHAR(500) NOT NULL,
                `time` VARCHAR(500) NOT NULL,
                `order_id` VARCHAR(500) NOT NULL,
                `order_item_id` VARCHAR(500) NOT NULL,
                `order_status` VARCHAR(500) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }
}

if (!function_exists('IJWLP')) {
    function IJWLP()
    {
        $instance = IJWLP_Backend::instance(IJWLP_FILE, IJWLP_VERSION);
        return $instance;
    }
}

// Load Options class (common) in both admin and frontend
new IJWLP_Options();

new IJWLP_Api();

// Load Custom Order Status (Partially Shipped)
new IJWLP_Order_Status();

// Load Timer Manager (common) in both admin and frontend
new IJWLP_Timer_Manager();

//register activation hook
register_activation_hook(IJWLP_FILE, 'woo_limit_activate_plugin');

// Load backend class only in admin
if (is_admin()) {
    IJWLP();
}

// Load frontend class only on frontend
if (!is_admin()) {
    new IJWLP_Frontend(IJWLP_FILE, IJWLP_VERSION);
}

require_once('updates/init.php'); //Plugin edits
