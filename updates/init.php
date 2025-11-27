<?php if ( ! defined( 'ABSPATH' ) ) exit; 

global $woocommerce;

function my_admin_menu() {

    add_menu_page(__( 'Woo Limit', 'ijwlp-woo' ),__( 'Woo Limit', 'ijwlp-woo' ),'manage_options','woo-limit','woolimit_main_page','dashicons-schedule',3);

    add_submenu_page('woo-limit', 'Orders', 'Orders', 'manage_options', 'woo-limit-orders-new', $callback = 'woolimit_sub_page_orders', $position = null );

    add_submenu_page('woo-limit', 'Reports', 'Reports', 'manage_options', 'woo-limit-reports', $callback = 'woolimit_sub_page_reports', $position = null );

}

function woolimit_sub_page_orders(){

     ?>

    <h1><?php esc_html_e( 'Orders', 'my-plugin-textdomain' ); ?></h1>

    <h3><?php esc_html_e( 'Woo Limited Product Orders', 'my-plugin-textdomain' ); ?></h3>

    <?php require_once(plugin_dir_path( __FILE__ ) . 'woo-orders.php'); ?>

    <?php

}

function woolimit_sub_page_reports(){

     ?>

    <h1><?php esc_html_e( 'Reports', 'my-plugin-textdomain' ); ?></h1>

    <h3><?php //esc_html_e( 'Woo Limited Product Orders', 'my-plugin-textdomain' ); ?></h3>

    <?php require_once(plugin_dir_path( __FILE__ ) . 'woo-reports.php'); ?>

    <?php

}

/******************************** Pages END ******************************/

add_action( 'admin_menu', 'my_admin_menu' );

function woolimit_main_page() {

    ?>

    <h1><?php esc_html_e( 'Settings', 'my-plugin-textdomain' ); ?></h1>

    <h3><?php esc_html_e( 'Setup Woo Limited Product options', 'my-plugin-textdomain' ); ?></h3>

    <?php require_once(plugin_dir_path( __FILE__ ) . 'cPanel.php'); ?>

    <?php

}

function register_my_plugin_scripts() {

    wp_register_style( 'woo-limit-datatable_css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', false, '1.0.0' );

    wp_enqueue_style( 'woo-limit-datatable_css' );

    

    wp_enqueue_script( 'woo-limit-datatable2', 'https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js' );

    wp_enqueue_script( 'woo-limit-datatable3', 'https://cdn.datatables.net/select/1.3.4/js/dataTables.select.js' );

    wp_enqueue_script( 'woo-limit-datatable-export', plugins_url('tableExport.js?v='.time(),__FILE__ ));

    wp_enqueue_script( 'woo-limit-custom_script', plugins_url('custom-script.js?v='.time(),__FILE__ ));

}

add_action( 'admin_enqueue_scripts', 'register_my_plugin_scripts' );


/*****Addition to the plugin functionality - added on 12th Nov 2023****/



/**************** Reports functions*******************/

function limitedNosSold($pid){

    global $table_prefix, $wpdb;

    $table = $table_prefix . 'woo_limit';

    $limit_no 	= $wpdb->get_col( "SELECT limit_no FROM $table WHERE parent_product_id = $pid AND ( status = 'block' OR status = 'ordered' )" );

    $limit_noSTR = '';

    //print_r($limit_no);

    if($limit_no){

        foreach($limit_no as $ln){

            $limit_noSTR .= $ln.',';

        }

    }

    $limit_noSTR = rtrim($limit_noSTR, ",");

    $numbersArray = explode(',', $limit_noSTR);

    sort($numbersArray, SORT_NUMERIC);

    $sortedNumbersString = implode(',', $numbersArray);

    return $sortedNumbersString;

}

/**
 * Enhanced function to get sold numbers with order ID logging
 * This function logs the corresponding order IDs for debugging purposes
 */
