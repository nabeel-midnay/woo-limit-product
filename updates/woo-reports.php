<?php
/********************************************************
Create a unique array that contains all theme settings
********************************************************/
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
error_reporting(E_ALL & ~E_NOTICE);
global $wpdb, $woocommerce, $post;

// Get current date for date picker defaults
$current_date = current_time('Y-m-d');
$last_month = date('Y-m-d', strtotime('-1 month'));

// Get product categories for filter
$product_categories = get_terms(array(
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
));

// Get product attributes for filter
$product_attributes = wc_get_attribute_taxonomies();

// Get product categories for filter
$product_categories = get_terms(array(
    'taxonomy' => 'product_cat',
    'hide_empty' => false,
));

// Get product attributes for filter
$product_attributes = wc_get_attribute_taxonomies();

// Prepare chart data
$chart_data = array();

// Get limited products for charts
$chart_products = array();
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

if ($products_query->have_posts()) {
    while ($products_query->have_posts()) {
        $products_query->the_post();
        $product_id = get_the_ID();
        $product = wc_get_product($product_id);

        $start = get_post_meta($product_id, '_woo_limit_start_value', true);
        $end = get_post_meta($product_id, '_woo_limit_end_value', true);

        if ($start && $end) {
            $total_count = ($end - $start) + 1;
            $available_count = limitedNosAvailableCount($product_id);
            $sold_count = limitedNosSoldCount($product_id);

            $chart_products[] = array(
                'id' => $product_id,
                'name' => get_the_title(),
                'total' => $total_count,
                'available' => $available_count,
                'sold' => $sold_count,
                'percentage' => $total_count > 0 ? round(($sold_count / $total_count) * 100, 1) : 0
            );
        }
    }
    wp_reset_postdata();
}

// Get monthly sales data (last 6 months)
$monthly_data = array();
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));

    // This would need to be connected to actual order data
    // For now, we'll use sample data
    $monthly_data[] = array(
        'month' => $month_name,
        'sales' => rand(10, 50) // Replace with actual sales data
    );
}

