<?php
/**
 * Partially Completed Order Email
 * 
 * This email is sent to the customer when an order status changes to "Partially Completed"
 * It works similar to the WooCommerce Completed Order email
 *
 * @package WooCommerce Limited Product
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!class_exists('IJWLP_Email_Partial_Complete')) {

	/**
	 * Partially Completed Order Email Class
	 * 
	 * Extends WooCommerce email class to send notifications when order is partially completed
	 */
	class IJWLP_Email_Partial_Complete extends WC_Email {

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->id             = 'customer_partial_complete_order';
			$this->customer_email = true;
			$this->title          = __('Partially Completed Order', 'woo-limit-product');
			$this->description    = __('This email is sent to customers when their order has been partially completed.', 'woo-limit-product');
			$this->template_html  = 'emails/customer-completed-order.php';
			$this->template_plain = 'emails/plain/customer-completed-order.php';
			$this->placeholders   = array(
				'{order_date}'   => '',
				'{order_number}' => '',
			);

			// Default heading and subject
			$this->heading = __('Your order has been partially completed', 'woo-limit-product');
			$this->subject = __('Your {site_title} order is partially complete', 'woo-limit-product');

			// Call parent constructor
			parent::__construct();
		}

		/**
		 * Get email subject
		 *
		 * @return string
		 */
		public function get_default_subject() {
			return __('Your {site_title} order is partially complete', 'woo-limit-product');
		}

		/**
		 * Get email heading
		 *
		 * @return string
		 */
		public function get_default_heading() {
			return __('Your order has been partially completed', 'woo-limit-product');
		}

		/**
		 * Trigger the sending of this email
		 *
		 * @param int $order_id Order ID
		 * @param WC_Order|bool $order Order object
		 */
		public function trigger($order_id, $order = false) {
			$this->setup_locale();

			if ($order_id && !is_a($order, 'WC_Order')) {
				$order = wc_get_order($order_id);
			}

			if (is_a($order, 'WC_Order')) {
				$this->object                         = $order;
				$this->recipient                      = $this->object->get_billing_email();
				$this->placeholders['{order_date}']   = wc_format_datetime($this->object->get_date_created());
				$this->placeholders['{order_number}'] = $this->object->get_order_number();
			}

			if ($this->is_enabled() && $this->get_recipient()) {
				$this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
			}

			$this->restore_locale();
		}

		/**
		 * Get content html
		 *
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => false,
					'email'              => $this,
				)
			);
		}

		/**
		 * Get content plain
		 *
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				array(
					'order'              => $this->object,
					'email_heading'      => $this->get_heading(),
					'additional_content' => $this->get_additional_content(),
					'sent_to_admin'      => false,
					'plain_text'         => true,
					'email'              => $this,
				)
			);
		}

		/**
		 * Default content to show below main email content
		 *
		 * @return string
		 */
		public function get_default_additional_content() {
			return __('Thank you for shopping with us. Your order has been partially completed and some items are on their way!', 'woo-limit-product');
		}

		/**
		 * Initialize settings form fields
		 */
		public function init_form_fields() {
			/* translators: %s: available placeholders list */
			$placeholder_text = sprintf(__('Available placeholders: %s', 'woo-limit-product'), '<code>' . esc_html(implode('</code>, <code>', array_keys($this->placeholders))) . '</code>');

			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __('Enable/Disable', 'woo-limit-product'),
					'type'    => 'checkbox',
					'label'   => __('Enable this email notification', 'woo-limit-product'),
					'default' => 'yes',
				),
				'subject'            => array(
					'title'       => __('Subject', 'woo-limit-product'),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'            => array(
					'title'       => __('Email heading', 'woo-limit-product'),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => $placeholder_text,
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'additional_content' => array(
					'title'       => __('Additional content', 'woo-limit-product'),
					'description' => __('Text to appear below the main email content.', 'woo-limit-product') . ' ' . $placeholder_text,
					'css'         => 'width:400px; height: 75px;',
					'placeholder' => $this->get_default_additional_content(),
					'type'        => 'textarea',
					'default'     => $this->get_default_additional_content(),
					'desc_tip'    => true,
				),
				'email_type'         => array(
					'title'       => __('Email type', 'woo-limit-product'),
					'type'        => 'select',
					'description' => __('Choose which format of email to send.', 'woo-limit-product'),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}
}
