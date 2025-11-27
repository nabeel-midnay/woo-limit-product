<?php

if (!defined('ABSPATH'))
    exit;

class IJWLP_Api
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
    private $_active = false;

    public function __construct()
    {
        add_action('rest_api_init', function () {

            register_rest_route('ijwlp/v1', '/ijwlpsettings/', array(
                'methods' => 'GET',
                'callback' => array($this, 'pluginSettings'),
                'permission_callback' => array($this, 'get_permission')
            ));

            register_rest_route('ijwlp/v1', '/ijwlpsettings/', array(
                'methods' => 'POST',
                'callback' => array($this, 'pluginSettings'),
                'permission_callback' => array($this, 'get_permission'),
            ));

            register_rest_route('ijwlp/v1', '/ijwlporders/', array(
                'methods' => 'GET',
                'callback' => array($this, 'pluginOrders'),
                'permission_callback' => array($this, 'get_permission')
            ));

            register_rest_route('ijwlp/v1', '/ijwlporders/', array(
                'methods' => 'POST',
                'callback' => array($this, 'pluginOrders'),
                'permission_callback' => array($this, 'get_permission')
            ));

            register_rest_route('ijwlp/v1', '/productsearch', array(
                'methods' => 'GET',
                'callback' => array($this, 'products_search'),
                'permission_callback' => array($this, 'get_permission')
            ));

            register_rest_route('ijwlp/v1', '/usersearch', array(
                'methods' => 'GET',
                'callback' => array($this, 'user_search'),
                'permission_callback' => array($this, 'get_permission')
            ));

        });
    }

    /**
     *
     * Ensures only one instance of IJWLP is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see WordPress_Plugin_Template()
     * @return Main IJWLP instance
     */
    public static function instance($file = '', $version = '1.0.0')
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self($file, $version);
        }
        return self::$_instance;
    }

    /**
     * @search parameter - title
     */
    public function products_search($arg)
    {
        global $wpdb;
        $params = $arg->get_params();
        $search = $params['search'];

        $results = $wpdb->get_results ( "SELECT post_title as label, ID as value, post_type as type FROM {$wpdb->prefix}posts WHERE post_type in ( 'product' ) AND post_status = 'publish' AND ( post_title LIKE '" . esc_sql ( $wpdb->esc_like ( $search ) ) . "%' OR post_title LIKE '%" . esc_sql ( $wpdb->esc_like ( $search ) ) . "%' OR post_title LIKE '%" . esc_sql ( $wpdb->esc_like ( $search ) ) . "' ) GROUP BY ID, post_title" );

        foreach ( $results as $result ) { 
            $result->value = (int)$result->value;
        } 

        return new WP_REST_Response($results, 200);
    }

    public function user_search($arg)
    {
        global $wpdb;
        $params     = $arg->get_params();
        $search     = $params['search'];
        $tax        = ( $params['tax'] == 'tag' ) ? 'product_tag' : 'product_cat';

        $results    = $wpdb->get_results ( "SELECT ID AS value, user_login AS label FROM {$wpdb->prefix}users WHERE ( user_login LIKE '" . esc_sql ( $wpdb->esc_like ( $search ) ) . "%' OR user_login LIKE '%" . esc_sql ( $wpdb->esc_like ( $search ) ) . "%' OR user_login LIKE '%" . esc_sql ( $wpdb->esc_like ( $search ) ) . "' )" );

        foreach ( $results as $result ) { 
            $result->value = (int)$result->value;
        } 

        return new WP_REST_Response($results, 200);
    }

    function pluginSettings($data)
    {
            
        $data = $data->get_params(); 

        if( $data['settings'] ) { 

            $enablelimit    = isset($data['settings']['enablelimit']) ? $data['settings']['enablelimit'] : '';
            $limitposition  = isset($data['settings']['limitposition']) ? $data['settings']['limitposition'] : '';
            $limitlabel     = isset($data['settings']['limitlabel']) ? $data['settings']['limitlabel'] : '';
            $limitlabelcart = isset($data['settings']['limitlabelcart']) ? $data['settings']['limitlabelcart'] : '';
            $limittime      = isset($data['settings']['limittime']) ? $data['settings']['limittime'] : '';

            $advanced_settings = array(
                'enablelimit'   => $enablelimit,
                'limitposition' => $limitposition,
                'limitlabel'    => $limitlabel,
                'limitlabelcart'=> $limitlabelcart,
                'limittime'     => $limittime,
            );

            if ( false === get_option ( 'ijwlp_advanced_settings' ) )
                add_option ( 'ijwlp_advanced_settings', $advanced_settings, '', 'yes' );
            else
                update_option( 'ijwlp_advanced_settings', $advanced_settings );

        }

        // Advanced Settings
        $advanced_settings          = get_option ( 'ijwlp_advanced_settings' ) ? get_option ( 'ijwlp_advanced_settings' ) : [];
        $result['enablelimit']      = array_key_exists ( 'enablelimit', $advanced_settings ) ? $advanced_settings['enablelimit'] : '';
        $result['limitposition']    = array_key_exists ( 'limitposition', $advanced_settings ) ? $advanced_settings['limitposition'] : '';
        $result['limitlabel']       = array_key_exists ( 'limitlabel', $advanced_settings ) ? $advanced_settings['limitlabel'] : '';
        $result['limitlabelcart']   = array_key_exists ( 'limitlabelcart', $advanced_settings ) ? $advanced_settings['limitlabelcart'] : '';
        $result['limittime']        = array_key_exists ( 'limittime', $advanced_settings ) ? $advanced_settings['limittime'] : 15;

        return new WP_REST_Response($result, 200);

    }

    public function delete_transient()
    {

        delete_transient(IJWLP_PRODUCTS_TRANSIENT_KEY);

    }

    function pluginOrders($data)
    {

        $result = array();

        global $table_prefix, $wpdb;

        $params     = $data->get_params();
        $filtype    = $params ? $params['filtertype'] : '';
        $filvalue   = $params ? $params['filtervalue'] : '';

        // Table
        $table      = $table_prefix . 'woo_limit'; 
        if ( $filtype != '' && $filvalue != '' ) {
            if ( $filtype == 'user' ) {
                $results    = $wpdb->get_results ( "SELECT * FROM $table WHERE `user_id` = $filvalue ORDER BY DATE(time) DESC" );
            } else {
                $allvariations = $wpdb->get_col ( $wpdb->prepare ( "SELECT id FROM ".$table_prefix."posts WHERE post_parent = ".$filvalue." and post_type = 'product_variation'" ) ); 
                $allvariations = !empty($allvariations) ? implode(",",$allvariations) : $filvalue;
                $results    = $wpdb->get_results( "SELECT * FROM $table WHERE `product_id` in ( $allvariations ) ORDER BY DATE(time) DESC" ); 
            }
        } else {
            $results    = $wpdb->get_results( "SELECT * FROM $table ORDER BY DATE(time) DESC" ); 
        }
        $usermeta   = [];

        if ( !empty ( $results ) ) 
        { 
            foreach ( $results as $row ) {  

                $uid = $row->user_id;
                $key = !empty ( $usermeta ) ? array_search ( $uid, array_column ( $usermeta, 'uid') ) : -1;
                if ( $key >= 0 ) { 
                    $username   = $usermeta[$key]['uname'];
                } else {
                    $query = $wpdb->get_row( $wpdb->prepare("SELECT `display_name`,`user_nicename` FROM $wpdb->users WHERE `ID` = $uid") );
                    $username = ( $query != null ) ? ( $query->display_name ? $query->display_name : $query->user_nicename ) : '';
                    $usermeta[] = array( 'uid' => $uid , 'uname' => $username );
                }

                $order_link = $row->order_id ? admin_url( '/post.php?post='.$row->order_id.'&action=edit' ) : '';
                $time 		= $row->time ? date_i18n ( 'Y-m-d', strtotime($row->time), true ) : '';
                // in case get_the_title didnt work
                // $titlequery = $wpdb->get_results ( $wpdb->prepare ( "SELECT `post_title` FROM $wpdb->posts WHERE `ID` = $row->product_id" ) ); 

                $result[] = array ( 
                    'id'            => $row->id, 
                    'user_id'       => $username, 
                    'product_id'    => html_entity_decode(get_the_title($row->product_id)), 
                    'limit_no'      => $row->limit_no, 
                    'status'        => $row->status, 
                    'order_link'    => $order_link, 
                    'order_status'  => $row->order_status, 
                    'date'          => $time, 
                );
            }
        }

        return new WP_REST_Response($result, 200);

    }

    /**
     * Permission Callback
     **/
    public function get_permission()
    {
        if (current_user_can('administrator') || current_user_can('manage_woocommerce')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?'), $this->_version);
    }

}
