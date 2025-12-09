<?php
/**
 * Custom Order Status: Partially Shipped
 * 
 * Registers a custom WooCommerce order status and sends email notification
 * when an order is marked as Partially Shipped.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IJWLP_Order_Status
{
    /**
     * Flag to track when partially shipped email is being sent
     */
    private $is_partially_shipped_email = false;

    /**
     * Constructor - Hook into WooCommerce
     */
    public function __construct()
    {
        // Register custom order status
        add_filter('woocommerce_register_shop_order_post_statuses', array($this, 'register_partially_shipped_status'));

        // Add to order status dropdown
        add_filter('wc_order_statuses', array($this, 'add_partially_shipped_to_dropdown'));

        // Send email when order status changes to partially-shipped
        add_action('woocommerce_order_status_partially-shipped', array($this, 'send_partially_shipped_email'), 20, 2);

        // Filter email content to replace "completed" with "Partially Shipped"
        add_filter('woocommerce_mail_content', array($this, 'modify_email_content'), 10, 1);
    }

    /**
     * Register the Partially Shipped order status
     */
    public function register_partially_shipped_status($order_statuses)
    {
        $order_statuses['wc-partially-shipped'] = array(
            'label' => _x('Partially Shipped', 'Order status', 'woocommerce'),
            'public' => false,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop(
                'Partially Shipped <span class="count">(%s)</span>',
                'Partially Shipped <span class="count">(%s)</span>',
                'woocommerce'
            ),
        );

        return $order_statuses;
    }

    /**
     * Add Partially Shipped to the order status dropdown
     */
    public function add_partially_shipped_to_dropdown($order_statuses)
    {
        $order_statuses['wc-partially-shipped'] = _x('Partially Shipped', 'Order status', 'woocommerce');
        return $order_statuses;
    }

    /**
     * Send email notification when order is marked as Partially Shipped
     */
    public function send_partially_shipped_email($order_id, $order)
    {
        if (!$order_id) {
            return;
        }

        // Custom email subject & heading
        $subject = __('Your Order Has Been Partially Shipped', 'woocommerce');
        $heading = __('Partially Shipped', 'woocommerce');

        // Load WC mailer
        $mailer = WC()->mailer()->get_emails();

        // Use the Completed Order email template as base
        if (isset($mailer['WC_Email_Customer_Completed_Order'])) {
            $email = $mailer['WC_Email_Customer_Completed_Order'];

            // Set custom subject + heading
            $email->settings['subject'] = $subject;
            $email->settings['heading'] = $heading;

            // Set flag to modify email content
            $this->is_partially_shipped_email = true;

            // Trigger the email
            $email->trigger($order_id);

            // Reset the flag
            $this->is_partially_shipped_email = false;
        }
    }

    /**
     * Modify email content to replace "completed" with "Partially Shipped"
     */
    public function modify_email_content($content)
    {
        if ($this->is_partially_shipped_email) {
            // Replace variations of "completed" with "Partially Shipped"
            $content = str_ireplace(
                array('has been completed', 'is now complete', 'order complete', 'order completed', 'completed'),
                array('has been Partially Shipped', 'is now Partially Shipped', 'Partially Shipped', 'Partially Shipped', 'Partially Shipped'),
                $content
            );
        }
        return $content;
    }
}