function limitedNosSoldWithOrderLog($pid){

    global $table_prefix, $wpdb;

    $table = $table_prefix . 'woo_limit';

    // Get detailed information including order IDs
    $results = $wpdb->get_results("SELECT limit_no, order_id, order_item_id, status, user_id, time FROM $table WHERE parent_product_id = $pid AND ( status = 'block' OR status = 'ordered' )");

    $limit_noSTR = '';
    $order_log = array();

    if($results){

        foreach($results as $row){

            $limit_noSTR .= $row->limit_no . ',';

            // Log order information for each sold number
            $numbers = explode(',', $row->limit_no);
            foreach($numbers as $number) {
                $number = trim($number);
                if(!empty($number)) {
                    $order_log[] = array(
                        'number' => $number,
                        'order_id' => $row->order_id,
                        'order_item_id' => $row->order_item_id,
                        'status' => $row->status,
                        'user_id' => $row->user_id,
                        'time' => $row->time
                    );
                }
            }

        }

    }

    $limit_noSTR = rtrim($limit_noSTR, ",");

    $numbersArray = explode(',', $limit_noSTR);

    sort($numbersArray, SORT_NUMERIC);

    $sortedNumbersString = implode(',', $numbersArray);

    // Log the order information
    if(!empty($order_log)) {
        error_log('=== LIMITED PRODUCT SOLD NUMBERS WITH ORDER IDs ===');
        error_log('Product ID: ' . $pid);
        error_log('Product Name: ' . get_the_title($pid));
        error_log('Total Sold Numbers: ' . count($numbersArray));
        error_log('Sold Numbers: ' . $sortedNumbersString);
        error_log('--- ORDER DETAILS ---');
        
        foreach($order_log as $log_entry) {
            $order_status = ($log_entry['order_id'] == 'block') ? 'BLOCKED (in cart)' : 'ORDERED';
            $order_link = ($log_entry['order_id'] != 'block') ? admin_url('post.php?post=' . $log_entry['order_id'] . '&action=edit') : 'N/A';
            
            error_log(sprintf(
                'Number: %s | Order ID: %s | Status: %s | User ID: %s | Time: %s | Order Link: %s',
                $log_entry['number'],
                $log_entry['order_id'],
                $order_status,
                $log_entry['user_id'],
                $log_entry['time'],
                $order_link
            ));
        }
        error_log('=== END ORDER LOG ===');
    }

    return $sortedNumbersString;

}

/**
 * Get detailed sold information with order IDs for a specific product
 * Returns array with number details and order information
 */
function getSoldNumbersWithOrderDetails($pid){

    global $table_prefix, $wpdb;

    $table = $table_prefix . 'woo_limit';

    $results = $wpdb->get_results("SELECT limit_no, order_id, order_item_id, status, user_id, time, product_id FROM $table WHERE parent_product_id = $pid AND ( status = 'block' OR status = 'ordered' )");

    $sold_details = array();
    $processed_numbers = array(); // Track processed numbers to avoid duplicates

    if($results){

        foreach($results as $row){

            $numbers = explode(',', $row->limit_no);
            foreach($numbers as $number) {
                $number = trim($number);
                if(!empty($number) && !in_array($number, $processed_numbers)) {
                    
                    // Check if this number has a cart key as order_id and try to fix it
                    $order_id = $row->order_id;
                    $order_status = 'ORDERED';
                    $order_link = 'N/A';
                    
                    if ($row->order_id == 'block') {
                        $order_status = 'BLOCKED (in cart)';
                    } elseif (strlen($row->order_id) > 10 && preg_match('/^[a-f0-9]+$/', $row->order_id)) {
                        // This looks like a cart key, try to find the actual order ID
                        $actual_order = $wpdb->get_var($wpdb->prepare("
                            SELECT order_id 
                            FROM {$wpdb->prefix}woocommerce_order_itemmeta 
                            WHERE meta_key = '_cart_item_key' 
                            AND meta_value = %s
                        ", $row->order_id));
                        
                        if ($actual_order) {
                            $order_id = $actual_order;
                            $order_link = admin_url('post.php?post=' . $actual_order . '&action=edit');
                            
                            // Update the database record with the correct order ID
                            $wpdb->update(
                                $table_prefix . 'woo_limit',
                                array('order_id' => $actual_order),
                                array('id' => $row->id),
                                array('%s'),
                                array('%d')
                            );
                            
                            error_log('Woo Limit: Auto-fixed order_id for number ' . $number . ' from cart key "' . $row->order_id . '" to order "' . $actual_order . '"');
                        } else {
                            $order_status = 'CART KEY (no order found)';
                        }
                    } else {
                        $order_link = admin_url('post.php?post=' . $row->order_id . '&action=edit');
                    }
                    
                    $sold_details[] = array(
                        'number' => $number,
                        'order_id' => $order_id,
                        'order_item_id' => $row->order_item_id,
                        'status' => $row->status,
                        'user_id' => $row->user_id,
                        'time' => $row->time,
                        'product_id' => $row->product_id,
                        'order_status' => $order_status,
                        'order_link' => $order_link
                    );
                    
                    $processed_numbers[] = $number; // Mark this number as processed
                }
            }

        }

    }

    return $sold_details;

}

/**
 * Debug function to log all sold numbers with order IDs for all products
 */
function debugAllSoldNumbersWithOrders(){

    global $table_prefix, $wpdb;

    $table = $table_prefix . 'woo_limit';

    // Get all products with limited edition status
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => array('publish'),
        'meta_query' => array(
            array(
                'key' => '_woo_limit_status',
                'value' => 'yes',
            ),
        ),
    );

    $products_query = new WP_Query($args);

    error_log('=== COMPREHENSIVE SOLD NUMBERS DEBUG LOG ===');
    error_log('Total Limited Products Found: ' . $products_query->post_count);

    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product_id = get_the_ID();
            
            // Use the enhanced function to log order details
            limitedNosSoldWithOrderLog($product_id);
        }
        wp_reset_postdata();
    }

    error_log('=== END COMPREHENSIVE DEBUG LOG ===');
}

