<?php
/**
 * Secure File Upload Handler
 * Addresses security concerns from competitor reviews:
 * - File type/extension restrictions
 * - File size limits
 * - MIME type validation
 * - Secure storage with randomized paths
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UPAD_File_Upload {

    protected static $_instance = null;

    /**
     * Default allowed MIME types mapped to extensions
     */
    private $mime_types = array(
        // Images
        'jpg'  => array( 'image/jpeg' ),
        'jpeg' => array( 'image/jpeg' ),
        'png'  => array( 'image/png' ),
        'gif'  => array( 'image/gif' ),
        'webp' => array( 'image/webp' ),
        'svg'  => array( 'image/svg+xml' ),
        'bmp'  => array( 'image/bmp' ),
        'tiff' => array( 'image/tiff' ),
        'tif'  => array( 'image/tiff' ),
        
        // Documents
        'pdf'  => array( 'application/pdf' ),
        'doc'  => array( 'application/msword' ),
        'docx' => array( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ),
        'xls'  => array( 'application/vnd.ms-excel' ),
        'xlsx' => array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ),
        'ppt'  => array( 'application/vnd.ms-powerpoint' ),
        'pptx' => array( 'application/vnd.openxmlformats-officedocument.presentationml.presentation' ),
        'txt'  => array( 'text/plain' ),
        'rtf'  => array( 'application/rtf', 'text/rtf' ),
        'odt'  => array( 'application/vnd.oasis.opendocument.text' ),
        'ods'  => array( 'application/vnd.oasis.opendocument.spreadsheet' ),
        
        // Design files
        'ai'   => array( 'application/postscript', 'application/illustrator' ),
        'eps'  => array( 'application/postscript' ),
        'psd'  => array( 'image/vnd.adobe.photoshop', 'application/x-photoshop' ),
        
        // Archives
        'zip'  => array( 'application/zip', 'application/x-zip-compressed' ),
        'rar'  => array( 'application/x-rar-compressed', 'application/vnd.rar' ),
        
        // Audio/Video
        'mp3'  => array( 'audio/mpeg' ),
        'mp4'  => array( 'video/mp4' ),
        'wav'  => array( 'audio/wav', 'audio/x-wav' ),
        'avi'  => array( 'video/x-msvideo' ),
        'mov'  => array( 'video/quicktime' ),
    );

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function __construct() {
        add_action( 'wp_ajax_upad_upload_file', array( $this, 'handle_upload' ) );
        add_action( 'wp_ajax_nopriv_upad_upload_file', array( $this, 'handle_upload' ) );
        add_action( 'wp_ajax_upad_remove_file', array( $this, 'handle_remove' ) );
        add_action( 'wp_ajax_nopriv_upad_remove_file', array( $this, 'handle_remove' ) );
        add_action( 'wp_ajax_upad_download_file', array( $this, 'handle_download' ) );
        add_action( 'upad_cleanup_temp_uploads', array( $this, 'cleanup_temp_uploads' ) );
        
        // Schedule cleanup if not scheduled
        if ( ! wp_next_scheduled( 'upad_cleanup_temp_uploads' ) ) {
            wp_schedule_event( time(), 'daily', 'upad_cleanup_temp_uploads' );
        }
    }

    /**
     * Handle file upload via AJAX
     */
    public function handle_upload() {
        // Verify nonce
        if ( ! check_ajax_referer( 'upad_upload_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed. Please refresh the page and try again.', 'unstoppable-product-addons' ),
            ) );
        }

        // Get addon settings
        $addon_id = isset( $_POST['addon_id'] ) ? absint( $_POST['addon_id'] ) : 0;
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $addon_id || ! $product_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid request.', 'unstoppable-product-addons' ),
            ) );
        }

        // Get addon configuration
        $addon = $this->get_addon( $addon_id );
        if ( ! $addon ) {
            wp_send_json_error( array(
                'message' => __( 'Add-on not found.', 'unstoppable-product-addons' ),
            ) );
        }

        // Check if file was uploaded
        if ( empty( $_FILES['file'] ) ) {
            wp_send_json_error( array(
                'message' => __( 'No file was uploaded.', 'unstoppable-product-addons' ),
            ) );
        }

        $file = $_FILES['file'];

        // Check for upload errors
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            wp_send_json_error( array(
                'message' => $this->get_upload_error_message( $file['error'] ),
            ) );
        }

        // Get allowed extensions from addon settings or global
        $settings = ! empty( $addon->settings ) ? maybe_unserialize( $addon->settings ) : array();
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }
        $allowed_extensions = ! empty( $settings['allowed_extensions'] ) 
            ? array_map( 'trim', explode( ',', strtolower( $settings['allowed_extensions'] ) ) )
            : $this->get_global_allowed_extensions();

        // Get max file size from addon settings or global
        $max_file_size = ! empty( $settings['max_file_size'] ) 
            ? floatval( $settings['max_file_size'] ) * 1024 * 1024 // Convert MB to bytes
            : $this->get_global_max_file_size() * 1024 * 1024;

        // Validate file extension.
        $file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $file_ext, $allowed_extensions, true ) ) {
            wp_send_json_error( array(
                /* translators: %s: list of allowed file extensions */
                'message' => sprintf(
                    __( 'File type not allowed. Allowed types: %s', 'unstoppable-product-addons' ),
                    implode( ', ', $allowed_extensions )
                ),
            ) );
        }

        // Validate file size.
        if ( $file['size'] > $max_file_size ) {
            wp_send_json_error( array(
                /* translators: %s: maximum file size in megabytes */
                'message' => sprintf(
                    __( 'File is too large. Maximum size: %s MB', 'unstoppable-product-addons' ),
                    number_format( $max_file_size / 1024 / 1024, 1 )
                ),
            ) );
        }

        // Validate MIME type
        $mime_validation = $this->validate_mime_type( $file['tmp_name'], $file_ext );
        if ( is_wp_error( $mime_validation ) ) {
            wp_send_json_error( array(
                'message' => $mime_validation->get_error_message(),
            ) );
        }

        // Validate image dimensions if applicable
        if ( in_array( $file_ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp' ), true ) ) {
            $dimension_validation = $this->validate_image_dimensions( $file['tmp_name'], $settings );
            if ( is_wp_error( $dimension_validation ) ) {
                wp_send_json_error( array(
                    'message' => $dimension_validation->get_error_message(),
                ) );
            }
        }

        // Generate secure file path
        $upload_dir = $this->get_upload_directory();
        $session_key = $this->get_session_key();
        $unique_folder = $upload_dir . '/' . $session_key;

        // Create session folder if doesn't exist
        if ( ! file_exists( $unique_folder ) ) {
            wp_mkdir_p( $unique_folder );
            
            // Add index.php
            file_put_contents( $unique_folder . '/index.php', '<?php // Silence is golden' );
        }

        // Generate unique filename
        $safe_filename = $this->generate_safe_filename( $file['name'] );
        $file_path = $unique_folder . '/' . $safe_filename;

        // Move the uploaded file using WordPress's helper so hosts that remap
        // move_uploaded_file() (or run under PHP-FPM / open_basedir restrictions)
        // still work. WP's wrapper performs the is_uploaded_file() check for us.
        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $overrides = array(
            'test_form'                => false,
            'test_type'                => false, // We've already validated MIME above.
            'unique_filename_callback' => function () use ( $safe_filename ) {
                return $safe_filename;
            },
            'upload_error_handler'     => function ( $file_arr, $message ) {
                return array( 'error' => $message );
            },
        );

        // Point WP's upload helper at our chosen folder for this request only.
        $target_dir     = $unique_folder;
        $target_url_dir = str_replace( wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $target_dir );
        $upload_filter  = function ( $dirs ) use ( $target_dir, $target_url_dir ) {
            $dirs['path']   = $target_dir;
            $dirs['url']    = $target_url_dir;
            $dirs['subdir'] = '';
            return $dirs;
        };
        add_filter( 'upload_dir', $upload_filter );
        $moved = wp_handle_upload( $file, $overrides );
        remove_filter( 'upload_dir', $upload_filter );

        if ( empty( $moved['file'] ) || ! empty( $moved['error'] ) ) {
            wp_send_json_error(
                array(
                    'message' => isset( $moved['error'] ) ? $moved['error'] : __( 'Failed to save uploaded file.', 'unstoppable-product-addons' ),
                )
            );
        }
        $file_path = $moved['file'];

        // Generate upload hash for tracking
        $upload_hash = wp_generate_password( 32, false );

        // Save upload record to database
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'upad_uploads',
            array(
                'product_id'  => $product_id,
                'addon_id'    => $addon_id,
                'file_name'   => $safe_filename,
                'file_path'   => $file_path,
                'file_url'    => '', // Will be set when order is placed
                'file_type'   => $file_ext,
                'file_size'   => $file['size'],
                'upload_hash' => $upload_hash,
                'session_key' => $session_key,
                'user_id'     => get_current_user_id() ?: null,
                'status'      => 'pending',
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
        );

        $upload_id = $wpdb->insert_id;

        wp_send_json_success( array(
            'message'     => __( 'File uploaded successfully.', 'unstoppable-product-addons' ),
            'upload_id'   => $upload_id,
            'upload_hash' => $upload_hash,
            'file_name'   => $safe_filename,
            'file_size'   => size_format( $file['size'] ),
            'file_type'   => $file_ext,
        ) );
    }

    /**
     * Handle file removal via AJAX
     */
    public function handle_remove() {
        if ( ! check_ajax_referer( 'upad_upload_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'unstoppable-product-addons' ),
            ) );
        }

        $upload_hash = isset( $_POST['upload_hash'] ) ? sanitize_text_field( $_POST['upload_hash'] ) : '';
        $session_key = $this->get_session_key();

        if ( empty( $upload_hash ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid request.', 'unstoppable-product-addons' ),
            ) );
        }

        global $wpdb;

        // Get upload record
        $upload = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_uploads WHERE upload_hash = %s AND session_key = %s AND status = 'pending'",
            $upload_hash,
            $session_key
        ) );

        if ( ! $upload ) {
            wp_send_json_error( array(
                'message' => __( 'File not found.', 'unstoppable-product-addons' ),
            ) );
        }

        // Delete physical file.
        if ( file_exists( $upload->file_path ) ) {
            wp_delete_file( $upload->file_path );
        }

        // Delete database record.
        $wpdb->delete(
            $wpdb->prefix . 'upad_uploads',
            array( 'id' => $upload->id ),
            array( '%d' )
        );

        wp_send_json_success( array(
            'message' => __( 'File removed successfully.', 'unstoppable-product-addons' ),
        ) );
    }

    /**
     * Handle secure file download
     */
    public function handle_download() {
        $upload_hash = isset( $_GET['hash'] ) ? sanitize_text_field( $_GET['hash'] ) : '';
        $nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( $_GET['nonce'] ) : '';

        if ( ! wp_verify_nonce( $nonce, 'upad_download_' . $upload_hash ) ) {
            wp_die( __( 'Invalid download link.', 'unstoppable-product-addons' ) );
        }

        global $wpdb;

        $upload = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_uploads WHERE upload_hash = %s",
            $upload_hash
        ) );

        if ( ! $upload || ! file_exists( $upload->file_path ) ) {
            wp_die( __( 'File not found.', 'unstoppable-product-addons' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            // Check if user owns the order
            if ( $upload->order_id ) {
                $order = wc_get_order( $upload->order_id );
                if ( $order && $order->get_customer_id() !== get_current_user_id() ) {
                    wp_die( __( 'You do not have permission to download this file.', 'unstoppable-product-addons' ) );
                }
            }
        }

        // Serve file.
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . $upload->file_name . '"' );
        header( 'Content-Length: ' . filesize( $upload->file_path ) );
        header( 'Cache-Control: no-cache, must-revalidate' );
        header( 'Pragma: no-cache' );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming download; WP_Filesystem is not applicable.
        readfile( $upload->file_path );
        exit;
    }

    /**
     * Validate MIME type matches extension
     */
    private function validate_mime_type( $file_path, $extension ) {
        // Get expected MIME types for this extension
        if ( ! isset( $this->mime_types[ $extension ] ) ) {
            return new WP_Error( 'invalid_mime', __( 'File type not recognized.', 'unstoppable-product-addons' ) );
        }

        $expected_mimes = $this->mime_types[ $extension ];

        // Get actual MIME type
        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $actual_mime = finfo_file( $finfo, $file_path );
        finfo_close( $finfo );

        // Check if actual MIME matches any expected MIME
        if ( ! in_array( $actual_mime, $expected_mimes, true ) ) {
            // Additional check using WordPress function
            $wp_check = wp_check_filetype_and_ext( $file_path, basename( $file_path ) );
            if ( empty( $wp_check['type'] ) || ! in_array( $wp_check['type'], $expected_mimes, true ) ) {
                return new WP_Error( 
                    'mime_mismatch', 
                    __( 'File content does not match file extension. This may indicate a potentially dangerous file.', 'unstoppable-product-addons' )
                );
            }
        }

        return true;
    }

    /**
     * Validate image dimensions
     */
    private function validate_image_dimensions( $file_path, $settings ) {
        $image_size = getimagesize( $file_path );
        
        if ( ! $image_size ) {
            return new WP_Error( 'invalid_image', __( 'Could not read image dimensions.', 'unstoppable-product-addons' ) );
        }

        $width = $image_size[0];
        $height = $image_size[1];

        // Check minimum dimensions
        if ( ! empty( $settings['min_dimensions'] ) ) {
            $min = explode( 'x', strtolower( $settings['min_dimensions'] ) );
            if ( count( $min ) === 2 ) {
                $min_width = absint( $min[0] );
                $min_height = absint( $min[1] );

                if ( $width < $min_width || $height < $min_height ) {
                    return new WP_Error(
                        'image_too_small',
                        sprintf(
                            /* translators: %1$d: min width, %2$d: min height, %3$d: actual width, %4$d: actual height */
                            __( 'Image is too small. Minimum dimensions: %1$dx%2$d pixels. Your image: %3$dx%4$d pixels.', 'unstoppable-product-addons' ),
                            $min_width,
                            $min_height,
                            $width,
                            $height
                        )
                    );
                }
            }
        }

        // Check maximum dimensions.
        if ( ! empty( $settings['max_dimensions'] ) ) {
            $max = explode( 'x', strtolower( $settings['max_dimensions'] ) );
            if ( count( $max ) === 2 ) {
                $max_width = absint( $max[0] );
                $max_height = absint( $max[1] );

                if ( $width > $max_width || $height > $max_height ) {
                    return new WP_Error(
                        'image_too_large',
                        sprintf(
                            /* translators: %1$d: max width, %2$d: max height, %3$d: actual width, %4$d: actual height */
                            __( 'Image is too large. Maximum dimensions: %1$dx%2$d pixels. Your image: %3$dx%4$d pixels.', 'unstoppable-product-addons' ),
                            $max_width,
                            $max_height,
                            $width,
                            $height
                        )
                    );
                }
            }
        }

        return true;
    }

    /**
     * Generate a safe filename
     */
    private function generate_safe_filename( $filename ) {
        // Get extension
        $ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
        
        // Sanitize base name
        $base = sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) );
        
        // Limit length
        $base = substr( $base, 0, 50 );
        
        // Add unique identifier
        $unique = wp_generate_password( 8, false );
        
        return $base . '-' . $unique . '.' . $ext;
    }

    /**
     * Get upload directory
     */
    private function get_upload_directory() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/upad-uploads';
    }

    /**
     * Get or create session key for anonymous uploads
     */
    private function get_session_key() {
        if ( is_user_logged_in() ) {
            return 'user_' . get_current_user_id();
        }

        if ( ! WC()->session ) {
            WC()->session = new WC_Session_Handler();
            WC()->session->init();
        }

        $session_key = WC()->session->get( 'upad_upload_session' );
        
        if ( ! $session_key ) {
            $session_key = 'guest_' . wp_generate_password( 16, false );
            WC()->session->set( 'upad_upload_session', $session_key );
        }

        return $session_key;
    }

    /**
     * Get addon from database
     */
    private function get_addon( $addon_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_addons WHERE id = %d",
            $addon_id
        ) );
    }

    /**
     * Get global allowed extensions
     */
    private function get_global_allowed_extensions() {
        $extensions = get_option( 'upad_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx' );
        if ( empty( $extensions ) || ! is_string( $extensions ) ) {
            $extensions = 'jpg,jpeg,png,gif,pdf,doc,docx';
        }
        return array_map( 'trim', explode( ',', strtolower( $extensions ) ) );
    }

    /**
     * Get global max file size in MB
     */
    private function get_global_max_file_size() {
        $size = get_option( 'upad_max_file_size', 5 );
        return ! empty( $size ) ? floatval( $size ) : 5;
    }

    /**
     * Get upload error message
     */
    private function get_upload_error_message( $error_code ) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the server limit.', 'unstoppable-product-addons' ),
            UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the maximum allowed size.', 'unstoppable-product-addons' ),
            UPLOAD_ERR_PARTIAL    => __( 'The file was only partially uploaded. Please try again.', 'unstoppable-product-addons' ),
            UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'unstoppable-product-addons' ),
            UPLOAD_ERR_NO_TMP_DIR => __( 'Server configuration error. Please contact support.', 'unstoppable-product-addons' ),
            UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk. Please contact support.', 'unstoppable-product-addons' ),
            UPLOAD_ERR_EXTENSION  => __( 'File upload stopped by a server extension.', 'unstoppable-product-addons' ),
        );

        return isset( $messages[ $error_code ] ) 
            ? $messages[ $error_code ] 
            : __( 'An unknown error occurred during upload.', 'unstoppable-product-addons' );
    }

    /**
     * Cleanup old temporary uploads
     */
    public function cleanup_temp_uploads() {
        global $wpdb;

        // Delete pending uploads older than 24 hours.
        $cutoff = gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );

        $old_uploads = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_uploads WHERE status = 'pending' AND created_at < %s",
            $cutoff
        ) );

        foreach ( $old_uploads as $upload ) {
            // Delete physical file.
            if ( file_exists( $upload->file_path ) ) {
                wp_delete_file( $upload->file_path );
            }

            // Delete database record.
            $wpdb->delete(
                $wpdb->prefix . 'upad_uploads',
                array( 'id' => $upload->id ),
                array( '%d' )
            );
        }

        // Clean up empty session directories.
        $upload_dir = $this->get_upload_directory();
        if ( is_dir( $upload_dir ) ) {
            $dirs = glob( $upload_dir . '/*', GLOB_ONLYDIR );
            foreach ( $dirs as $dir ) {
                $files = glob( $dir . '/*' );
                // Only index.php remains.
                if ( count( $files ) <= 1 ) {
                    // Check if directory is older than 24 hours.
                    if ( filemtime( $dir ) < strtotime( '-24 hours' ) ) {
                        array_map( 'wp_delete_file', $files );
                        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing temp upload directory; WP_Filesystem requires credentials.
                        rmdir( $dir );
                    }
                }
            }
        }
    }

    /**
     * Associate pending uploads with an order
     */
    public function associate_uploads_with_order( $order_id, $order_item_id, $addon_id, $upload_hash ) {
        global $wpdb;

        $session_key = $this->get_session_key();

        return $wpdb->update(
            $wpdb->prefix . 'upad_uploads',
            array(
                'order_id'      => $order_id,
                'order_item_id' => $order_item_id,
                'status'        => 'completed',
            ),
            array(
                'upload_hash' => $upload_hash,
                'session_key' => $session_key,
                'addon_id'    => $addon_id,
            ),
            array( '%d', '%d', '%s' ),
            array( '%s', '%s', '%d' )
        );
    }

    /**
     * Get download URL for a file
     */
    public function get_download_url( $upload_hash ) {
        $nonce = wp_create_nonce( 'upad_download_' . $upload_hash );
        return add_query_arg( array(
            'action' => 'upad_download_file',
            'hash'   => $upload_hash,
            'nonce'  => $nonce,
        ), admin_url( 'admin-ajax.php' ) );
    }

    /**
     * Get uploads for an order item
     */
    public function get_order_item_uploads( $order_item_id ) {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}upad_uploads WHERE order_item_id = %d ORDER BY created_at ASC",
            $order_item_id
        ) );
    }
}
