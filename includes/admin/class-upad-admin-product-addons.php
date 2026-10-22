<?php
/**
 * Admin Product Add-ons
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_Admin_Product_Addons {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'product_tab_content' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_meta' ) );
    }

    /**
     * Add product data tab
     */
    public function add_product_tab( $tabs ) {
        $tabs['upad_addons'] = array(
            'label'    => __( 'Add-ons', 'wc-options-studio' ),
            'target'   => 'upad_addons_data',
            'class'    => array(),
            'priority' => 70,
        );
        return $tabs;
    }

    /**
     * Product tab content
     */
    public function product_tab_content() {
        global $post;
        include UPAD_PLUGIN_DIR . 'includes/admin/views/product-addons.php';
    }

    /**
     * Save product meta
     */
    public function save_product_meta( $post_id ) {
        // Save global addons setting
        $use_global = isset( $_POST['_upad_use_global_addons'] ) ? 'yes' : 'no';
        update_post_meta( $post_id, '_upad_use_global_addons', $use_global );
    }
}