/**
 * Admin action to trigger debug logging
 * Usage: Add ?debug_sold_orders=1 to any admin page URL
 */
add_action('admin_init', 'debug_sold_orders_action');
function debug_sold_orders_action() {
    if (isset($_GET['debug_sold_orders']) && $_GET['debug_sold_orders'] == '1') {
        if (current_user_can('manage_options')) {
            debugAllSoldNumbersWithOrders();
            wp_die('Debug logging completed. Check your error log for details.');
        }
    }
}

/**
 * Debug function for a specific product
 * Usage: Add ?debug_product_orders=PRODUCT_ID to any admin page URL
 */
add_action('admin_init', 'debug_product_orders_action');
function debug_product_orders_action() {
    if (isset($_GET['debug_product_orders']) && is_numeric($_GET['debug_product_orders'])) {
        if (current_user_can('manage_options')) {
            $product_id = intval($_GET['debug_product_orders']);
            limitedNosSoldWithOrderLog($product_id);
            wp_die('Debug logging completed for Product ID: ' . $product_id . '. Check your error log for details.');
        }
    }
}

/**
 * Add admin notice with debug links
 */
add_action('admin_notices', 'add_debug_notice');
function add_debug_notice() {
    if (current_user_can('manage_options') && isset($_GET['page']) && strpos($_GET['page'], 'woo-limit') !== false) {
        $current_url = admin_url('admin.php?page=' . $_GET['page']);
        echo '<div class="notice notice-info">';
        echo '<p><strong>Woo Limit Debug Tools:</strong> ';
        echo '<a href="' . $current_url . '&debug_sold_orders=1" class="button button-small">Debug All Products</a> ';
        echo '<span style="margin-left: 10px;">Add ?debug_product_orders=PRODUCT_ID to debug specific product</span>';
        echo '</p>';
        echo '</div>';
    }
}

/**
 * Clean up function to fix existing records with cart keys as order IDs
 * This function will help fix the current data structure
 */
