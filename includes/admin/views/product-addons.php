<?php
/**
 * Product Add-ons Tab View
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_id = $post->ID;
$product = wc_get_product( $product_id );
$addons = UPAD_Product_Addons::instance()->get_addons( $product_id );
$addon_types = UPAD_Addon_Types::instance()->get_types();
$use_global = get_post_meta( $product_id, '_upad_use_global_addons', true );
$use_global = $use_global !== 'no';

// Get variations if variable product
$variations = array();
$is_variable = $product && $product->is_type( 'variable' );
if ( $is_variable ) {
    $variation_ids = $product->get_children();
    foreach ( $variation_ids as $variation_id ) {
        $variation = wc_get_product( $variation_id );
        if ( $variation ) {
            $variation_name = $variation->get_name();
            // Get formatted variation attributes
            $attributes = $variation->get_variation_attributes();
            $attr_names = array();
            foreach ( $attributes as $attr => $value ) {
                if ( $value ) {
                    $attr_names[] = $value;
                }
            }
            if ( ! empty( $attr_names ) ) {
                $variation_name = implode( ' / ', $attr_names );
            }
            $variations[ $variation_id ] = $variation_name;
        }
    }
}
?>

<div id="upad_addons_data" class="panel woocommerce_options_panel">
    <div class="upad-product-addons-wrapper">
        
        <div class="upad-global-toggle">
            <label>
                <input type="checkbox" name="_upad_use_global_addons" value="yes" <?php checked( $use_global ); ?>>
                <?php esc_html_e( 'Use Global Add-ons', 'wc-options-studio' ); ?>
            </label>
            <p class="description">
                <?php esc_html_e( 'When enabled, this product will inherit add-ons from matching global groups.', 'wc-options-studio' ); ?>
            </p>
        </div>

        <div class="upad-addons-header">
            <h4><?php esc_html_e( 'Product Add-ons', 'wc-options-studio' ); ?></h4>
            <div class="upad-addons-actions">
                <button type="button" class="button upad-import-btn"><?php esc_html_e( 'Import', 'wc-options-studio' ); ?></button>
                <button type="button" class="button upad-export-btn" <?php echo empty( $addons ) ? 'disabled' : ''; ?>><?php esc_html_e( 'Export', 'wc-options-studio' ); ?></button>
            </div>
        </div>

        <div class="upad-addons-list" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <?php if ( empty( $addons ) ) : ?>
                <div class="upad-no-addons">
                    <p><?php esc_html_e( 'No add-ons yet. Add your first add-on below.', 'wc-options-studio' ); ?></p>
                </div>
            <?php else : ?>
                <?php foreach ( $addons as $addon ) : ?>
                    <div class="upad-addon-item" data-addon-id="<?php echo esc_attr( $addon->id ); ?>">
                        <div class="upad-addon-header">
                            <span class="upad-addon-drag-handle dashicons dashicons-menu"></span>
                            <span class="upad-addon-title"><?php echo esc_html( $addon->title ?? '' ); ?></span>
                            <span class="upad-addon-type"><?php 
                                $type_key = $addon->addon_type ?? '';
                                $type_name = isset( $addon_types[ $type_key ]['name'] ) ? $addon_types[ $type_key ]['name'] : $type_key;
                                echo esc_html( $type_name ); 
                            ?></span>
                            <?php if ( ! empty( $addon->required ) ) : ?>
                                <span class="upad-addon-required-badge"><?php esc_html_e( 'Required', 'wc-options-studio' ); ?></span>
                            <?php endif; ?>
                            <div class="upad-addon-actions">
                                <button type="button" class="button-link upad-edit-addon"><?php esc_html_e( 'Edit', 'wc-options-studio' ); ?></button>
                                <button type="button" class="button-link upad-duplicate-addon"><?php esc_html_e( 'Duplicate', 'wc-options-studio' ); ?></button>
                                <button type="button" class="button-link upad-delete-addon"><?php esc_html_e( 'Delete', 'wc-options-studio' ); ?></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="upad-add-addon-section">
            <label><?php esc_html_e( 'Add new add-on:', 'wc-options-studio' ); ?></label>
            <select class="upad-addon-type-select">
                <option value=""><?php esc_html_e( 'Select type...', 'wc-options-studio' ); ?></option>
                <?php foreach ( $addon_types as $key => $type ) : ?>
                    <option value="<?php echo esc_attr( $key ); ?>">
                        <?php echo esc_html( $type['name'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="button upad-add-addon-btn" disabled>
                <?php esc_html_e( 'Add', 'wc-options-studio' ); ?>
            </button>
        </div>

    </div>
</div>

<!-- Addon Modal Template -->
<script type="text/template" id="tmpl-upad-addon-modal">
    <div class="upad-modal-overlay">
        <div class="upad-modal upad-addon-modal">
            <div class="upad-modal-header">
                <h2>{{ data.title }}</h2>
                <button type="button" class="upad-modal-close">&times;</button>
            </div>
            <div class="upad-modal-body">
                <form class="upad-addon-form">
                    <input type="hidden" name="addon_id" value="{{ data.addon_id }}">
                    <input type="hidden" name="group_id" value="{{ data.group_id }}">
                    <input type="hidden" name="product_id" value="{{ data.product_id }}">
                    
                    <div class="upad-form-row">
                        <label><?php esc_html_e( 'Add-on Type', 'wc-options-studio' ); ?></label>
                        <select name="addon_type" class="upad-addon-type-field widefat">
                            <?php foreach ( $addon_types as $key => $type ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>">
                                    <?php echo esc_html( $type['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Type-specific settings -->
                    <div class="upad-type-settings">
                        <?php foreach ( $addon_types as $key => $type ) : ?>
                            <?php if ( ! empty( $type['settings'] ) ) : ?>
                                <div class="upad-type-setting-group" data-type="<?php echo esc_attr( $key ); ?>" style="display:none;">
                                    <?php foreach ( $type['settings'] as $setting_key => $setting ) : ?>
                                        <div class="upad-form-row">
                                            <label><?php echo esc_html( $setting['label'] ); ?></label>
                                            <?php if ( $setting['type'] === 'select' ) : ?>
                                                <select name="settings[<?php echo esc_attr( $setting_key ); ?>]" class="widefat upad-type-setting" data-setting="<?php echo esc_attr( $setting_key ); ?>">
                                                    <?php foreach ( $setting['options'] as $opt_val => $opt_label ) : ?>
                                                        <option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $setting['default'], $opt_val ); ?>>
                                                            <?php echo esc_html( $opt_label ); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php elseif ( $setting['type'] === 'checkbox' ) : ?>
                                                <label>
                                                    <input type="checkbox" name="settings[<?php echo esc_attr( $setting_key ); ?>]" value="1" class="upad-type-setting" data-setting="<?php echo esc_attr( $setting_key ); ?>" <?php checked( $setting['default'], true ); ?>>
                                                    <?php if ( ! empty( $setting['desc'] ) ) echo esc_html( $setting['desc'] ); ?>
                                                </label>
                                            <?php elseif ( $setting['type'] === 'number' ) : ?>
                                                <input type="number" name="settings[<?php echo esc_attr( $setting_key ); ?>]" value="<?php echo esc_attr( $setting['default'] ); ?>" class="widefat upad-type-setting" data-setting="<?php echo esc_attr( $setting_key ); ?>" <?php if ( isset( $setting['min'] ) ) echo 'min="' . esc_attr( $setting['min'] ) . '"'; ?>>
                                                <?php if ( ! empty( $setting['desc'] ) ) : ?>
                                                    <p class="description"><?php echo esc_html( $setting['desc'] ); ?></p>
                                                <?php endif; ?>
                                            <?php elseif ( $setting['type'] === 'text' ) : ?>
                                                <input type="text" name="settings[<?php echo esc_attr( $setting_key ); ?>]" value="<?php echo esc_attr( $setting['default'] ); ?>" class="widefat upad-type-setting" data-setting="<?php echo esc_attr( $setting_key ); ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="upad-form-row">
                        <label><?php esc_html_e( 'Title', 'wc-options-studio' ); ?></label>
                        <input type="text" name="title" value="{{ data.addon_title }}" class="widefat" required>
                    </div>
                    
                    <div class="upad-form-row">
                        <label><?php esc_html_e( 'Title Format', 'wc-options-studio' ); ?></label>
                        <select name="title_format" class="widefat">
                            <option value="label"><?php esc_html_e( 'Label', 'wc-options-studio' ); ?></option>
                            <option value="heading"><?php esc_html_e( 'Heading', 'wc-options-studio' ); ?></option>
                            <option value="hide"><?php esc_html_e( 'Hide', 'wc-options-studio' ); ?></option>
                        </select>
                    </div>
                    
                    <div class="upad-form-row">
                        <label><?php esc_html_e( 'Description', 'wc-options-studio' ); ?></label>
                        <textarea name="description" class="widefat" rows="3">{{ data.description }}</textarea>
                    </div>
                    
                    <div class="upad-form-row upad-checkboxes">
                        <label>
                            <input type="checkbox" name="required" value="1" <# if (data.required) { #>checked<# } #>>
                            <?php esc_html_e( 'Required', 'wc-options-studio' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <# if (data.enabled) { #>checked<# } #>>
                            <?php esc_html_e( 'Enabled', 'wc-options-studio' ); ?>
                        </label>
                    </div>
                    
                    <?php if ( $is_variable && ! empty( $variations ) ) : ?>
                    <div class="upad-form-row upad-variations-row">
                        <label><?php esc_html_e( 'Apply to Variations', 'wc-options-studio' ); ?></label>
                        <select name="apply_to_variations[]" class="upad-variation-select widefat" multiple="multiple" style="height: auto; min-height: 100px;">
                            <?php foreach ( $variations as $var_id => $var_name ) : ?>
                                <option value="<?php echo esc_attr( $var_id ); ?>">
                                    <?php echo esc_html( $var_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Leave empty to show this add-on for all variations. Select specific variations to show this add-on only when those variations are selected.', 'wc-options-studio' ); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="upad-options-section">
                        <h4><?php esc_html_e( 'Options', 'wc-options-studio' ); ?></h4>
                        <div class="upad-options-list">
                            <!-- Options will be added here -->
                        </div>
                        <button type="button" class="button upad-add-option-btn">
                            <?php esc_html_e( 'Add Option', 'wc-options-studio' ); ?>
                        </button>
                    </div>
                </form>
            </div>
            <div class="upad-modal-footer">
                <button type="button" class="button upad-modal-cancel"><?php esc_html_e( 'Cancel', 'wc-options-studio' ); ?></button>
                <button type="button" class="button button-primary upad-save-addon"><?php esc_html_e( 'Save Add-on', 'wc-options-studio' ); ?></button>
            </div>
        </div>
    </div>
</script>
