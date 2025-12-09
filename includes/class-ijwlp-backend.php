<?php

if (!defined('ABSPATH'))
    exit;

class IJWLP_Backend
{

    /**
     * @var    object
     * @access  private
     * @since    1.0.0
     */
    private static $_instance = null;

    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;

    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_token;

    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * Suffix for Javascripts.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $script_suffix;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;
    public $hook_suffix = array();
    public $plugin_slug;

    public function __construct($file = '', $version = '1.0.0')
    {
        $this->_version = $version;
        $this->_token = IJWLP_TOKEN;
        $this->file = $file;
        $this->dir = dirname($this->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));

        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'), 10, 1);
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_styles'), 10, 1);
        add_filter('woocommerce_product_data_tabs', array($this, 'woo_limit_admin_tab'), 10, 1);
        add_action('woocommerce_product_data_panels', array($this, 'woo_limit_tab_fields'), 20, 0);
        add_action('woocommerce_process_product_meta', array($this, 'woo_limit_save_fields'), 30, 1);
    
		// Display delivery preference in admin order details
		add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_delivery_preference_in_admin'), 10, 1);
		add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_delivery_preference_in_admin_shipping'), 10, 1);
    
    }

    public static function instance($file = '', $version = '1.0.0')
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }
        return self::$_instance;
    }

    public function woo_limit_admin_orders()
    {
        IJWLP_Backend::view('admin-orders', []);
    }

    public function woo_limit_admin_settings()
    {
        IJWLP_Backend::view('admin-settings', []);
    }

    static function view($view, $data = array())
    {
        extract($data);
        include(plugin_dir_path(__FILE__) . 'views/' . $view . '.php');
    }

    public function admin_enqueue_styles($hook = '')
    {
        $currentScreen = get_current_screen();
        $screenID = $currentScreen->id;

        if (strpos($screenID, 'woo-limit') !== false) {

            wp_register_style($this->_token . '-admin', esc_url($this->assets_url) . 'css/backend.css', array(), $this->_version);
            wp_enqueue_style($this->_token . '-admin');
        }

        if ($screenID == 'product') {
            wp_enqueue_style($this->_token . '-admin-style', esc_url($this->assets_url) . 'css/admin.css');
        }
    }

    public function admin_enqueue_scripts($hook = '')
    {
        $currentScreen = get_current_screen();
        $screenID = $currentScreen->id;

        if (strpos($screenID, 'woo-limit') !== false) {
            wp_enqueue_script('jquery');

            if (!wp_script_is('wp-i18n', 'registered')) {
                wp_register_script('wp-i18n', esc_url($this->assets_url) . 'js/i18n.min.js', array('jquery'), $this->_version, true);
            }

            wp_enqueue_script($this->_token . '-backend-script', esc_url($this->assets_url) . 'js/backend.js?v=' . time(), array('jquery', 'wp-i18n'), $this->_version, true);
            wp_localize_script(
                $this->_token . '-backend-script',
                'ijwlp_object',
                array(
                    'api_nonce' => wp_create_nonce('wp_rest'),
                    'root' => rest_url('ijwlp/v1/')
                )
            );
        }

        if ($screenID == 'product') {
            wp_enqueue_script($this->_token . '-admin-product-script', esc_url($this->assets_url) . 'js/admin-product.js?v=' . time(), array('jquery', 'wp-i18n'), $this->_version, true);
        }
    }

    public function woo_limit_admin_tab($product_data_tabs)
    {

        $product_data_tabs['woo-limit-product'] = array('label' =>  __('Woo Limit Product', 'woolimited'), 'target' => 'woo_limit_product', 'class' => 'woolimitedtab',);
        return $product_data_tabs;
    }

    public function woo_limit_tab_fields()

    {
        $pro_id = get_the_ID();

        $sold_limited_nos       = get_post_meta($pro_id, '_sold_limited_nos', true);
        $temp_limited_nos       = get_post_meta($pro_id, '_temp_limited_nos', true);

        $readonly = array();

        if ((!empty($temp_limited_nos)) || (!empty($sold_limited_nos))) {
            $readonly = array('readonly' => 'readonly');
        }

        ob_start(); ?>

        <div id="woo_limit_product" class="panel woocommerce_options_panel">
            <p class="form-field _woo_limit_status_field" style="font-size:1.3em; font-weight:700;padding: 0px 10px !important;margin: 1em 0 1.5em 0;">Woo Limit Product Options</h3>
                <?php
                woocommerce_wp_text_input(array(
                    'id'            => '_woo_limit_max_quantity',
                    'label'         => esc_html__('Count', 'woolimited'),
                    'desc_tip'      => false,
                    'type'          => 'number',
                    'description'     => esc_html__('Maximum products that can be added to cart.', 'woolimited'),
                    'custom_attributes' => $readonly,
                ));

                woocommerce_wp_checkbox(array(
                    'id'            => '_woo_limit_status',
                    'label'         => esc_html__('Status', 'woolimited'),
                    'description'   => esc_html__('Enable / disable limited number options', 'woolimited'),
                ));

                woocommerce_wp_text_input(array(
                    'id'            => '_woo_limit_start_value',
                    'label'         => esc_html__('Range Starts', 'woolimited'),
                    'description'     => esc_html__('Start limit', 'woolimited'),
                    'type'          => 'number',
                    'desc_tip'      => false,
                    'custom_attributes' => $readonly,

                ));

                woocommerce_wp_text_input(array(
                    'id'            => '_woo_limit_end_value',
                    'label'         => esc_html__('Range Ends', 'woolimited'),
                    'description'     => esc_html__('End limit', 'woolimited'),
                    'type'          => 'number',
                    'desc_tip'      => false,
                    'custom_attributes' => $readonly,
                ));
                ?>
        </div>
<?php
        $data = ob_get_contents();
        ob_end_clean();
        echo $data;
    }

    public function woo_limit_save_fields($post_id)
    {
        $max_quantity        = isset($_POST['_woo_limit_max_quantity']) ? $_POST['_woo_limit_max_quantity'] : '';

        //Limited
        $limit_status     = isset($_POST['_woo_limit_status']) ? $_POST['_woo_limit_status'] : '';
        $limit_start      = isset($_POST['_woo_limit_start_value']) ? $_POST['_woo_limit_start_value'] : '';
        $limit_end        = isset($_POST['_woo_limit_end_value']) ? $_POST['_woo_limit_end_value'] : '';

        if (!empty($max_quantity)) {
            update_post_meta($post_id, '_woo_limit_max_quantity', $max_quantity);
        } else {
            delete_post_meta($post_id, '_woo_limit_max_quantity');
        }

        //Limited
        if (!empty($limit_status)) {
            update_post_meta($post_id, '_woo_limit_status', $limit_status);

            if (!empty($limit_start)) {
                update_post_meta($post_id, '_woo_limit_start_value', $limit_start);
            } else {
                delete_post_meta($post_id, '_woo_limit_start_value');
            }

            if (!empty($limit_end)) {
                update_post_meta($post_id, '_woo_limit_end_value', $limit_end);
            } else {
                delete_post_meta($post_id, '_woo_limit_end_value');
            }
        } else {
            delete_post_meta($post_id, '_woo_limit_status');
            delete_post_meta($post_id, '_woo_limit_start_value');
            delete_post_meta($post_id, '_woo_limit_end_value');
        }
    }

	/**
	 * Display delivery preference in admin order details (after billing address)
	 * 
	 * @param WC_Order $order
	 */
	public function display_delivery_preference_in_admin($order)
	{
		$delivery_preference = $order->get_meta('_delivery_preference');
		
		if (empty($delivery_preference)) {
			return;
		}

		$preference_label = $delivery_preference === 'partial_delivery' 
			? __('Partial Delivery (available items now, backordered later)', 'woo-limit-product')
			: __('Complete Delivery (wait for all items)', 'woo-limit-product');

		?>
		<div class="delivery-preference-admin" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #2271b1;">
			<p style="margin: 0;">
				<strong><?php echo esc_html__('Delivery Preference:', 'woo-limit-product'); ?></strong><br>
				<?php echo esc_html($preference_label); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display delivery preference in admin order details (after shipping address)
	 * This is an alternative location if you prefer it in the shipping section
	 * 
	 * @param WC_Order $order
	 */
	public function display_delivery_preference_in_admin_shipping($order)
	{
		// Uncomment the code below if you want to show it after shipping address instead
		// $this->display_delivery_preference_in_admin($order);
	}
}
