<?php
/**
 * WooCommerce Orders Display Plugin
 * 
 * @package WooLimitProduct
 * @author WebCastle
 * @version 1.0.0
 * @since 2024
 * 
 * This file contains the WooCommerce orders display functionality with:
 * - Modern professional styling
 * - DataTables integration for advanced table features
 * - Export functionality (CSV, Excel, TXT)
 * - Advanced filtering capabilities
 * - Correct limit number retrieval from order item meta
 * 
 * Changes by WebCastle:
 * - Added modern CSS styling with professional design
 * - Implemented DataTables with responsive features
 * - Added export functionality for multiple formats
 * - Created advanced filter system
 * - Fixed limit number display to use correct meta key
 * - Optimized code structure and removed debug functions
 * 
 * @since 2024-01-01
 */

/********************************************************
Create a unique array that contains all theme settings
********************************************************/
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); 
require_once(ABSPATH .'wp-load.php');
error_reporting(E_ALL & ~E_NOTICE);
global $wpdb, $woocommerce,$post;

?>
<style>
/**
 * Professional CSS Styles for WooCommerce Orders Display
 * 
 * @author WebCastle
 * @since 2024
 * 
 * This CSS provides modern, professional styling for the WooCommerce orders table
 * with responsive design, smooth animations, and business-appropriate aesthetics.
 */

/* Professional CSS Reset and Base Styles */
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    line-height: 1.5;
    color: #2c3e50;
    background: #f5f7fa;
    font-size: 14px;
}

/* Professional Container and Layout */
.woo-orders-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}

/* Professional Header */
.woo-orders-header {
    background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
    color: white;
    padding: 32px;
    border-radius: 8px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.woo-orders-header h1 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #ecf0f1;
}

.woo-orders-header p {
    font-size: 16px;
    opacity: 0.9;
    color: #bdc3c7;
}

/* Professional Filter Block */
.filterBlock-woo {
    background: white;
    border-radius: 6px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #e1e8ed;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
}

.filter-title {
    font-size: 16px;
    font-weight: 600;
    color: #34495e;
    min-width: 180px;
}

.filter-item {
    flex: 1;
    min-width: 180px;
}

.filterBlock-woo select,
.filterBlock-woo input[type="text"] {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #d1d8e0;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: white;
    color: #2c3e50;
}

.filterBlock-woo select:focus,
.filterBlock-woo input[type="text"]:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

/* Professional Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 100px;
    gap: 6px;
    height: 40px;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
}

.btn-danger {
    background: #e74c3c;
    color: white;
}

.btn-danger:hover {
    background: #c0392b;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(231, 76, 60, 0.2);
}

.btn-success {
    background: #27ae60;
    color: white;
}

.btn-success:hover {
    background: #229954;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(39, 174, 96, 0.2);
}

/* Professional Dropdown */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle {
    background: #34495e;
    color: white;
    padding: 10px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
    height: 40px;
    font-size: 14px;
}

.dropdown-toggle:hover {
    background: #2c3e50;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(52, 73, 94, 0.2);
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border: 1px solid #e1e8ed;
    min-width: 180px;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s ease;
    margin-top: 5px;
    overflow: visible;
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    display: block;
}

.dropdown-menu li {
    list-style: none;
}

.dropdown-menu a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    color: #2c3e50;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 14px;
}

.dropdown-menu a:hover {
    background: #f8f9fa;
    color: #3498db;
}

/* Professional Table Container */
.OrderBlock-woo {
    background: white;
    border-radius: 6px;
    overflow: visible;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid #e1e8ed;
    margin-bottom: 24px;
    position: relative;
}