?>
<style>
    /* WooCommerce-style Reporting Page Styles */
    .woo-limit-reports-wrapper {
        margin: 20px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        background: #f0f0f1;
        padding: 20px;
        border-radius: 8px;
    }

    .woo-limit-reports-header {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        border: none;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        color: white;
    }

    .woo-limit-reports-header h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 600;
        line-height: 1.3;
        color: white;
    }

    .woo-limit-reports-header p {
        margin: 0;
        color: rgba(255, 255, 255, 0.9);
        font-size: 16px;
        line-height: 1.5;
    }

    /* Stats Cards */
    .woo-limit-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin-bottom: 35px;
    }

    .woo-limit-stat-card {
        background: white;
        border: none;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .woo-limit-stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--card-color) 0%, var(--card-color-light) 100%);
    }

    .woo-limit-stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .woo-limit-stat-card h3 {
        margin: 0 0 15px 0;
        font-size: 13px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .woo-limit-stat-card .stat-number {
        font-size: 36px;
        font-weight: 700;
        color: var(--card-color);
        line-height: 1.2;
        margin-bottom: 8px;
    }

    .woo-limit-stat-card .stat-description {
        font-size: 14px;
        color: #6b7280;
        margin: 0;
        font-weight: 500;
    }

    .woo-limit-stat-card.available {
        --card-color: #00a32a;
        --card-color-light: #00d93a;
    }

    .woo-limit-stat-card.sold {
        --card-color: #d63638;
        --card-color-light: #e74c3c;
    }

    .woo-limit-stat-card.total {
        --card-color: #1a1a1a;
        --card-color-light: #2d2d2d;
    }

    .woo-limit-stat-card.products {
        --card-color: #333333;
        --card-color-light: #4a4a4a;
    }

    /* Enhanced Filters Section */
    .woo-limit-filters {
        background: white;
        border: none;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
    }

    .woo-limit-filters h3 {
        margin: 0 0 20px 0;
        font-size: 18px;
        font-weight: 600;
        color: #1f2937;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .woo-limit-filters h3::before {
        content: '🔍';
        font-size: 20px;
    }

    .woo-limit-filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        align-items: end;
    }

    .woo-limit-filters-grid .woo-limit-filter-actions {
        grid-column: 1 / -1;
        justify-self: start;
    }

    .woo-limit-filter-group {
        display: flex;
        flex-direction: column;
    }

    .woo-limit-filter-group label {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .woo-limit-filter-group select,
    .woo-limit-filter-group input {
        padding: 12px 16px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 14px;
        line-height: 1.4;
        background: #f9fafb;
        color: #1f2937;
        transition: all 0.2s ease;
        min-height: 45px;
    }

    .woo-limit-filter-group select:focus,
    .woo-limit-filter-group input:focus {
        border-color: #1a1a1a;
        background: white;
        box-shadow: 0 0 0 3px rgba(26, 26, 26, 0.1);
        outline: none;
    }

    .woo-limit-filter-actions {
        display: flex;
        flex-direction: row;
        gap: 12px;
        align-items: center;
        justify-content: flex-start;
        flex-wrap: wrap;
        margin-top: 10px;
        width: 100%;
    }

    .woo-limit-btn {
        padding: 12px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
        min-height: 45px;
        min-width: 120px;
        text-align: center;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .woo-limit-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        color: white;
    }

    .woo-limit-btn.primary {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: white;
    }

    .woo-limit-btn.primary:hover {
        background: linear-gradient(135deg, #2d2d2d 0%, #404040 100%);
    }

    .woo-limit-btn.secondary {
        background: linear-gradient(135deg, #333333 0%, #4a4a4a 100%);
        color: white;
    }

    .woo-limit-btn.secondary:hover {
        background: linear-gradient(135deg, #4a4a4a 0%, #5a5a5a 100%);
    }

    .woo-limit-btn.danger {
        background: linear-gradient(135deg, #d63638 0%, #e74c3c 100%);
        color: white;
    }

    .woo-limit-btn.danger:hover {
        background: linear-gradient(135deg, #e74c3c 0%, #f56565 100%);
    }

    .woo-limit-btn.success {
        background: linear-gradient(135deg, #00a32a 0%, #00d93a 100%);
        color: white;
    }

    .woo-limit-btn.success:hover {
        background: linear-gradient(135deg, #00d93a 0%, #00ff40 100%);
    }

    /* Table Styles */
    .woo-limit-table-wrapper {
        background: white;
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        margin-bottom: 30px;
    }

    .woo-limit-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .woo-limit-table thead th {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border-bottom: 2px solid #e2e8f0;
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        color: #1e293b;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 1px;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .woo-limit-table tbody td {
        padding: 16px 20px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
        color: #334155;
        background: white;
    }

    .woo-limit-table tbody tr:hover {
        background: #f8fafc;
    }

    .woo-limit-table tbody tr:hover td {
        background: #f8fafc;
    }

    .woo-limit-table tbody tr:last-child td {
        border-bottom: none;
    }

    /* Product Name Column */
    .woo-limit-product-name {
        font-weight: 600;
        color: #2271b1;
        text-decoration: none;
    }

    .woo-limit-product-name:hover {
        color: #135e96;
        text-decoration: underline;
    }

    .woo-limit-product-meta {
        font-size: 12px;
        color: #646970;
        margin-top: 5px;
    }

    .woo-limit-product-meta span {
        display: inline-block;
        margin-right: 10px;
    }

    /* Numbers Display */
    .woo-limit-numbers-display {
        max-width: 300px;
        word-wrap: break-word;
    }

    .woo-limit-numbers-toggle {
        cursor: pointer;
        color: #2271b1;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 5px;
    }

    .woo-limit-numbers-toggle:hover {
        color: #135e96;
    }

    .woo-limit-numbers-toggle .toggle-icon {
        transition: transform 0.2s ease;
    }

    .woo-limit-numbers-toggle.expanded .toggle-icon {
        transform: rotate(180deg);
    }

    .woo-limit-numbers-list {
        display: none;
        font-size: 12px;
        color: #646970;
        background: #f6f7f7;
        padding: 8px;
        border-radius: 4px;
        margin-top: 5px;
        max-height: 100px;
        overflow-y: auto;
    }

    .woo-limit-numbers-list.show {
        display: block;
    }

    /* Sold numbers now use the same styling as available numbers */

    .sold-number-item .order-info a {
        color: #0073aa;
        text-decoration: none;
        font-weight: 500;
    }

    .sold-number-item .order-info a:hover {
        text-decoration: underline;
    }

    /* Status Indicators */
    .woo-limit-status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .woo-limit-status-indicator.available {
        background: #edfaef;
        color: #00a32a;
    }

    .woo-limit-status-indicator.sold {
        background: #fcf0f1;
        color: #d63638;
    }

    /* Progress Bar */
    .woo-limit-progress {
        width: 100%;
        height: 8px;
        background: #f0f0f1;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 5px;
    }

    .woo-limit-progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #00a32a 0%, #00a32a 100%);
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    .woo-limit-progress-bar.sold {
        background: linear-gradient(90deg, #d63638 0%, #d63638 100%);
    }

    /* Empty State */
    .woo-limit-empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #646970;
    }

    .woo-limit-empty-state .dashicons {
        font-size: 48px;
        color: #ccd0d4;
        margin-bottom: 15px;
    }

    .woo-limit-empty-state h3 {
        margin: 0 0 10px 0;
        font-size: 18px;
        color: #23282d;
    }

    .woo-limit-empty-state p {
        margin: 0;
        font-size: 14px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .woo-limit-reports-wrapper {
            padding: 15px;
            margin: 10px 0;
        }

        .woo-limit-reports-header {
            padding: 20px;
            margin-bottom: 20px;
        }

        .woo-limit-reports-header h1 {
            font-size: 24px;
        }

        .woo-limit-stats-grid {
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .woo-limit-stat-card {
            padding: 20px;
        }

        .woo-limit-stat-card .stat-number {
            font-size: 28px;
        }

        .woo-limit-filters {
            padding: 20px;
            margin-bottom: 20px;
        }

        .woo-limit-filters-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .woo-limit-filter-actions {
            flex-direction: row;
            gap: 8px;
            justify-content: center;
        }

        .woo-limit-btn {
            min-width: auto;
            flex: 1;
            justify-content: center;
            font-size: 12px;
            padding: 10px 12px;
        }

        .woo-limit-table {
            font-size: 12px;
        }

        .woo-limit-table thead th,
        .woo-limit-table tbody td {
            padding: 10px 12px;
        }

        .dataTables_wrapper {
            padding: 15px;
        }

        .dataTables_filter input {
            min-width: 200px;
            width: 100%;
        }

        .dataTables_paginate {
            flex-wrap: wrap;
            gap: 3px;
        }

        .dataTables_paginate .paginate_button {
            padding: 6px 12px;
            font-size: 12px;
            min-width: 35px;
        }
    }

    @media (max-width: 480px) {
        .woo-limit-reports-wrapper {
            padding: 10px;
        }

        .woo-limit-reports-header {
            padding: 15px;
        }

        .woo-limit-reports-header h1 {
            font-size: 20px;
        }

        .woo-limit-stat-card {
            padding: 15px;
        }

        .woo-limit-stat-card .stat-number {
            font-size: 24px;
        }

        .woo-limit-filters {
            padding: 15px;
        }

        .dataTables_wrapper {
            padding: 10px;
        }

        .dataTables_filter input {
            min-width: 150px;
        }
    }

    /* DataTables Customization */
    .dataTables_wrapper {
        padding: 25px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
    }

    .dataTables_filter {
        margin-bottom: 20px;
    }

    .dataTables_filter input {
        border: 2px solid #e5e7eb !important;
        border-radius: 8px !important;
        padding: 10px 16px !important;
        background: #f9fafb !important;
        color: #1f2937 !important;
        font-size: 14px !important;
        min-width: 250px !important;
        transition: all 0.2s ease !important;
    }

    .dataTables_filter input:focus {
        border-color: #1a1a1a !important;
        background: white !important;
        box-shadow: 0 0 0 3px rgba(26, 26, 26, 0.1) !important;
        outline: none !important;
    }

    .dataTables_length {
        margin-bottom: 20px;
    }

    .dataTables_length select {
        border: 2px solid #e5e7eb !important;
        border-radius: 8px !important;
        padding: 8px 12px !important;
        background: #f9fafb !important;
        color: #1f2937 !important;
        font-size: 14px !important;
        min-width: 80px !important;
    }

    .dataTables_length select:focus {
        border-color: #1a1a1a !important;
        background: white !important;
        outline: none !important;
    }

    .dataTables_info {
        color: #6b7280 !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        margin-top: 20px !important;
    }

    .dataTables_paginate {
        margin-top: 20px !important;
        display: flex !important;
        justify-content: center !important;
        gap: 5px !important;
    }

    .dataTables_paginate .paginate_button {
        border: 2px solid #e5e7eb !important;
        border-radius: 8px !important;
        padding: 8px 16px !important;
        margin: 0 2px !important;
        background: white !important;
        color: #374151 !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
        min-width: 40px !important;
        text-align: center !important;
    }

    .dataTables_paginate .paginate_button.current {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%) !important;
        border-color: #1a1a1a !important;
        color: white !important;
        box-shadow: 0 2px 4px rgba(26, 26, 26, 0.2) !important;
    }

    .dataTables_paginate .paginate_button:hover {
        background: #f8fafc !important;
        border-color: #1a1a1a !important;
        color: #1a1a1a !important;
        transform: translateY(-1px) !important;
    }

    .dataTables_paginate .paginate_button.current:hover {
        background: linear-gradient(135deg, #2d2d2d 0%, #404040 100%) !important;
        color: white !important;
    }

    .dataTables_paginate .paginate_button.disabled {
        opacity: 0.5 !important;
        cursor: not-allowed !important;
    }

    .dataTables_paginate .paginate_button.disabled:hover {
        background: white !important;
        border-color: #e5e7eb !important;
        color: #9ca3af !important;
        transform: none !important;
    }

    /* Loading State */
    .woo-limit-loading {
        text-align: center;
        padding: 40px 20px;
        color: #646970;
    }

    .woo-limit-loading .dashicons {
        font-size: 32px;
        color: #2271b1;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Loading State */
    .woo-limit-loading {
        text-align: center;
        padding: 40px 20px;
        color: #646970;
    }

    .woo-limit-loading .dashicons {
        font-size: 32px;
        color: #2271b1;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Edit Button Style */
    .woo-limit-edit-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        min-width: 70px;
        justify-content: center;
    }

    .woo-limit-edit-btn:hover {
        background: linear-gradient(135deg, #2d2d2d 0%, #404040 100%);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
        text-decoration: none;
    }

    .woo-limit-edit-btn .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }

    /* Charts Section */
    .woo-limit-charts-section {
        margin-top: 30px;
    }

    .woo-limit-charts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 25px;
        margin-bottom: 30px;
    }

    .woo-limit-chart-card {
        background: white;
        border: none;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .woo-limit-chart-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #1a1a1a 0%, #2d2d2d 100%);
    }

    .woo-limit-chart-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .woo-limit-chart-card h3 {
        margin: 0 0 20px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        text-align: center;
    }

    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    .chart-container canvas {
        max-height: 100%;
        max-width: 100%;
    }

    /* Responsive Charts */
    @media (max-width: 768px) {
        .woo-limit-charts-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .woo-limit-chart-card {
            padding: 20px;
        }

        .chart-container {
            height: 250px;
        }
    }

    /* Improved styling for available/sold number lists */
    .woo-limit-numbers-list {
        display: none;
        font-size: 13px;
        color: #374151;
        background: #f1f5f9;
        padding: 10px 12px;
        border-radius: 6px;
        margin-top: 6px;
        max-height: 140px;
        overflow-y: auto;
        line-height: 1.4;
        border: 1px solid #e2e8f0;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04);
    }

    .woo-limit-numbers-list.show {
        display: block;
    }

    /* Number items separated by comma -> put them on separate line for better readability */
    .woo-limit-numbers-list span,
    .woo-limit-numbers-list .number-item {
        display: block;
        margin-bottom: 4px;
    }

    .woo-limit-numbers-toggle {
        cursor: pointer;
        color: #1d4ed8;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: color .2s ease;
    }

    .woo-limit-numbers-toggle:hover {
        color: #0e3a9e;
    }

    /* Order link and status styling */
    .order-link {
        color: #1d4ed8;
        text-decoration: none;
        font-weight: 500;
        transition: color .2s ease;
    }

    .order-link:hover {
        color: #0e3a9e;
        text-decoration: underline;
    }

    .order-status {
        font-size: 12px;
        padding: 2px 6px;
        border-radius: 3px;
        font-weight: 500;
    }

    .order-status.blocked {
        background: #fef3c7;
        color: #92400e;
        border: 1px solid #f59e0b;
    }

    .number-item {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 6px;
        padding: 4px 0;
    }

    .number-item strong {
        min-width: 40px;
        color: #1f2937;
    }
</style>

<div class="woo-limit-reports-wrapper">
    <!-- Header -->
    <div class="woo-limit-reports-header">
        <h1>Limited Product Reports</h1>
        <p>Track the availability and sales of your limited edition products with detailed analytics and insights.</p>
    </div>

    <!-- Statistics Cards -->
    <div class="woo-limit-stats-grid">
        <?php
        // Calculate overall statistics
        $total_products = 0;
        $total_available = 0;
        $total_sold = 0;
        $total_numbers = 0;

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

        if ($products_query->have_posts()) {
            while ($products_query->have_posts()) {
                $products_query->the_post();
                $product_id = get_the_ID();

                $start = get_post_meta($product_id, '_woo_limit_start_value', true);
                $end = get_post_meta($product_id, '_woo_limit_end_value', true);

                if ($start && $end) {
                    $total_products++;
                    $product_range = ($end - $start) + 1;
                    $total_numbers += $product_range;

                    $available_count = limitedNosAvailableCount($product_id);
                    $sold_count = limitedNosSoldCount($product_id);

                    $total_available += $available_count;
                    $total_sold += $sold_count;
                }
            }
            wp_reset_postdata();
        }
        ?>

        <div class="woo-limit-stat-card products">
            <h3>Total Products</h3>
            <div class="stat-number"><?php echo $total_products; ?></div>
            <p class="stat-description">Limited edition products</p>
        </div>

        <div class="woo-limit-stat-card total">
            <h3>Total Numbers</h3>
            <div class="stat-number"><?php echo number_format($total_numbers); ?></div>
            <p class="stat-description">Across all products</p>
        </div>

        <div class="woo-limit-stat-card available">
            <h3>Available Numbers</h3>
            <div class="stat-number"><?php echo number_format($total_available); ?></div>
            <p class="stat-description">Ready for purchase</p>
        </div>

        <div class="woo-limit-stat-card sold">
            <h3>Sold Numbers</h3>
            <div class="stat-number"><?php echo number_format($total_sold); ?></div>
            <p class="stat-description">Already purchased</p>
        </div>
    </div>

    <!-- Enhanced Filters -->
    <div class="woo-limit-filters">
        <h3>Filter Products</h3>
        <div class="woo-limit-filters-grid">
            <div class="woo-limit-filter-group">
                <label for="product-search">Search Products</label>
                <input type="text" id="product-search" placeholder="Enter product name, SKU...">
            </div>

            <div class="woo-limit-filter-group">
                <label for="category-filter">Category</label>
                <select id="category-filter">
                    <option value="">All Categories</option>
                    <?php foreach ($product_categories as $category): ?>
                        <option value="<?php echo esc_attr($category->slug); ?>"><?php echo esc_html($category->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="woo-limit-filter-group">
                <label for="availability-filter">Availability</label>
                <select id="availability-filter">
                    <option value="">All Products</option>
                    <option value="available">Available Numbers</option>
                    <option value="sold">Sold Out</option>
                    <option value="partial">Partially Sold</option>
                </select>
            </div>

            <div class="woo-limit-filter-group">
                <label for="product-type-filter">Product Type</label>
                <select id="product-type-filter">
                    <option value="">All Types</option>
                    <option value="simple">Simple</option>
                    <option value="variable">Variable</option>
                </select>
            </div>

            <div class="woo-limit-filter-group">
                <label for="sort-by">Sort By</label>
                <select id="sort-by">
                    <option value="name">Product Name</option>
                    <option value="available">Available Count</option>
                    <option value="sold">Sold Count</option>
                    <option value="percentage">Sold Percentage</option>
                    <option value="sku">SKU</option>
                </select>
            </div>

            <div class="woo-limit-filter-actions">
                <button class="woo-limit-btn success" onclick="exportToExcel()">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    Export Excel
                </button>
                <button class="woo-limit-btn primary" onclick="exportReport()">
                    <span class="dashicons dashicons-download"></span>
                    Export CSV
                </button>
                <button class="woo-limit-btn secondary" onclick="refreshReport()">
                    <span class="dashicons dashicons-update"></span>
                    Refresh
                </button>
                <button class="woo-limit-btn danger" id="clear-in-cart-all">
                    <span class="dashicons dashicons-trash"></span>
                    Clear In-Cart (All)
                </button>
            </div>
        </div>
    </div>

    <!-- Products Table -->
    <div class="woo-limit-table-wrapper">
        <table class="woo-limit-table" id="woo-limit-reports-table">
            <thead>
                <tr>
                    <th>Product Details</th>
                    <th>Number Range</th>
                    <th>Total Numbers</th>
                    <th>Available</th>
                    <th>Sold</th>
                    <th>Progress</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
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

                if ($products_query->have_posts()) {
                    while ($products_query->have_posts()) {
                        $products_query->the_post();
                        $product_id = get_the_ID();
                        $product = wc_get_product($product_id);
                        $start = get_post_meta($product_id, '_woo_limit_start_value', true);
                        $end = get_post_meta($product_id, '_woo_limit_end_value', true);

                        if ($start && $end) {
                            $range = $start . ' - ' . $end;
                            $total_count = ($end - $start) + 1;
                            $available_count = limitedNosAvailableCount($product_id);
                            $sold_count = limitedNosSoldCount($product_id);
                            $sold_percentage = $total_count > 0 ? round(($sold_count / $total_count) * 100, 1) : 0;

                            $available_numbers = limitedNosAvailable($product_id);
                            $sold_numbers = limitedNosSold($product_id);

                            // Get detailed sold information with order IDs
                            $sold_details = getSoldNumbersWithOrderDetails($product_id);
                            $sold_numbers_with_orders = '';
                            if (!empty($sold_details)) {
                                $sold_numbers_array = array();
                                foreach ($sold_details as $detail) {
                                    $sold_numbers_array[] = $detail['number'];
                                }
                                $sold_numbers_with_orders = implode(', ', $sold_numbers_array);
                            }

                            // Get product categories
                            $categories = get_the_terms($product_id, 'product_cat');
                            $category_names = array();
                            $category_slugs = array();
                            if ($categories && !is_wp_error($categories)) {
                                foreach ($categories as $category) {
                                    $category_names[] = $category->name;
                                    $category_slugs[] = $category->slug;
                                }
                            }

                            // Get product type
                            $product_type = $product ? $product->get_type() : 'simple';

                            // Get SKU
                            $sku = $product ? $product->get_sku() : '';

                            ?>
                            <tr data-product-id="<?php echo $product_id; ?>"
                                data-category="<?php echo esc_attr(implode(',', $category_names)); ?>"
                                data-category-slugs="<?php echo esc_attr(implode(',', $category_slugs)); ?>"
                                data-type="<?php echo esc_attr($product_type); ?>" data-sku="<?php echo esc_attr($sku); ?>">
                                <td>
                                    <a href="<?php echo get_permalink(); ?>" class="woo-limit-product-name">
                                        <?php echo get_the_title(); ?>
                                    </a>
                                    <div class="woo-limit-product-meta">
                                        <?php if ($sku): ?>
                                            <span><strong>SKU:</strong> <?php echo esc_html($sku); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($category_names)): ?>
                                            <span><strong>Category:</strong>
                                                <?php echo esc_html(implode(', ', $category_names)); ?></span>
                                        <?php endif; ?>
                                        <span><strong>Type:</strong> <?php echo ucfirst($product_type); ?></span>
                                    </div>
                                </td>
                                <td><?php echo $range; ?></td>
                                <td><?php echo number_format($total_count); ?></td>
                                <td>
                                    <div class="woo-limit-numbers-display">
                                        <div class="woo-limit-numbers-toggle"
                                            onclick="toggleNumbers(this, 'available-<?php echo $product_id; ?>')">
                                            <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                                            <?php echo number_format($available_count); ?> numbers available
                                        </div>
                                        <div class="woo-limit-numbers-list" id="available-<?php echo $product_id; ?>">
                                            <?php echo $available_numbers ?: 'None available'; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="woo-limit-numbers-display">
                                        <div class="woo-limit-numbers-toggle"
                                            onclick="toggleNumbers(this, 'sold-<?php echo $product_id; ?>')">
                                            <span class="toggle-icon dashicons dashicons-arrow-down-alt2"></span>
                                            <?php echo number_format($sold_count); ?> numbers sold
                                        </div>
                                        <div class="woo-limit-numbers-list" id="sold-<?php echo $product_id; ?>">
                                            <?php if (!empty($sold_details)): ?>
                                                <?php foreach ($sold_details as $detail): ?>
                                                    <div class="number-item">
                                                        <strong><?php echo esc_html($detail['number']); ?></strong>
                                                        <?php if ($detail['order_status'] === 'ORDERED' && $detail['order_link'] !== 'N/A'): ?>
                                                            - <a href="<?php echo esc_url($detail['order_link']); ?>" target="_blank"
                                                                class="order-link">
                                                                #<?php echo esc_html($detail['order_id']); ?>
                                                            </a>
                                                        <?php elseif ($detail['order_status'] === 'BLOCKED (in cart)'): ?>
                                                            - <span class="order-status blocked">In Cart</span>

                                                        <?php else: ?>
                                                            - <span class="order-status"><?php echo esc_html($detail['order_status']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <?php echo $sold_numbers ?: 'None sold'; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="woo-limit-progress">
                                        <div class="woo-limit-progress-bar sold" style="width: <?php echo $sold_percentage; ?>%">
                                        </div>
                                    </div>
                                    <small><?php echo $sold_percentage; ?>% sold</small>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('post.php?post=' . $product_id . '&action=edit'); ?>"
                                        class="woo-limit-edit-btn">
                                        <span class="dashicons dashicons-edit"></span>
                                        Edit
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    wp_reset_postdata();
                } else {
                    ?>
                    <tr>
                        <td colspan="7">
                            <div class="woo-limit-empty-state">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <h3>No Limited Products Found</h3>
                                <p>You haven't set up any limited edition products yet. Create your first limited product to
                                    see reports here.</p>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <!-- Charts Section -->
    <div class="woo-limit-charts-section">
        <div class="woo-limit-charts-grid">
            <div class="woo-limit-chart-card">
                <h3>Sales Distribution</h3>
                <div class="chart-container">
                    <canvas id="salesDistributionChart"></canvas>
                </div>
            </div>

            <div class="woo-limit-chart-card">
                <h3>Monthly Sales Trend</h3>
                <div class="chart-container">
                    <canvas id="monthlyTrendChart"></canvas>
                </div>
            </div>

            <div class="woo-limit-chart-card">
                <h3>Product Performance</h3>
                <div class="chart-container">
                    <canvas id="productPerformanceChart"></canvas>
                </div>
            </div>

            <div class="woo-limit-chart-card">
                <h3>Availability Status</h3>
                <div class="chart-container">
                    <canvas id="availabilityChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    // Toggle numbers display
    function toggleNumbers(element, targetId) {
        const target = document.getElementById(targetId);
        const icon = element.querySelector('.toggle-icon');

        if (target.classList.contains('show')) {
            target.classList.remove('show');
            element.classList.remove('expanded');
        } else {
            target.classList.add('show');
            element.classList.add('expanded');
        }
    }

    // Export report to CSV
    function exportReport() {
        const table = document.getElementById('woo-limit-reports-table');
        const rows = table.querySelectorAll('tbody tr');

        let csv = 'Product Name,Category,Product Type,Number Range,Total Numbers,Available Count,Available Numbers,Sold Count,Sold Numbers,Sold Percentage\n';

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 6) {
                const productName = cells[0].querySelector('.woo-limit-product-name').textContent.trim();
                const productMeta = cells[0].querySelector('.woo-limit-product-meta');
                
                // Get category - look for the span containing category info
                let category = '';
                if (productMeta) {
                    const spans = productMeta.querySelectorAll('span');
                    spans.forEach(span => {
                        if (span.textContent.includes('Category:')) {
                            category = span.textContent.replace('Category:', '').trim();
                        }
                    });
                }
                
                // Get product type
                let type = '';
                if (productMeta) {
                    const spans = productMeta.querySelectorAll('span');
                    spans.forEach(span => {
                        if (span.textContent.includes('Type:')) {
                            type = span.textContent.replace('Type:', '').trim();
                        }
                    });
                }

                const range = cells[1].textContent.trim();
                const totalNumbers = cells[2].textContent.trim();
                const availableText = cells[3].textContent.trim();
                const soldText = cells[4].textContent.trim();
                const progressText = cells[5].textContent.trim();

                // Extract count numbers from text
                const availableCount = availableText.match(/([\d,]+)/)?.[1] || '0';
                const soldCount = soldText.match(/([\d,]+)/)?.[1] || '0';
                const soldPercentage = progressText.match(/(\d+\.?\d*)%/)?.[1] || '0';
                
                // Get available numbers list
                const availableNumbersList = cells[3].querySelector('.woo-limit-numbers-list');
                const availableNumbers = availableNumbersList ? availableNumbersList.textContent.trim().replace(/\s+/g, ' ') : '';
                
                // Get sold numbers list
                const soldNumbersList = cells[4].querySelector('.woo-limit-numbers-list');
                let soldNumbers = '';
                if (soldNumbersList) {
                    const numberItems = soldNumbersList.querySelectorAll('.number-item strong');
                    if (numberItems.length > 0) {
                        const nums = [];
                        numberItems.forEach(item => nums.push(item.textContent.trim()));
                        soldNumbers = nums.join(', ');
                    } else {
                        soldNumbers = soldNumbersList.textContent.trim().replace(/\s+/g, ' ');
                    }
                }

                csv += `"${productName}","${category}","${type}","${range}","${totalNumbers}","${availableCount}","${availableNumbers}","${soldCount}","${soldNumbers}","${soldPercentage}%"\n`;
            }
        });

        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'limited-products-report-' + new Date().toISOString().split('T')[0] + '.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    }

    // Export to Excel (XLSX) using SheetJS
    function exportToExcel() {
        // Show loading state
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'woo-limit-loading';
        loadingDiv.innerHTML = '<span class="dashicons dashicons-update"></span><p>Generating Excel report...</p>';
        document.querySelector('.woo-limit-table-wrapper').appendChild(loadingDiv);

        // Check if SheetJS is loaded
        if (typeof XLSX === 'undefined') {
            // Load SheetJS library
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
            script.onload = function () {
                generateExcelReport();
            };
            document.head.appendChild(script);
        } else {
            generateExcelReport();
        }
    }

    function generateExcelReport() {
        const table = document.getElementById('woo-limit-reports-table');
        const rows = table.querySelectorAll('tbody tr');

        // Prepare data for Excel with full number lists
        const excelData = [
            ['Product Name', 'Category', 'Product Type', 'Number Range', 'Total Numbers', 'Available Count', 'Available Numbers', 'Sold Count', 'Sold Numbers', 'Sold Percentage']
        ];

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells.length >= 6) {
                const productName = cells[0].querySelector('.woo-limit-product-name').textContent.trim();
                const productMeta = cells[0].querySelector('.woo-limit-product-meta');
                
                // Get category - look for the span containing category info
                let category = '';
                if (productMeta) {
                    const spans = productMeta.querySelectorAll('span');
                    spans.forEach(span => {
                        if (span.textContent.includes('Category:')) {
                            category = span.textContent.replace('Category:', '').trim();
                        }
                    });
                }
                
                // Get product type
                let type = '';
                if (productMeta) {
                    const spans = productMeta.querySelectorAll('span');
                    spans.forEach(span => {
                        if (span.textContent.includes('Type:')) {
                            type = span.textContent.replace('Type:', '').trim();
                        }
                    });
                }

                const range = cells[1].textContent.trim();
                const totalNumbers = cells[2].textContent.trim();
                const availableText = cells[3].textContent.trim();
                const soldText = cells[4].textContent.trim();
                const progressText = cells[5].textContent.trim();

                // Extract count numbers from text
                const availableCount = availableText.match(/([\d,]+)/)?.[1] || '0';
                const soldCount = soldText.match(/([\d,]+)/)?.[1] || '0';
                const soldPercentage = progressText.match(/(\d+\.?\d*)%/)?.[1] || '0';
                
                // Get available numbers list
                const availableNumbersList = cells[3].querySelector('.woo-limit-numbers-list');
                const availableNumbers = availableNumbersList ? availableNumbersList.textContent.trim().replace(/\s+/g, ' ') : '';
                
                // Get sold numbers list
                const soldNumbersList = cells[4].querySelector('.woo-limit-numbers-list');
                let soldNumbers = '';
                if (soldNumbersList) {
                    const numberItems = soldNumbersList.querySelectorAll('.number-item strong');
                    if (numberItems.length > 0) {
                        const nums = [];
                        numberItems.forEach(item => nums.push(item.textContent.trim()));
                        soldNumbers = nums.join(', ');
                    } else {
                        soldNumbers = soldNumbersList.textContent.trim().replace(/\s+/g, ' ');
                    }
                }

                excelData.push([
                    productName, category, type, range, totalNumbers, availableCount, availableNumbers, soldCount, soldNumbers, soldPercentage + '%'
                ]);
            }
        });

        // Create workbook and worksheet
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(excelData);

        // Set column widths
        const colWidths = [
            { wch: 30 }, // Product Name
            { wch: 20 }, // Category
            { wch: 12 }, // Product Type
            { wch: 15 }, // Number Range
            { wch: 12 }, // Total Numbers
            { wch: 15 }, // Available Count
            { wch: 40 }, // Available Numbers
            { wch: 12 }, // Sold Count
            { wch: 40 }, // Sold Numbers
            { wch: 15 }  // Sold Percentage
        ];
        ws['!cols'] = colWidths;

        // Add worksheet to workbook
        XLSX.utils.book_append_sheet(wb, ws, 'Limited Products Report');

        // Generate and download file
        const fileName = 'limited-products-report-' + new Date().toISOString().split('T')[0] + '.xlsx';
        XLSX.writeFile(wb, fileName);

        // Remove loading state
        const loadingDiv = document.querySelector('.woo-limit-loading');
        if (loadingDiv) {
            loadingDiv.remove();
        }
    }

    // Refresh report
    function refreshReport() {
        location.reload();
    }

    // Initialize DataTable with custom options
    jQuery(document).ready(function ($) {
        $('#woo-limit-reports-table').DataTable({
            pageLength: 25,
            order: [[0, 'asc']],
            responsive: true,
            language: {
                search: "Search products:",
                lengthMenu: "Show _MENU_ products per page",
                info: "Showing _START_ to _END_ of _TOTAL_ products",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            columnDefs: [
                { orderable: false, targets: [6] } // Disable sorting on Actions column
            ]
        });

        // Custom filtering
        $('#product-search').on('keyup', function () {
            $('#woo-limit-reports-table').DataTable().search(this.value).draw();
        });

        $('#category-filter').on('change', function () {
            const filter = $(this).val();
            const table = $('#woo-limit-reports-table').DataTable();

            if (filter === '') {
                table.draw();
            } else {
                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    const row = $(settings.aoData[dataIndex].nTr);
                    const categorySlugs = row.attr('data-category-slugs');

                    if (categorySlugs) {
                        const slugs = categorySlugs.split(',');
                        return slugs.includes(filter);
                    }
                    return false;
                });
                table.draw();
                $.fn.dataTable.ext.search.pop();
            }
        });

        $('#availability-filter').on('change', function () {
            const filter = $(this).val();
            const table = $('#woo-limit-reports-table').DataTable();

            if (filter === '') {
                table.draw();
            } else {
                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    const availableText = data[3];
                    const soldText = data[4];
                    const availableCount = parseInt(availableText.match(/([\d,]+)/)?.[1].replace(',', '') || '0');
                    const soldCount = parseInt(soldText.match(/([\d,]+)/)?.[1].replace(',', '') || '0');

                    if (filter === 'available' && availableCount > 0) return true;
                    if (filter === 'sold' && soldCount > 0 && availableCount === 0) return true;
                    if (filter === 'partial' && soldCount > 0 && availableCount > 0) return true;
                    return false;
                });
                table.draw();
                $.fn.dataTable.ext.search.pop();
            }
        });

        $('#product-type-filter').on('change', function () {
            const filter = $(this).val();
            const table = $('#woo-limit-reports-table').DataTable();

            if (filter === '') {
                table.draw();
            } else {
                $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                    const row = $(settings.aoData[dataIndex].nTr);
                    const type = row.attr('data-type');
                    return type === filter;
                });
                table.draw();
                $.fn.dataTable.ext.search.pop();
            }
        });

        $('#sort-by').on('change', function () {
            const sortBy = $(this).val();
            const table = $('#woo-limit-reports-table').DataTable();

            switch (sortBy) {
                case 'name':
                    table.order([0, 'asc']).draw();
                    break;
                case 'available':
                    table.order([3, 'desc']).draw();
                    break;
                case 'sold':
                    table.order([4, 'desc']).draw();
                    break;
                case 'percentage':
                    // Custom sorting for percentage would need more complex logic
                    table.order([0, 'asc']).draw();
                    break;
                case 'sku':
                    // Sort by SKU (would need to extract from data attributes)
                    table.order([0, 'asc']).draw();
                    break;
            }
        });

        // Initialize Charts
        initializeCharts();

        // Clear In-Cart (All)
        $('#clear-in-cart-all').on('click', function (e) {
            e.preventDefault();
            if (!confirm('This will clear ALL in-cart (blocked) numbers across all products. Continue?')) return;
            $.post(ajaxurl, {
                action: 'ijwlp_clear_in_cart',
                _ajax_nonce: '<?php echo wp_create_nonce('ijwlp_clear_in_cart'); ?>'
            }, function (resp) {
                if (resp && resp.success) {
                    alert('Cleared ' + (resp.data && resp.data.cleared ? resp.data.cleared : 0) + ' in-cart entries.');
                    location.reload();
                } else {
                    alert('Failed to clear in-cart entries.');
                }
            });
        });

        // Per-product clear in-cart
        $(document).on('click', '.clear-in-cart-product', function (e) {
            e.preventDefault();
            var pid = $(this).data('product-id');
            if (!pid) return;
            if (!confirm('Clear in-cart (blocked) numbers for this product?')) return;
            $.post(ajaxurl, {
                action: 'ijwlp_clear_in_cart',
                product_id: pid,
                _ajax_nonce: '<?php echo wp_create_nonce('ijwlp_clear_in_cart'); ?>'
            }, function (resp) {
                if (resp && resp.success) {
                    alert('Cleared ' + (resp.data && resp.data.cleared ? resp.data.cleared : 0) + ' in-cart entries for product #' + pid + '.');
                    location.reload();
                } else {
                    alert('Failed to clear in-cart entries for product.');
                }
            });
        });
    });

    // Chart initialization function
    function initializeCharts() {
        // Chart data from PHP
        const chartProducts = <?php echo json_encode($chart_products); ?>;
        const monthlyData = <?php echo json_encode($monthly_data); ?>;

        // Sales Distribution Chart (Doughnut)
        const salesDistributionCtx = document.getElementById('salesDistributionChart').getContext('2d');
        new Chart(salesDistributionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Sold'],
                datasets: [{
                    data: [
                        chartProducts.reduce((sum, product) => sum + product.available, 0),
                        chartProducts.reduce((sum, product) => sum + product.sold, 0)
                    ],
                    backgroundColor: [
                        '#00a32a',
                        '#d63638'
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // Monthly Trend Chart (Line)
        const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        new Chart(monthlyTrendCtx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => item.month),
                datasets: [{
                    label: 'Sales',
                    data: monthlyData.map(item => item.sales),
                    borderColor: '#1a1a1a',
                    backgroundColor: 'rgba(26, 26, 26, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#1a1a1a',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Product Performance Chart (Bar)
        const productPerformanceCtx = document.getElementById('productPerformanceChart').getContext('2d');
        const topProducts = chartProducts.slice(0, 5); // Top 5 products

        new Chart(productPerformanceCtx, {
            type: 'bar',
            data: {
                labels: topProducts.map(product => product.name.length > 15 ? product.name.substring(0, 15) + '...' : product.name),
                datasets: [{
                    label: 'Sold Percentage',
                    data: topProducts.map(product => product.percentage),
                    backgroundColor: 'rgba(26, 26, 26, 0.8)',
                    borderColor: '#1a1a1a',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Availability Status Chart (Pie)
        const availabilityCtx = document.getElementById('availabilityChart').getContext('2d');

        // Categorize products by availability status
        const fullyAvailable = chartProducts.filter(p => p.available > 0 && p.sold === 0).length;
        const partiallySold = chartProducts.filter(p => p.available > 0 && p.sold > 0).length;
        const soldOut = chartProducts.filter(p => p.available === 0 && p.sold > 0).length;

        new Chart(availabilityCtx, {
            type: 'pie',
            data: {
                labels: ['Fully Available', 'Partially Sold', 'Sold Out'],
                datasets: [{
                    data: [fullyAvailable, partiallySold, soldOut],
                    backgroundColor: [
                        '#00a32a',
                        '#ffa500',
                        '#d63638'
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    }
</script>