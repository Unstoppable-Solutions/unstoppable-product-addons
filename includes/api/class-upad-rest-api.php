<?php
/**
 * REST API Handler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_REST_API {

    protected static $_instance = null;

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST routes
     */
    public function register_routes() {
        $namespace = 'wcos/v1';

        // Global Groups
        register_rest_route( $namespace, '/groups', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_groups' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_group' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
        ) );

        register_rest_route( $namespace, '/groups/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_group' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_group' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_group' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
        ) );

        // Product Add-ons
        register_rest_route( $namespace, '/products/(?P<product_id>\d+)/addons', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_product_addons' ),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_product_addon' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
        ) );

        register_rest_route( $namespace, '/addons/(?P<id>\d+)', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_addon' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_addon' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_addon' ),
                'permission_callback' => array( $this, 'manage_permission' ),
            ),
        ) );
    }

    /**
     * Check manage permission
     */
    public function manage_permission() {
        return current_user_can( 'manage_woocommerce' );
    }

    /**
     * Get all groups
     */
    public function get_groups( $request ) {
        $groups = UPAD_Global_Addons::instance()->get_groups();
        return rest_ensure_response( $groups );
    }

    /**
     * Create group
     */
    public function create_group( $request ) {
        $data = $request->get_params();
        $result = UPAD_Global_Addons::instance()->create_group( $data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array( 'id' => $result ) );
    }

    /**
     * Get single group
     */
    public function get_group( $request ) {
        $group = UPAD_Global_Addons::instance()->get_group( $request['id'] );
        
        if ( ! $group ) {
            return new WP_Error( 'not_found', __( 'Group not found.', 'unstoppable-product-addons' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response( $group );
    }

    /**
     * Update group
     */
    public function update_group( $request ) {
        $data = $request->get_params();
        $result = UPAD_Global_Addons::instance()->update_group( $request['id'], $data );

        return rest_ensure_response( array( 'success' => (bool) $result ) );
    }

    /**
     * Delete group
     */
    public function delete_group( $request ) {
        $result = UPAD_Global_Addons::instance()->delete_group( $request['id'] );
        return rest_ensure_response( array( 'success' => (bool) $result ) );
    }

    /**
     * Get product addons
     */
    public function get_product_addons( $request ) {
        $addons = UPAD_Product_Addons::instance()->get_product_addons( $request['product_id'] );
        return rest_ensure_response( $addons );
    }

    /**
     * Create product addon
     */
    public function create_product_addon( $request ) {
        $data = $request->get_params();
        $result = UPAD_Product_Addons::instance()->create_addon( $request['product_id'], $data );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return rest_ensure_response( array( 'id' => $result ) );
    }

    /**
     * Get single addon
     */
    public function get_addon( $request ) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table requires direct query.
        $addon = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_addons WHERE id = %d",
            $request['id']
        ) );

        if ( ! $addon ) {
            return new WP_Error( 'not_found', __( 'Addon not found.', 'unstoppable-product-addons' ), array( 'status' => 404 ) );
        }

        $addon->settings = maybe_unserialize( $addon->settings );
        $addon->options = UPAD_Product_Addons::instance()->get_addon_options( $addon->id );

        return rest_ensure_response( $addon );
    }

    /**
     * Update addon
     */
    public function update_addon( $request ) {
        $data = $request->get_params();
        $result = UPAD_Product_Addons::instance()->update_addon( $request['id'], $data );

        return rest_ensure_response( array( 'success' => (bool) $result ) );
    }

    /**
     * Delete addon
     */
    public function delete_addon( $request ) {
        $result = UPAD_Product_Addons::instance()->delete_addon( $request['id'] );
        return rest_ensure_response( array( 'success' => (bool) $result ) );
    }
}