function cleanup_cart_key_order_ids() {
    global $table_prefix, $wpdb;
    $table = $table_prefix . 'woo_limit';
    
    // Find records where order_id looks like a cart key (long alphanumeric string)
    $cart_key_records = $wpdb->get_results("
        SELECT id, order_id, cart_key, product_id, limit_no, status 
        FROM $table 
        WHERE order_id != 'block' 
        AND order_id != 'NA' 
        AND LENGTH(order_id) > 10 
        AND order_id REGEXP '^[a-f0-9]+$'
    ");
    
    if (!empty($cart_key_records)) {
        error_log('Woo Limit: Found ' . count($cart_key_records) . ' records with cart keys as order IDs');
        
        foreach ($cart_key_records as $record) {
            // Check if this cart key exists in any actual orders
            $actual_order = $wpdb->get_var($wpdb->prepare("
                SELECT order_id 
                FROM {$wpdb->prefix}woocommerce_order_itemmeta 
                WHERE meta_key = '_cart_item_key' 
                AND meta_value = %s
            ", $record->order_id));
            
            if ($actual_order) {
                // Update the record with the actual order ID
                $update_result = $wpdb->update(
                    $table,
                    array('order_id' => $actual_order),
                    array('id' => $record->id),
                    array('%s'),
                    array('%d')
                );
                
                if ($update_result !== false) {
                    error_log('Woo Limit: Fixed record ID ' . $record->id . ' - Updated order_id from "' . $record->order_id . '" to "' . $actual_order . '"');
                } else {
                    error_log('Woo Limit: Failed to fix record ID ' . $record->id);
                }
            } else {
                // If no actual order found, reset to 'block' status
                $update_result = $wpdb->update(
                    $table,
                    array(
                        'order_id' => 'block',
                        'status' => 'block'
                    ),
                    array('id' => $record->id),
                    array('%s', '%s'),
                    array('%d')
                );
                
                if ($update_result !== false) {
                    error_log('Woo Limit: Reset record ID ' . $record->id . ' - No actual order found for cart key "' . $record->order_id . '"');
                } else {
                    error_log('Woo Limit: Failed to reset record ID ' . $record->id);
                }
            }
        }
    } else {
        error_log('Woo Limit: No records found with cart keys as order IDs');
    }
}

/**
 * Admin action to trigger cleanup of cart key order IDs
 * Usage: Add ?cleanup_cart_keys=1 to any admin page URL
 */
add_action('admin_init', 'cleanup_cart_keys_action');
function cleanup_cart_keys_action() {
    if (isset($_GET['cleanup_cart_keys']) && $_GET['cleanup_cart_keys'] == '1') {
        if (current_user_can('manage_options')) {
            cleanup_cart_key_order_ids();
            wp_die('Cart key cleanup completed. Check your error log for details.');
        }
    }
}

/**
 * Auto-cleanup cart keys when reports page is loaded
 */
add_action('admin_init', 'auto_cleanup_cart_keys_on_reports');
function auto_cleanup_cart_keys_on_reports() {
    if (current_user_can('manage_options') && 
        isset($_GET['page']) && 
        strpos($_GET['page'], 'woo-limit') !== false &&
        !isset($_GET['debug_sold_orders']) && 
        !isset($_GET['debug_product_orders']) && 
        !isset($_GET['cleanup_cart_keys'])) {
        
        // Run cleanup silently
        cleanup_cart_key_order_ids();
    }
}

/**
 * Debug function to show current order ID state
 * Usage: Add ?debug_order_ids=1 to any admin page URL
 */
add_action('admin_init', 'debug_order_ids_action');
function debug_order_ids_action() {
    if (isset($_GET['debug_order_ids']) && $_GET['debug_order_ids'] == '1') {
        if (current_user_can('manage_options')) {
            global $table_prefix, $wpdb;
            $table = $table_prefix . 'woo_limit';
            
            echo '<h2>Current Order ID State</h2>';
            
            // Get all records with their order IDs
            $results = $wpdb->get_results("
                SELECT id, product_id, limit_no, order_id, status, cart_key 
                FROM $table 
                WHERE order_id != 'block' 
                ORDER BY id DESC 
                LIMIT 50
            ");
            
            if (!empty($results)) {
                echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
                echo '<tr><th>ID</th><th>Product ID</th><th>Limit Numbers</th><th>Order ID</th><th>Status</th><th>Cart Key</th><th>Type</th></tr>';
                
                foreach ($results as $row) {
                    $type = 'Normal';
                    if (strlen($row->order_id) > 10 && preg_match('/^[a-f0-9]+$/', $row->order_id)) {
                        $type = 'Cart Key (NEEDS FIX)';
                    } elseif ($row->order_id == 'NA') {
                        $type = 'NA';
                    }
                    
                    echo '<tr>';
                    echo '<td>' . $row->id . '</td>';
                    echo '<td>' . $row->product_id . '</td>';
                    echo '<td>' . $row->limit_no . '</td>';
                    echo '<td>' . $row->order_id . '</td>';
                    echo '<td>' . $row->status . '</td>';
                    echo '<td>' . $row->cart_key . '</td>';
                    echo '<td>' . $type . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            } else {
                echo '<p>No records found.</p>';
            }
            
            wp_die();
        }
    }
}


/**
 * Manual order ID update for specific order
 * Usage: Add ?fix_order_id=ORDER_ID to any admin page URL
 */
add_action('admin_init', 'fix_specific_order_id_action');
function fix_specific_order_id_action() {
    if (isset($_GET['fix_order_id']) && is_numeric($_GET['fix_order_id'])) {
        if (current_user_can('manage_options')) {
            $order_id = intval($_GET['fix_order_id']);
            
            // Get the front-end class instance and call the update function
            global $ijwlp_front_end;
            if (isset($ijwlp_front_end)) {
                $ijwlp_front_end->update_order_id_from_cart_key($order_id);
            } else {
                // Create a temporary instance if needed
                $options = new IJWLP_Options();
                $front_end = new IJWLP_Front_End($options, __FILE__, IJWLP_VERSION);
                $front_end->update_order_id_from_cart_key($order_id);
            }
            
            wp_die('Order ID fix completed for order ' . $order_id . '. Check error logs for details.');
        }
    }
}

/**
 * Debug specific order state
 * Usage: Add ?debug_order=ORDER_ID to any admin page URL
 */
add_action('admin_init', 'debug_specific_order_action');
function debug_specific_order_action() {
    if (isset($_GET['debug_order']) && is_numeric($_GET['debug_order'])) {
        if (current_user_can('manage_options')) {
            $order_id = intval($_GET['debug_order']);
            global $table_prefix, $wpdb;
            $table = $table_prefix . 'woo_limit';
            
            echo '<h2>Debug Order ' . $order_id . '</h2>';
            
            // Get order object
            $order = wc_get_order($order_id);
            if (!$order) {
                echo '<p>Order not found.</p>';
                wp_die();
            }
            
            echo '<h3>Order Details:</h3>';
            echo '<p>Status: ' . $order->get_status() . '</p>';
            echo '<p>Date: ' . $order->get_date_created()->format('Y-m-d H:i:s') . '</p>';
            
            // Get order items
            $items = $order->get_items();
            echo '<h3>Order Items:</h3>';
            foreach ($items as $item) {
                $item_id = $item->get_id();
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $cart_item_key = $item->get_meta('_cart_item_key');
                $limited_number = $item->get_meta('Limited Edition Number');
                
                echo '<p>Item ID: ' . $item_id . '</p>';
                echo '<p>Product ID: ' . $product_id . '</p>';
                echo '<p>Variation ID: ' . $variation_id . '</p>';
                echo '<p>Cart Item Key: ' . ($cart_item_key ? $cart_item_key : 'NOT FOUND') . '</p>';
                echo '<p>Limited Number: ' . ($limited_number ? $limited_number : 'NOT FOUND') . '</p>';
                echo '<hr>';
            }
            
            // Get database records
            $records = $wpdb->get_results($wpdb->prepare("
                SELECT id, product_id, limit_no, order_id, status, cart_key, order_item_id 
                FROM $table 
                WHERE order_id = %s OR cart_key IN (
                    SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta 
                    WHERE order_id = %d AND meta_key = '_cart_item_key'
                )
            ", $order_id, $order_id));
            
            echo '<h3>Database Records:</h3>';
            if (!empty($records)) {
                echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
                echo '<tr><th>ID</th><th>Product ID</th><th>Limit Numbers</th><th>Order ID</th><th>Status</th><th>Cart Key</th><th>Order Item ID</th></tr>';
                
                foreach ($records as $record) {
                    echo '<tr>';
                    echo '<td>' . $record->id . '</td>';
                    echo '<td>' . $record->product_id . '</td>';
                    echo '<td>' . $record->limit_no . '</td>';
                    echo '<td>' . $record->order_id . '</td>';
                    echo '<td>' . $record->status . '</td>';
                    echo '<td>' . $record->cart_key . '</td>';
                    echo '<td>' . $record->order_item_id . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            } else {
                echo '<p>No database records found for this order.</p>';
            }
            
            wp_die();
        }
    }
}

function limitedNosSoldCount($pid){

    $limitedNosSold = limitedNosSold($pid);

    $limitedNosSoldArray = explode(',', $limitedNosSold);

    if (empty($limitedNosSold)) {

        return 0;

    }

    return (count($limitedNosSoldArray));

}

function limitedNosAvailable($pid){

    global $wpdb, $post;

    $existingNumbersString = limitedNosSold($pid);//"1,34,12,98,2,12";

    $existingNumbersArray = explode(',', $existingNumbersString);

    $start      = get_post_meta($pid,'_woo_limit_start_value',true);

    $end        = get_post_meta($pid,'_woo_limit_end_value',true);

    $fullRange = range($start, $end);

    $excludedNumbers = array_diff($fullRange, $existingNumbersArray);

    return implode(', ', $excludedNumbers);

}

function limitedNosAvailableCount($pid){

    $limitedNosAvailable = limitedNosAvailable($pid);

    $limitedNosAvailableArray = explode(', ', $limitedNosAvailable);

    if (empty($limitedNosAvailable)) {

        return 0;

    }

    return (count($limitedNosAvailableArray));

}
/************************************************************/

// Add a meta box for 'color' attribute images

add_action('add_meta_boxes', 'add_color_attribute_images_meta_box');



function add_color_attribute_images_meta_box() {

    global $post;

    

    // Check if the product has variations with the 'color' attribute

    $has_color_variations = false;

    

    if ($post && $post->post_type === 'product') {

        $product = wc_get_product($post->ID);

        

        if ($product && $product->is_type('variable')) {

            foreach ($product->get_attributes() as $attribute) {

                if ($attribute->get_name() === 'color' || $attribute->get_name() === 'pa_color') {

                    $has_color_variations = true;

                    break;

                }

            }

        }

    }

    

    // Display the meta box only if the product has 'color' variations

    if ($has_color_variations) {

        add_meta_box(

            'color_attribute_images_meta_box',

            __('Color Attribute Images for Cart', 'your-text-domain'),

            'color_attribute_images_meta_box_content',

            'product',

            'normal',

            'high'

        );

    }

}



function color_attribute_images_meta_box_content($post) {

    // Output the form fields for each 'color' attribute term

    $color_terms = get_terms(array('taxonomy' => 'pa_color', 'hide_empty' => false));



    if (!empty($color_terms) && !is_wp_error($color_terms)) {

        foreach ($color_terms as $color_term) {

            $variation_image = get_post_meta($post->ID, '_variation_image_' . $color_term->slug, true);

            ?>

            <p>

                <label for="variation_image_<?php echo esc_attr($color_term->slug); ?>">

                    <?php echo esc_html($color_term->name); ?>:

                </label>

                <input type="text" id="variation_image_<?php echo esc_attr($color_term->slug); ?>" name="variation_image[<?php echo esc_attr($color_term->slug); ?>]" value="<?php echo esc_attr($variation_image); ?>" />

                <button type="button" class="button upload_image_button" data-target="#variation_image_<?php echo esc_attr($color_term->slug); ?>">Upload Image</button>

            </p>

            <?php

        }

    }

}



// Save meta box data

add_action('save_post_product', 'save_color_attribute_images_meta_box');



function save_color_attribute_images_meta_box($post_id) {

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $color_terms = get_terms(array('taxonomy' => 'pa_color', 'hide_empty' => false));
    if (!empty($color_terms) && !is_wp_error($color_terms)) {

        foreach ($color_terms as $color_term) {

            $meta_key = '_variation_image_' . $color_term->slug;



            if (isset($_POST['variation_image'][$color_term->slug])) {

                $variation_image = sanitize_text_field($_POST['variation_image'][$color_term->slug]);

                update_post_meta($post_id, $meta_key, $variation_image);

            }

        }

    }

}









/************************** End of changes *******************************/

/**

 * Get all selected variants for a cart item in WooCommerce.

 *

 * @param string $cart_item_key Cart item key.

 * @return array|bool Array of variation details if found, false otherwise.

 */

function get_all_selected_variants_for_cart_item($cart_item_key) {

    $cart = WC()->cart->get_cart();



    if (isset($cart[$cart_item_key])) {

        $cart_item = $cart[$cart_item_key];



        // Check if the cart item has variation data

        if (isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0) {

            $variation = wc_get_product($cart_item['variation_id']);



            if ($variation) {

                // Retrieve variation attributes and values

                $variation_attributes = $variation->get_variation_attributes();

                $variation_values = array();



                foreach ($variation_attributes as $attribute_name => $attribute_value) {

                    $taxonomy = wc_attribute_taxonomy_name($attribute_name);

                    $term = get_term_by('slug', $attribute_value, $taxonomy);



                    if ($term && !is_wp_error($term)) {

                        $variation_values[$attribute_name] = $term->name;

                    }

                }



                return array(

                    'variation_id' => $cart_item['variation_id'],

                    'variation_attributes' => $variation_values,

                );

            }

        }

    }



    return false;

}



/*****************/

/**
 * Manual fix for cart data issues
 * Usage: Add ?fix_cart_data=1 to any admin page URL
 */
add_action('admin_init', 'fix_cart_data_action');
function fix_cart_data_action() {
    if (isset($_GET['fix_cart_data']) && $_GET['fix_cart_data'] == '1') {
        if (current_user_can('manage_options')) {
            global $table_prefix, $wpdb;
            $table = $table_prefix . 'woo_limit';
            
            echo '<h2>Fixing Cart Data Issues</h2>';
            
            // Get all blocked records
            $blocked_records = $wpdb->get_results("
                SELECT id, product_id, limit_no, order_id, status, cart_key, user_id, time 
                FROM $table 
                WHERE status = 'block' 
                ORDER BY id DESC
            ");
            
            if (!empty($blocked_records)) {
                echo '<h3>Current Blocked Records:</h3>';
                echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
                echo '<tr><th>ID</th><th>Product ID</th><th>Limit Numbers</th><th>Order ID</th><th>Status</th><th>Cart Key</th><th>User ID</th><th>Time</th></tr>';
                
                foreach ($blocked_records as $record) {
                    echo '<tr>';
                    echo '<td>' . $record->id . '</td>';
                    echo '<td>' . $record->product_id . '</td>';
                    echo '<td>' . $record->limit_no . '</td>';
                    echo '<td>' . $record->order_id . '</td>';
                    echo '<td>' . $record->status . '</td>';
                    echo '<td>' . $record->cart_key . '</td>';
                    echo '<td>' . $record->user_id . '</td>';
                    echo '<td>' . $record->time . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                
                // Clear all blocked records - WARNING: This is a global operation that affects all users
                // Only use this for admin maintenance purposes
                if (current_user_can('manage_options')) {
                    $deleted = $wpdb->delete($table, array('status' => 'block'));
                    echo '<p><strong>Deleted ' . $deleted . ' blocked records globally. WARNING: This affects all users!</strong></p>';
                } else {
                    echo '<p><strong>Insufficient permissions to clear blocked records.</strong></p>';
                }
                
                // Clear WooCommerce cart session data
                if (WC()->session) {
                    WC()->session->__unset('woo_limit_data');
                    WC()->session->save_data();
                    echo '<p><strong>Cleared WooCommerce session data.</strong></p>';
                }
                
                echo '<p><strong>Cart data has been cleared. Users will need to re-enter their limited numbers.</strong></p>';
            } else {
                echo '<p>No blocked records found.</p>';
            }
            
            wp_die();
        }
    }
}

/**
 * Debug cart session data
 * Usage: Add ?debug_cart_session=1 to any admin page URL
 */
add_action('admin_init', 'debug_cart_session_action');
function debug_cart_session_action() {
    if (isset($_GET['debug_cart_session']) && $_GET['debug_cart_session'] == '1') {
        if (current_user_can('manage_options')) {
            echo '<h2>Cart Session Debug</h2>';
            
            if (WC()->session) {
                $session_data = WC()->session->get_session_data();
                echo '<h3>WooCommerce Session Data:</h3>';
                echo '<pre>' . print_r($session_data, true) . '</pre>';
                
                // Check for cart contents
                if (WC()->cart) {
                    $cart_contents = WC()->cart->get_cart_contents();
                    echo '<h3>Cart Contents:</h3>';
                    echo '<pre>' . print_r($cart_contents, true) . '</pre>';
                }
            } else {
                echo '<p>No WooCommerce session found.</p>';
            }
            
            wp_die();
        }
    }
}

/**
 * Manual fix for orders missing limited edition numbers
 * Usage: Add ?fix_order_numbers=ORDER_ID to any admin page URL
 */
add_action('admin_init', 'fix_order_numbers_action');
function fix_order_numbers_action() {
    if (isset($_GET['fix_order_numbers']) && is_numeric($_GET['fix_order_numbers'])) {
        if (current_user_can('manage_options')) {
            $order_id = intval($_GET['fix_order_numbers']);
            global $table_prefix, $wpdb;
            $table = $table_prefix . 'woo_limit';
            
            echo '<h2>Fixing Order ' . $order_id . ' Limited Edition Numbers</h2>';
            
            // Get the order
            $order = wc_get_order($order_id);
            if (!$order) {
                echo '<p>Order not found.</p>';
                wp_die();
            }
            
            echo '<h3>Order Details:</h3>';
            echo '<p>Status: ' . $order->get_status() . '</p>';
            echo '<p>Date: ' . $order->get_date_created()->format('Y-m-d H:i:s') . '</p>';
            
            // Get order items
            $items = $order->get_items();
            echo '<h3>Order Items:</h3>';
            
            foreach ($items as $item) {
                $item_id = $item->get_id();
                $product_id = $item->get_product_id();
                $variation_id = $item->get_variation_id();
                $cart_item_key = $item->get_meta('_cart_item_key');
                $limited_number_meta = $item->get_meta('Limited Edition Number');
                
                echo '<p><strong>Item ID:</strong> ' . $item_id . '</p>';
                echo '<p><strong>Product ID:</strong> ' . $product_id . '</p>';
                echo '<p><strong>Variation ID:</strong> ' . $variation_id . '</p>';
                echo '<p><strong>Cart Item Key:</strong> ' . ($cart_item_key ? $cart_item_key : 'NOT FOUND') . '</p>';
                echo '<p><strong>Limited Number Meta:</strong> ' . ($limited_number_meta ? $limited_number_meta : 'NOT FOUND') . '</p>';
                
                // Check if this item has limited edition numbers
                if ($limited_number_meta) {
                    echo '<p><strong>✅ Limited numbers found in order item meta: ' . $limited_number_meta . '</strong></p>';
                    
                    // Check if there are corresponding database records
                    $actual_product_id = $variation_id > 0 ? $variation_id : $product_id;
                    $db_records = $wpdb->get_results($wpdb->prepare("
                        SELECT id, limit_no, status, order_id 
                        FROM $table 
                        WHERE product_id = %s AND order_id = %s
                    ", $actual_product_id, $order_id));
                    
                    if (!empty($db_records)) {
                        echo '<p><strong>✅ Database records found:</strong></p>';
                        foreach ($db_records as $record) {
                            echo '<p>Record ID: ' . $record->id . ' - Numbers: ' . $record->limit_no . ' - Status: ' . $record->status . '</p>';
                        }
                    } else {
                        echo '<p><strong>❌ No database records found for this order item.</strong></p>';
                        
                        // Try to find blocked records for this product
                        $blocked_records = $wpdb->get_results($wpdb->prepare("
                            SELECT id, limit_no, status, cart_key 
                            FROM $table 
                            WHERE product_id = %s AND status = 'block'
                        ", $actual_product_id));
                        
                        if (!empty($blocked_records)) {
                            echo '<p><strong>Found blocked records for this product:</strong></p>';
                            foreach ($blocked_records as $record) {
                                echo '<p>Record ID: ' . $record->id . ' - Numbers: ' . $record->limit_no . ' - Cart Key: ' . $record->cart_key . '</p>';
                                
                                // If cart key matches, update the record
                                if ($cart_item_key && $record->cart_key === $cart_item_key) {
                                    $update = $wpdb->update($table, 
                                        array('order_id' => $order_id, 'order_item_id' => $item_id, 'status' => 'ordered'), 
                                        array('id' => $record->id)
                                    );
                                    if ($update !== false) {
                                        echo '<p><strong>✅ Updated record ID ' . $record->id . ' to order ' . $order_id . '</strong></p>';
                                    } else {
                                        echo '<p><strong>❌ Failed to update record ID ' . $record->id . '</strong></p>';
                                    }
                                }
                            }
                        } else {
                            echo '<p><strong>❌ No blocked records found for this product.</strong></p>';
                        }
                    }
                } else {
                    echo '<p><strong>❌ No limited numbers found in order item meta.</strong></p>';
                    
                    // Try to get from cart session data
                    if ($cart_item_key) {
                        echo '<p>Attempting to get data from cart session...</p>';
                        
                        // Check if there are any blocked records with this cart key
                        $blocked_records = $wpdb->get_results($wpdb->prepare("
                            SELECT id, limit_no, product_id 
                            FROM $table 
                            WHERE cart_key = %s AND status = 'block'
                        ", $cart_item_key));
                        
                        if (!empty($blocked_records)) {
                            echo '<p><strong>Found blocked records with cart key ' . $cart_item_key . ':</strong></p>';
                            foreach ($blocked_records as $record) {
                                echo '<p>Record ID: ' . $record->id . ' - Numbers: ' . $record->limit_no . ' - Product: ' . $record->product_id . '</p>';
                                
                                // Update the record to link it to this order
                                $update = $wpdb->update($table, 
                                    array('order_id' => $order_id, 'order_item_id' => $item_id, 'status' => 'ordered'), 
                                    array('id' => $record->id)
                                );
                                if ($update !== false) {
                                    echo '<p><strong>✅ Updated record ID ' . $record->id . ' to order ' . $order_id . '</strong></p>';
                                    
                                    // Also update the order item meta
                                    $item->update_meta_data('Limited Edition Number', $record->limit_no);
                                    $item->save();
                                    echo '<p><strong>✅ Updated order item meta with numbers: ' . $record->limit_no . '</strong></p>';
                                } else {
                                    echo '<p><strong>❌ Failed to update record ID ' . $record->id . '</strong></p>';
                                }
                            }
                        } else {
                            echo '<p><strong>❌ No blocked records found with cart key ' . $cart_item_key . '</strong></p>';
                        }
                    }
                }
                
                echo '<hr>';
            }
            
            wp_die();
        }
    }
}




