<?php
/**
 * Admin Global Add-ons
 *
 * Handles global add-on group management in the admin area.
 *
 * @package WC_Options_Studio
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class UPAD_Admin_Global_Addons
 *
 * @since 1.0.0
 */
class UPAD_Admin_Global_Addons {

    /**
     * Single instance of the class.
     *
     * @var UPAD_Admin_Global_Addons
     */
    protected static $_instance = null;

    /**
     * Main Instance.
     *
     * @return UPAD_Admin_Global_Addons
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'wp_ajax_upad_save_group', array( $this, 'save_group' ) );
        add_action( 'wp_ajax_upad_delete_group', array( $this, 'delete_group' ) );
    }

    /**
     * Verify admin request.
     *
     * @return bool
     */
    private function verify_admin_request() {
        // Verify nonce
        if ( ! check_ajax_referer( 'upad_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Security check failed. Please refresh the page and try again.', 'unstoppable-product-addons' ) 
            ) );
            return false;
        }

        // Check capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'You do not have permission to perform this action.', 'unstoppable-product-addons' ) 
            ) );
            return false;
        }

        return true;
    }

    /**
     * Save group.
     *
     * @since 1.0.0
     */
    public function save_group() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_admin_request() above.
        $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
        
        // Sanitize and validate input
        $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        
        if ( empty( $name ) ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Group name is required.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        // Sanitize categories - ensure all are valid term IDs
        $categories = array();
        if ( isset( $_POST['categories'] ) && is_array( $_POST['categories'] ) ) {
            foreach ( $_POST['categories'] as $cat_id ) {
                $cat_id = absint( $cat_id );
                if ( $cat_id > 0 ) {
                    // Verify the term exists
                    $term = get_term( $cat_id, 'product_cat' );
                    if ( $term && ! is_wp_error( $term ) ) {
                        $categories[] = $cat_id;
                    }
                }
            }
        }

        $data = array(
            'name'                   => $name,
            'priority'               => isset( $_POST['priority'] ) ? absint( $_POST['priority'] ) : 10,
            'restrict_to_categories' => $categories,
            'enabled'                => isset( $_POST['enabled'] ) && $_POST['enabled'] ? 1 : 0,
        );

        if ( $group_id ) {
            // Verify group exists before updating
            $existing = UPAD_Global_Addons::instance()->get_group( $group_id );
            if ( ! $existing ) {
                wp_send_json_error( array( 
                    'message' => esc_html__( 'Group not found.', 'unstoppable-product-addons' ) 
                ) );
                return;
            }

            $result = UPAD_Global_Addons::instance()->update_group( $group_id, $data );
        } else {
            $result = UPAD_Global_Addons::instance()->create_group( $data );
            if ( ! is_wp_error( $result ) ) {
                $group_id = $result;
            }
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 
                'message' => esc_html( $result->get_error_message() ) 
            ) );
            return;
        }

        wp_send_json_success( array(
            'message'  => esc_html__( 'Group saved successfully.', 'unstoppable-product-addons' ),
            'group_id' => absint( $group_id ),
        ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    /**
     * Delete group.
     *
     * @since 1.0.0
     */
    public function delete_group() {
        if ( ! $this->verify_admin_request() ) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_admin_request() above.
        $group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;

        if ( ! $group_id ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Invalid group ID.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        // Verify group exists
        $existing = UPAD_Global_Addons::instance()->get_group( $group_id );
        if ( ! $existing ) {
            wp_send_json_error( array( 
                'message' => esc_html__( 'Group not found.', 'unstoppable-product-addons' ) 
            ) );
            return;
        }

        $result = UPAD_Global_Addons::instance()->delete_group( $group_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 
                'message' => esc_html( $result->get_error_message() ) 
            ) );
            return;
        }

        wp_send_json_success( array(
            'message' => esc_html__( 'Group deleted successfully.', 'unstoppable-product-addons' ),
        ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }
}
