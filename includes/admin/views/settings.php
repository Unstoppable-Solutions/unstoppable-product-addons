<?php
/**
 * Settings Page View
 *
 * @package WC_Custom_Addons
 * @since 1.5.0
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- View template variables are not true globals.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings = UPAD_Admin_Settings::instance();
$sections = $settings->get_sections();
$fields = $settings->get_fields();

// Remove license from settings sections (it's now its own tab)
$settings_sections = array_diff_key( $sections, array( 'license' => '' ) );
$current_section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'general';

// Handle settings save
if ( isset( $_POST['upad_save_settings'] ) ) {
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'upad_settings' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'unstoppable-product-addons' ) );
    }
    
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( esc_html__( 'You do not have permission to save settings.', 'unstoppable-product-addons' ) );
    }
    
    foreach ( $fields as $section_id => $section_fields ) {
        if ( $section_id === 'license' ) {
            continue; // Skip license fields
        }
        foreach ( $section_fields as $field ) {
            if ( $field['type'] === 'checkbox' ) {
                $value = isset( $_POST[ $field['id'] ] ) ? 'yes' : 'no';
            } else {
                $value = isset( $_POST[ $field['id'] ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field['id'] ] ) ) : '';
            }
            update_option( $field['id'], $value );
        }
    }
    echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Settings saved.', 'unstoppable-product-addons' ) . '</p></div>';
}
?>

<div class="upad-settings-content">
    <h2><?php esc_html_e( 'Plugin Settings', 'unstoppable-product-addons' ); ?></h2>

    <?php if ( count( $settings_sections ) > 1 ) : ?>
    <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <?php foreach ( $settings_sections as $key => $label ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=upad-settings&tab=settings&section=' . $key ) ); ?>" 
               class="nav-tab <?php echo $current_section === $key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <form method="post" action="" class="upad-settings-form">
        <?php wp_nonce_field( 'upad_settings' ); ?>
        
        <?php if ( isset( $fields[ $current_section ] ) && ! empty( $fields[ $current_section ] ) ) : ?>
            <table class="form-table">
                <?php foreach ( $fields[ $current_section ] as $field ) : ?>
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr( $field['id'] ); ?>">
                                <?php echo esc_html( $field['title'] ); ?>
                            </label>
                        </th>
                        <td>
                            <?php
                            $value = get_option( $field['id'], isset( $field['default'] ) ? $field['default'] : '' );
                            
                            switch ( $field['type'] ) :
                                case 'text':
                                    ?>
                                    <input type="text" 
                                           id="<?php echo esc_attr( $field['id'] ); ?>" 
                                           name="<?php echo esc_attr( $field['id'] ); ?>" 
                                           value="<?php echo esc_attr( $value ); ?>" 
                                           class="regular-text">
                                    <?php
                                    break;
                                
                                case 'number':
                                    ?>
                                    <input type="number" 
                                           id="<?php echo esc_attr( $field['id'] ); ?>" 
                                           name="<?php echo esc_attr( $field['id'] ); ?>" 
                                           value="<?php echo esc_attr( $value ); ?>" 
                                           class="small-text"
                                           <?php echo isset( $field['min'] ) ? 'min="' . esc_attr( $field['min'] ) . '"' : ''; ?>
                                           <?php echo isset( $field['max'] ) ? 'max="' . esc_attr( $field['max'] ) . '"' : ''; ?>>
                                    <?php
                                    break;
                                
                                case 'textarea':
                                    ?>
                                    <textarea id="<?php echo esc_attr( $field['id'] ); ?>" 
                                              name="<?php echo esc_attr( $field['id'] ); ?>" 
                                              class="large-text" 
                                              rows="5"><?php echo esc_textarea( $value ); ?></textarea>
                                    <?php
                                    break;
                                
                                case 'checkbox':
                                    ?>
                                    <label>
                                        <input type="checkbox" 
                                               id="<?php echo esc_attr( $field['id'] ); ?>" 
                                               name="<?php echo esc_attr( $field['id'] ); ?>" 
                                               value="yes" 
                                               <?php checked( $value, 'yes' ); ?>>
                                        <?php echo isset( $field['label'] ) ? esc_html( $field['label'] ) : ''; ?>
                                    </label>
                                    <?php
                                    break;
                                
                                case 'select':
                                    ?>
                                    <select id="<?php echo esc_attr( $field['id'] ); ?>" 
                                            name="<?php echo esc_attr( $field['id'] ); ?>">
                                        <?php if ( isset( $field['options'] ) ) : ?>
                                            <?php foreach ( $field['options'] as $opt_value => $opt_label ) : ?>
                                                <option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>>
                                                    <?php echo esc_html( $opt_label ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <?php
                                    break;
                            endswitch;
                            
                            if ( isset( $field['desc'] ) ) :
                                ?>
                                <p class="description"><?php echo esc_html( $field['desc'] ); ?></p>
                                <?php
                            endif;
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No settings available for this section.', 'unstoppable-product-addons' ); ?></p>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" name="upad_save_settings" class="button button-primary">
                <?php esc_html_e( 'Save Settings', 'unstoppable-product-addons' ); ?>
            </button>
        </p>
    </form>

    <hr style="margin: 30px 0;">

    <h3><?php esc_html_e( 'Plugin Information', 'unstoppable-product-addons' ); ?></h3>
    <table class="form-table">
        <tr>
            <th scope="row"><?php esc_html_e( 'Version', 'unstoppable-product-addons' ); ?></th>
            <td><code><?php echo esc_html( UPAD_VERSION ); ?></code></td>
        </tr>
    </table>
</div>