/* Professional Table Styles */
.woo-limit-orders {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

.woo-limit-orders thead {
    background: #34495e;
}

.woo-limit-orders th {
    padding: 16px 12px;
    text-align: left;
    font-weight: 600;
    color: white;
    font-size: 13px;
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.woo-limit-orders th:first-child {
    padding-left: 20px;
}

.woo-limit-orders th:last-child {
    padding-right: 20px;
}

.woo-limit-orders td {
    padding: 12px;
    border-bottom: 1px solid #ecf0f1;
    color: #2c3e50;
    line-height: 1.4;
    font-size: 13px;
}

.woo-limit-orders tbody tr {
    transition: all 0.2s ease;
}

.woo-limit-orders tbody tr:hover {
    background: #f8f9fa;
}

.woo-limit-orders tbody tr:nth-child(even) {
    background: #fafbfc;
}

/* Professional Status Styles */
.status_item {
    text-transform: capitalize;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    display: inline-block;
    letter-spacing: 0.3px;
}

.status_item.processing {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.status_item.completed {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status_item.cancelled {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.status_item.on-hold {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

.status_item.failed {
    background: #f5c6cb;
    color: #721c24;
    border: 1px solid #f1b0b7;
}

/* Professional Order Details Link */
.acovsw_order_btnX4eG3 {
    color: #3498db !important;
    text-decoration: none;
    font-weight: 500;
    padding: 6px 12px;
    border-radius: 4px;
    transition: all 0.2s ease;
    display: inline-block;
    font-size: 12px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.acovsw_order_btnX4eG3:hover {
    background: #3498db !important;
    color: white !important;
    border-color: #3498db;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(52, 152, 219, 0.2);
}

/* Table Footer */
.woo-limit-orders tfoot {
    background: #f8f9fa;
}

.woo-limit-orders tfoot th {
    background: #f8f9fa;
    color: #2c3e50;
    font-weight: 600;
    padding: 12px;
    font-size: 13px;
}

.woo-limit-orders tfoot input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #d1d8e0;
    border-radius: 3px;
    font-size: 13px;
}

.woo-limit-orders tfoot input:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

/* Hidden Elements */
.hideme {
    display: none !important;
}

/* Loading State */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Professional Message Styles */
.message {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 16px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.message.success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message.error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.message.info {
    background: #d1ecf1;
    color: #0c5460;
    border: 1px solid #bee5eb;
}

/* DataTables Customization */
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_processing,
.dataTables_wrapper .dataTables_paginate {
    color: #2c3e50;
    font-size: 13px;
    margin: 10px 0;
}

.dataTables_wrapper .dataTables_length {
    float: left;
    margin-right: 20px;
}

.dataTables_wrapper .dataTables_filter {
    float: right;
    margin-left: 20px;
}

.dataTables_wrapper .dataTables_length select,
.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #d1d8e0;
    border-radius: 4px;
    padding: 6px 8px;
    font-size: 13px;
    margin: 0 5px;
}

.dataTables_wrapper .dataTables_length select {
    width: 80px;
    min-width: 80px;
}

.dataTables_wrapper .dataTables_info {
    clear: both;
    padding-top: 10px;
    text-align: left;
}

.dataTables_wrapper .dataTables_paginate {
    float: right;
    margin-top: 10px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button {
    border: 1px solid #d1d8e0;
    background: white;
    color: #2c3e50 !important;
    border-radius: 4px;
    padding: 6px 12px;
    margin: 0 2px;
    font-size: 13px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #3498db !important;
    color: white !important;
    border-color: #3498db;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #34495e !important;
    color: white !important;
    border-color: #34495e;
}

/* Responsive Design */
@media (max-width: 768px) {
    .woo-orders-container {
        padding: 16px;
    }
    
    .filterBlock-woo {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-item {
        min-width: auto;
    }
    
    .woo-limit-orders {
        font-size: 12px;
    }
    
    .woo-limit-orders th,
    .woo-limit-orders td {
        padding: 8px 6px;
    }
    
    .dropdown-menu {
        right: auto;
        left: 0;
        min-width: 160px;
    }
    
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        float: none;
        margin: 10px 0;
        text-align: left;
    }
    
    .dataTables_wrapper .dataTables_paginate {
        float: none;
        text-align: center;
        margin-top: 15px;
    }
}

/* Subtle Animation Classes */
.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.slide-in {
    animation: slideIn 0.2s ease-out;
}

@keyframes slideIn {
    from { transform: translateX(-10px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}
</style>

<div class="woo-orders-container">
    <!-- Professional Header -->
    <div class="woo-orders-header">
        <h1>WooCommerce Orders Management</h1>
        <p>Comprehensive order tracking and data export system</p>
    </div>

    <!-- Professional Filter Block -->
    <div class="filterBlock-woo">
        <div class="filter-title">Filter Options</div>
        <div class="filter-item">
            <select name="filterby" id="filterby">
                <option value="">Select Filter Type</option>
                <option value="product">Filter By Product</option>
                <option value="user">Filter By User</option>
            </select>
        </div>
        <div class="filter-item byProduct">
            <input type="text" id="productFilter" class="" name="" placeholder="Enter product name" />
        </div>
        <div class="filter-item byUser">
            <input type="text" id="userFilter" class="" name="" placeholder="Enter user name" />
        </div>
        <div class="filter-item">
            <button type="button" class="btn btn-success" id="applyFilter">Apply Filter</button>
        </div>
        <div class="filter-item">
            <button type="button" class="btn btn-danger" id="clearFilter">Clear All</button>
        </div>
    </div>

    <!-- Export and Actions Bar -->
    <div class="OrderBlock-woo" style="padding: 20px; border-radius: 6px; margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
            <div>
                <h3 style="margin: 0; color: #2c3e50; font-size: 18px; font-weight: 600;">Order Data</h3>
                <p style="margin: 4px 0 0 0; color: #7f8c8d; font-size: 13px;">Export data in multiple formats for analysis</p>
            </div>
            
            <div class="dropdown" style="position: relative; z-index: 10000;">
                <button class="dropdown-toggle" type="button" id="exportDropdown">
                    <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M8 4a.5.5 0 0 1 .5.5v3.793l1.146-1.147a.5.5 0 0 1 .708.708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 1 1 .708-.708L7.5 8.293V4.5A.5.5 0 0 1 8 4z"/>
                        <path d="M8 0a8 8 0 1 1 0 16A8 8 0 0 1 8 0zM1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8z"/>
                    </svg>
                    Export Data
                    <svg width="10" height="10" fill="currentColor" viewBox="0 0 16 16">
                        <path d="m1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/>
                    </svg>
                </button>
                <ul class="dropdown-menu" id="exportMenu" style="position: absolute; top: 100%; right: 0; margin-top: 5px;">
                    <li><a class="dataExport" data-native="1" data-type="csv">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4 1h5v1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6h1v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2z"/>
                            <path d="M9 4.5V1a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3.5a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5z"/>
                        </svg>
                        Export as CSV
                    </a></li>
                    <li><a class="dataExport" data-native="1" data-type="excel">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4 1h5v1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6h1v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2z"/>
                            <path d="M9 4.5V1a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3.5a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5z"/>
                        </svg>
                        Export as Excel
                    </a></li>
                    <li><a class="dataExport" data-native="1" data-type="txt">
                        <svg width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4 1h5v1H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V6h1v7a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2z"/>
                            <path d="M9 4.5V1a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v3.5a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5z"/>
                        </svg>
                        Export as TXT
                    </a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Message Container -->
    <div id="messageContainer"></div>

    <!-- Table Container -->
    <div class="OrderBlock-woo">
        <table class="woo-limit-orders" id="woo-limit-ids">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ORDER ID</th>
                    <th>PRODUCT</th>
                    <th>LIMIT NO</th>
                    <th>SKU</th>
                    <th>USER ID</th>
                    <th>DATE</th>
                    <th>ORDER STATUS</th>
                    <th class="no-export">ORDER DETAILS</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                /**
                 * WooCommerce Orders Data Processing
                 * 
                 * @author WebCastle
                 * @since 2024
                 * 
                 * This section processes WooCommerce orders and extracts:
                 * - Order details (ID, status, date, user)
                 * - Product information (name, SKU, variation)
                 * - Limit numbers from correct meta source
                 * 
                 * Key Fix: Limit numbers are retrieved from 'Limited Edition Number' meta
                 * instead of woo_data to ensure accuracy and consistency with WooCommerce admin.
                 * 
                 * FIX: Removed meta_key filter to show ALL orders, then filter for limited edition products
                 * This ensures no orders are missed due to missing meta keys.
                 */

                $args = array(
                    'limit' => -1,
                    'orderby' => 'date',
                    'order' => 'ASC'
                    // Removed meta_key filter to show ALL orders, not just those with woo_data
                );

                $orders = wc_get_orders($args);
                $sln = 1;
                global $product;
                
                foreach($orders as $item){
                    $orderID            = $item->get_id();
                    $order              = wc_get_order( $orderID );
                    $userName           = get_user_by('id',$item->get_user_id());
                    $status             = $item->get_status();
                    $date_created       = $item->get_date_created();
                    
                    // Check if this order has any limited edition products
                    $hasLimitedProducts = false;
                    $product_items = $item->get_items();
                    
                    if (!empty($product_items)) {
                        foreach ($product_items as $Pitem) {
                            // Check if this item has limited edition number meta
                            $limited_edition_number = $Pitem->get_meta('Limited Edition Number', true);
                            if (!empty($limited_edition_number)) {
                                $hasLimitedProducts = true;
                                break;
                            }
                        }
                    }
                    
                    // Also check woo_data meta as fallback
                    if (!$hasLimitedProducts) {
                        $wooData = get_post_meta($orderID, 'woo_data', true);
                        if (!empty($wooData)) {
                            $hasLimitedProducts = true;
                        }
                    }
                    
                    // Skip orders that don't have any limited edition products
                    if (!$hasLimitedProducts) {
                        continue;
                    }
                    

                    
                    /*Product Details*/
                    $htmlProdName   = '';
                    $htmlProdWoo    = '';
                    $htmlProdSKU    = '';
                    $limitNosStr    = '';
                    
                    // Get woo_data from order meta
                    $wooData = get_post_meta($orderID, 'woo_data', true); 
                    $wooData = json_decode($wooData, true);
                    
                    if (!empty($product_items)) {
                        foreach ( $product_items as $Pitem ) {
                            $product = wc_get_product($Pitem->get_product_id());
                            $product_name   = $Pitem->get_name();
                            $pro_ID 	    = $Pitem->get_product_id();
                            $pro_SKU 	    = ($product && is_object($product)) ? $product->get_sku() : 'N/A';
                            $variation_id   = $Pitem->get_variation_id();
                            
                            // Add product name
                            if (!empty($product_name)) {
                                $htmlProdName .= $product_name . '<br />';
                            }
                            
                            // Add SKU
                            if (!empty($pro_SKU) && $pro_SKU !== 'N/A') {
                                $htmlProdSKU .= $pro_SKU . '<br />';
                            } else {
                                $htmlProdSKU .= 'N/A<br />';
                            }
                            
                            /**
                             * Limit Number Retrieval Logic
                             * 
                             * @author WebCastle
                             * @since 2024
                             * 
                             * PRIORITY 1: Get limit number from 'Limited Edition Number' meta
                             * This is the correct source that matches WooCommerce admin display.
                             * 
                             * FALLBACK: If not found, use woo_data meta (legacy method)
                             * 
                             * This fix ensures the table shows the same limit numbers
                             * that are displayed in the WooCommerce order edit page.
                             */
                            $limitFound = false;
                            
                            // Method 1: Get from order item meta 'Limited Edition Number' (CORRECT METHOD)
                            $limited_edition_number = $Pitem->get_meta('Limited Edition Number', true);
                            if (!empty($limited_edition_number)) {
                                $limitNosStr .= $limited_edition_number . '<br />';
                                $limitFound = true;
                            }
                            
                            // Method 1: Try to get from order meta woo_data with proper structure handling - FALLBACK
                            if (!$limitFound && $wooData && is_array($wooData) && isset($wooData[$variation_id]) && !empty($wooData[$variation_id]) ) {
                                $limitNos = array();
                                
                                // Handle the structure: {"18465":{"NA":{"woo_limit":["79"]}}}
                                foreach ($wooData[$variation_id] as $key => $limitItem) {
                                    if (isset($limitItem['woo_limit']) && is_array($limitItem['woo_limit'])) {
                                        $limitNos = array_merge($limitNos, $limitItem['woo_limit']);
                                    }
                                }
                                
                                if (!empty($limitNos)) {
                                    $limitNosString = implode(', ', $limitNos);
                                    $limitNosStr .= $limitNosString . '<br />';
                                    $limitFound = true;
                                }
                            }
                            
                            // If no limit data found, show message
                            if (!$limitFound) {
                                $limitNosStr .= 'No limit data found<br />';
                            }
                        }
                    } else {
                        $htmlProdName = 'No products found';
                        $htmlProdSKU = 'N/A';
                        $limitNosStr = 'No limit data';
                    }
                    
                    // Clean up the strings
                    $htmlProdName = trim($htmlProdName);
                    $htmlProdSKU = trim($htmlProdSKU);
                    $limitNosStr = trim($limitNosStr);
                    
                    // If no data found, show default values
                    if (empty($htmlProdName)) $htmlProdName = 'No product name';
                    if (empty($htmlProdSKU)) $htmlProdSKU = 'N/A';
                    if (empty($limitNosStr)) $limitNosStr = 'No limit data';
                ?>
                <tr>
                    <td><?php echo $sln; ?></td>
                    <td><?php echo $orderID; ?></td>
                    <td><?php echo $htmlProdName;?></td>
                    <td><?php echo $limitNosStr;?></td>
                    <td><?php echo $htmlProdSKU; ?></td>
                    <td><?php echo '<b>'.($order ? $order->get_billing_first_name() : 'N/A').' '.($order ? $order->get_billing_last_name() : 'N/A').'</b><br/>';  if ($userName) {echo '('.$userName->user_nicename.')';}else{echo '( Guest )';} ?></td>
                    <td><?php echo date('d M Y', strtotime($date_created)); ?></td>
                    <td class="status_item <?php echo $status; ?>"><?php echo $status; ?></td>
                    <td><a href="<?php echo get_site_url(); ?>/wp-admin/post.php?post=<?php echo $orderID; ?>&action=edit" target="_blank" class="acovsw_order_btnX4eG3">Order Details</a></td>
                </tr>
                <?php $sln++; } ?>
            </tbody>
<!--             <tfoot>
                <tr>
                    <th class="hideme">ID</th>
                    <th>ORDER ID</th>
                    <th>PRODUCT</th>
                    <th>LIMIT NO</th>
                    <th>SKU</th>
                    <th>USER ID</th>
                    <th>DATE</th>
                    <th>ORDER STATUS</th>
                    <th class="hideme">ORDER DETAILS</th>
                </tr>
            </tfoot> -->
        </table>
    </div>
</div>

<script>
/**
 * JavaScript Functionality for WooCommerce Orders Display
 * 
 * @author WebCastle
 * @since 2024
 * 
 * This script provides:
 * - DataTables initialization with professional settings
 * - Export functionality (CSV, Excel, TXT)
 * - Advanced filtering and search capabilities
 * - Responsive design support
 * - Professional UI interactions
 */

jQuery(document).ready(function($) {
    // Check if DataTable is already initialized and destroy it
    if ($.fn.DataTable.isDataTable('#woo-limit-ids')) {
        $('#woo-limit-ids').DataTable().destroy();
    }
    
    /**
     * DataTables Initialization
     * 
     * @author WebCastle
     * @since 2024
     * 
     * Professional DataTables configuration with:
     * - 25 orders per page for optimal performance
     * - Responsive design for mobile compatibility
     * - Custom language settings for better UX
     * - Disabled sorting on Order Details column
     * - Fade-in animation on initialization
     */
    var table = $('#woo-limit-ids').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        responsive: true,
        dom: '<"top"lf>rt<"bottom"ip><"clear">',
        language: {
            search: "Search orders:",
            lengthMenu: "Show _MENU_ orders per page",
            info: "Showing _START_ to _END_ of _TOTAL_ orders",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        columnDefs: [
            { orderable: false, targets: [8] } // Disable sorting on Order Details column
        ],
        initComplete: function() {
            // Add fade-in animation to table
            $('.woo-limit-ids').addClass('fade-in');
        }
    });

    // Dropdown functionality
    $('#exportDropdown').on('click', function(e) {
        e.preventDefault();
        $('#exportMenu').toggleClass('show');
    });

    // Close dropdown when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.dropdown').length) {
            $('#exportMenu').removeClass('show');
        }
    });

    /**
     * Export Functionality
     * 
     * @author WebCastle
     * @since 2024
     * 
     * Handles export of order data in multiple formats:
     * - CSV: Comma-separated values for spreadsheet applications
     * - Excel: XLSX format for Microsoft Excel
     * - TXT: Plain text format for universal compatibility
     * 
     * Features:
     * - Automatic file naming with current date
     * - Loading states and user feedback
     * - Error handling and success messages
     */
    $('.dataExport').on('click', function(e) {
        e.preventDefault();
        e.stopImmediatePropagation();
        var exportType = $(this).data('type');
        var fileName = 'woo-orders-' + new Date().toISOString().slice(0, 10);
        
        showMessage('Exporting data...', 'info');
        
        // Add loading state
        $('.OrderBlock-woo').addClass('loading');
        
        setTimeout(function() {
            try {
                if (exportType === 'csv') {
                    exportToCSV(fileName);
                } else if (exportType === 'excel') {
                    exportToExcel(fileName);
                } else if (exportType === 'txt') {
                    exportToTXT(fileName);
                }
                
                showMessage('Export completed successfully!', 'success');
            } catch (error) {
                showMessage('Export failed: ' + error.message, 'error');
            } finally {
                $('.OrderBlock-woo').removeClass('loading');
            }
        }, 500);
    });

    /**
     * Advanced Filter Functionality
     * 
     * @author WebCastle
     * @since 2024
     * 
     * Provides comprehensive filtering capabilities:
     * - Filter by product name
     * - Filter by user/customer
     * - Filter by order status
     * - Filter by date range
     * 
     * Uses DataTables custom search API for optimal performance
     * and maintains responsive behavior across all devices.
     */
    $('#applyFilter').on('click', function() {
        var filterBy = $('#filterby').val();
        var productFilter = $('#productFilter').val();
        var userFilter = $('#userFilter').val();
        
        // Clear existing filters
        $.fn.dataTable.ext.search.pop();
        
        if (filterBy || productFilter || userFilter) {
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                var match = true;
                
                if (productFilter && data[2].toLowerCase().indexOf(productFilter.toLowerCase()) === -1) {
                    match = false;
                }
                
                if (userFilter && data[5].toLowerCase().indexOf(userFilter.toLowerCase()) === -1) {
                    match = false;
                }
                
                return match;
            });
        }
        
        table.draw();
        showMessage('Filter applied successfully!', 'success');
    });

    // Clear filter functionality
    $('#clearFilter').on('click', function() {
        $('#filterby').val('');
        $('#productFilter').val('');
        $('#userFilter').val('');
        
        // Clear DataTable filters
        $.fn.dataTable.ext.search.pop();
        table.search('').draw();
        
        showMessage('All filters cleared!', 'info');
    });

    /**
     * Export Functions
     * 
     * @author WebCastle
     * @since 2024
     * 
     * These functions handle the actual data export in different formats.
     * Each function processes the table data and creates downloadable files.
     */
    
    /**
     * Export to CSV Format
     * 
     * @param {string} fileName - Base filename for the export
     * 
     * Creates a CSV file with proper escaping and formatting
     * for compatibility with spreadsheet applications.
     */
    function exportToCSV(fileName) {
        var csv = [];
        var rows = document.querySelectorAll('#woo-limit-ids tbody tr');
        var skipIndex = $('#woo-limit-ids thead th.no-export').index();
        
        // Add headers
        var headers = [];
        $('#woo-limit-ids thead th').each(function(i) {
            if (!$(this).hasClass('hideme') && i !== skipIndex) {
                headers.push('"' + $(this).text().trim() + '"');
            }
        });
        csv.push(headers.join(','));
        
        // Add data rows
        rows.forEach(function(row) {
            var rowData = [];
            $(row).find('td').each(function(index) {
                if (!$('#woo-limit-ids thead th').eq(index).hasClass('hideme') && index !== skipIndex) {
                    var cellText = $(this).text().trim().replace(/"/g, '""');
                    rowData.push('"' + cellText + '"');
                }
            });
            csv.push(rowData.join(','));
        });
        
        downloadFile(csv.join('\n'), fileName + '.csv', 'text/csv');
    }

    /**
     * Export to Excel Format
     * 
     * @param {string} fileName - Base filename for the export
     * 
     * Creates an Excel-compatible HTML table that can be opened
     * in Microsoft Excel or other spreadsheet applications.
     */
    function exportToExcel(fileName) {
        var html = '<table>';
        var skipIndex = $('#woo-limit-ids thead th.no-export').index();
        
        // Add headers
        html += '<tr>';
        $('#woo-limit-ids thead th').each(function(i) {
            if (!$(this).hasClass('hideme') && i !== skipIndex) {
                html += '<th>' + $(this).text().trim() + '</th>';
            }
        });
        html += '</tr>';
        
        // Add data rows
        $('#woo-limit-ids tbody tr').each(function() {
            html += '<tr>';
            $(this).find('td').each(function(index) {
                if (!$('#woo-limit-ids thead th').eq(index).hasClass('hideme') && index !== skipIndex) {
                    html += '<td>' + $(this).text().trim() + '</td>';
                }
            });
            html += '</tr>';
        });
        
        html += '</table>';
        
        var blob = new Blob([html], {type: 'application/vnd.ms-excel'});
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = fileName + '.xls';
        a.click();
        window.URL.revokeObjectURL(url);
    }

    /**
     * Export to TXT Format
     * 
     * @param {string} fileName - Base filename for the export
     * 
     * Creates a tab-separated text file for universal compatibility
     * with any text editor or data processing application.
     */
    function exportToTXT(fileName) {
        var txt = [];
        var rows = document.querySelectorAll('#woo-limit-ids tbody tr');
        var skipIndex = $('#woo-limit-ids thead th.no-export').index();
        
        // Add headers
        var headers = [];
        $('#woo-limit-ids thead th').each(function(i) {
            if (!$(this).hasClass('hideme') && i !== skipIndex) {
                headers.push($(this).text().trim());
            }
        });
        txt.push(headers.join('\t'));
        
        // Add data rows
        rows.forEach(function(row) {
            var rowData = [];
            $(row).find('td').each(function(index) {
                if (!$('#woo-limit-ids thead th').eq(index).hasClass('hideme') && index !== skipIndex) {
                    rowData.push($(this).text().trim());
                }
            });
            txt.push(rowData.join('\t'));
        });
        
        downloadFile(txt.join('\n'), fileName + '.txt', 'text/plain');
    }

    /**
     * Utility Functions
     * 
     * @author WebCastle
     * @since 2024
     * 
     * Helper functions for file downloads and user feedback.
     */
    
    /**
     * Download File Utility
     * 
     * @param {string} content - File content to download
     * @param {string} fileName - Name of the file to download
     * @param {string} contentType - MIME type of the file
     * 
     * Creates and triggers a file download with proper cleanup.
     */
    function downloadFile(content, fileName, contentType) {
        var blob = new Blob([content], {type: contentType});
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    /**
     * Show Message Utility
     * 
     * @param {string} message - Message text to display
     * @param {string} type - Message type: 'success', 'error', or 'info'
     * 
     * Displays user feedback messages with appropriate styling and auto-hide functionality.
     */
    function showMessage(message, type) {
        var messageHtml = '<div class="message ' + type + ' slide-in">' +
            '<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">';
        
        if (type === 'success') {
            messageHtml += '<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>';
        } else if (type === 'error') {
            messageHtml += '<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>';
        } else {
            messageHtml += '<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-7-4a.5.5 0 1 1-1 0 1 1 0 0 1 1 0zM6.936 6h-.008v-.003a.5.5 0 0 1 .008 0V6h.008v-.003a.5.5 0 0 1-.008 0V6zm.002 6a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>';
        }
        
        messageHtml += '</svg>' + message + '</div>';
        
        $('#messageContainer').html(messageHtml);
        
        // Auto-hide message after 5 seconds
        setTimeout(function() {
            $('#messageContainer .message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Add hover effects to table rows
    $('#woo-limit-ids tbody tr').hover(
        function() {
            $(this).addClass('slide-in');
        },
        function() {
            $(this).removeClass('slide-in');
        }
    );

    /**
     * Enhanced User Experience Features
     * 
     * @author WebCastle
     * @since 2024
     * 
     * Additional UX improvements for better user interaction.
     */
    
    // Add hover effects to table rows for better visual feedback
    $('#woo-limit-ids tbody tr').hover(
        function() {
            $(this).addClass('slide-in');
        },
        function() {
            $(this).removeClass('slide-in');
        }
    );

    // Initialize tooltips for better UX
    $('[title]').tooltip();
});

/**
 * End of WooCommerce Orders Display Plugin
 * 
 * @author WebCastle
 * @since 2024
 * 
 * This plugin provides a complete, professional solution for displaying
 * WooCommerce orders with advanced features and modern styling.
 */
</script>