<?php
/**
 * Global Add-ons Page View
 *
 * @package WC_Custom_Addons
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$groups = UPAD_Global_Addons::instance()->get_groups();
$addon_types = UPAD_Addon_Types::instance()->get_types();
?>

<div class="upad-global-addons-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;"><?php esc_html_e( 'Global Add-ons', 'unstoppable-product-addons' ); ?></h2>
        <a href="#" class="button button-primary upad-add-group-btn">
            <?php esc_html_e( 'Add New Group', 'unstoppable-product-addons' ); ?>
        </a>
    </div>

    <p class="description">
        <?php esc_html_e( 'Global add-ons are applied to products based on category. Create groups to organize your add-ons.', 'unstoppable-product-addons' ); ?>
    </p>

    <div class="upad-groups-container">
        <?php if ( empty( $groups ) ) : ?>
            <div class="upad-no-groups">
                <p><?php esc_html_e( 'No global add-on groups yet. Create your first group to get started!', 'unstoppable-product-addons' ); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ( $groups as $group ) : ?>
                <?php
                // Get category names for display
                $category_names = array();
                $restricted_cats = maybe_unserialize( $group->restrict_to_categories ?? '' );
                if ( ! empty( $restricted_cats ) && is_array( $restricted_cats ) ) {
                    foreach ( $restricted_cats as $cat_id ) {
                        $term = get_term( $cat_id, 'product_cat' );
                        if ( $term && ! is_wp_error( $term ) ) {
                            $category_names[] = $term->name;
                        }
                    }
                }
                ?>
                <div class="upad-group-card" data-group-id="<?php echo esc_attr( $group->id ); ?>">
                    <div class="upad-group-header">
                        <div class="upad-group-title-section">
                            <h3><?php echo esc_html( $group->name ?? '' ); ?></h3>
                            <span class="upad-group-categories">
                                <?php if ( empty( $category_names ) ) : ?>
                                    <em><?php esc_html_e( 'Applies to: All Products', 'unstoppable-product-addons' ); ?></em>
                                <?php else : ?>
                                    <?php /* translators: %s: list of category names */ ?>
                                    <em><?php echo esc_html( sprintf( __( 'Applies to: %s', 'unstoppable-product-addons' ), implode( ', ', $category_names ) ) ); ?></em>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="upad-group-actions">
                            <span class="upad-priority">
                                <?php /* translators: %d: priority number */ ?>
                                <?php echo esc_html( sprintf( __( 'Priority: %d', 'unstoppable-product-addons' ), $group->priority ?? 10 ) ); ?>
                            </span>
                            <span class="upad-status <?php echo ! empty( $group->enabled ) ? 'active' : 'inactive'; ?>">
                                <?php echo ! empty( $group->enabled ) ? esc_html__( 'Active', 'unstoppable-product-addons' ) : esc_html__( 'Inactive', 'unstoppable-product-addons' ); ?>
                            </span>
                            <button type="button" class="button upad-edit-group"><?php esc_html_e( 'Edit', 'unstoppable-product-addons' ); ?></button>
                            <button type="button" class="button upad-delete-group"><?php esc_html_e( 'Delete', 'unstoppable-product-addons' ); ?></button>
                        </div>
                    </div>
                    
                    <div class="upad-group-addons">
                        <?php
                        $addons = UPAD_Global_Addons::instance()->get_group_addons( $group->id );
                        if ( empty( $addons ) ) :
                        ?>
                            <p class="upad-no-addons"><?php esc_html_e( 'No add-ons in this group.', 'unstoppable-product-addons' ); ?></p>
                        <?php else : ?>
                            <table class="upad-addons-table">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Add-on', 'unstoppable-product-addons' ); ?></th>
                                        <th><?php esc_html_e( 'Type', 'unstoppable-product-addons' ); ?></th>
                                        <th><?php esc_html_e( 'Required', 'unstoppable-product-addons' ); ?></th>
                                        <th><?php esc_html_e( 'Actions', 'unstoppable-product-addons' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $addons as $addon ) : ?>
                                        <tr data-addon-id="<?php echo esc_attr( $addon->id ); ?>">
                                            <td><?php echo esc_html( $addon->title ?? '' ); ?></td>
                                            <td><?php 
                                                $type_key = $addon->addon_type ?? '';
                                                $type_name = isset( $addon_types[ $type_key ]['name'] ) ? $addon_types[ $type_key ]['name'] : $type_key;
                                                echo esc_html( $type_name ); 
                                            ?></td>
                                            <td><?php echo ! empty( $addon->required ) ? '&#10003;' : '&mdash;'; ?></td>
                                            <td>
                                                <button type="button" class="button-link upad-edit-addon"><?php esc_html_e( 'Edit', 'unstoppable-product-addons' ); ?></button>
                                                <button type="button" class="button-link upad-delete-addon"><?php esc_html_e( 'Delete', 'unstoppable-product-addons' ); ?></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        
                        <button type="button" class="button upad-add-addon" data-group-id="<?php echo esc_attr( $group->id ); ?>"><?php esc_html_e( 'Add New Add-on', 'unstoppable-product-addons' ); ?></button>
                        </button>
                    </div>

                    <div class="upad-group-footer">
                        <div class="upad-import-export">
                            <button type="button" class="button upad-export-group"><?php esc_html_e( 'Export', 'unstoppable-product-addons' ); ?></button>
                            <button type="button" class="button upad-import-group"><?php esc_html_e( 'Import', 'unstoppable-product-addons' ); ?></button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Group Modal Template -->
<script type="text/template" id="tmpl-upad-group-modal">
    <div class="upad-modal-overlay">
        <div class="upad-modal">
            <div class="upad-modal-header">
                <h2>{{ data.title }}</h2>
                <button type="button" class="upad-modal-close">&times;</button>
            </div>
            <div class="upad-modal-body">
                <form class="upad-group-form">
                    <input type="hidden" name="group_id" value="{{ data.group_id }}">
                    
                    <p>
                        <label><?php esc_html_e( 'Group Name', 'unstoppable-product-addons' ); ?></label>
                        <input type="text" name="name" value="{{ data.name }}" class="widefat" required>
                    </p>
                    
                    <p>
                        <label><?php esc_html_e( 'Priority', 'unstoppable-product-addons' ); ?></label>
                        <input type="number" name="priority" value="{{ data.priority }}" class="small-text">
                        <span class="description"><?php esc_html_e( 'Lower numbers display first.', 'unstoppable-product-addons' ); ?></span>
                    </p>
                    
                    <p>
                        <label><?php esc_html_e( 'Restrict to Categories', 'unstoppable-product-addons' ); ?></label>
                        <select name="categories[]" class="upad-category-select" multiple>
                            <?php
                            $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
                            foreach ( $categories as $cat ) :
                            ?>
                                <option value="<?php echo esc_attr( $cat->term_id ); ?>">
                                    <?php echo esc_html( $cat->name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span class="description"><?php esc_html_e( 'Leave empty to apply to all products.', 'unstoppable-product-addons' ); ?></span>
                    </p>
                    
                    <p>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <# if (data.enabled) { #>checked<# } #>>
                            <?php esc_html_e( 'Enabled', 'unstoppable-product-addons' ); ?>
                        </label>
                    </p>
                </form>
            </div>
            <div class="upad-modal-footer">
                <button type="button" class="button upad-modal-cancel"><?php esc_html_e( 'Cancel', 'unstoppable-product-addons' ); ?></button>
                <button type="button" class="button button-primary upad-save-group"><?php esc_html_e( 'Save Group', 'unstoppable-product-addons' ); ?></button>
            </div>
        </div>
    </div>
</script>

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
                        <label><?php esc_html_e( 'Add-on Type', 'unstoppable-product-addons' ); ?></label>
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
                        <label><?php esc_html_e( 'Title', 'unstoppable-product-addons' ); ?></label>
                        <input type="text" name="title" value="{{ data.addon_title }}" class="widefat" required>
                    </div>
                    
                    <div class="upad-form-row">
                        <label><?php esc_html_e( 'Title Format', 'unstoppable-product-addons' ); ?></label>
                        <select name="title_format" class="widefat">
                            <option value="label"><?php esc_html_e( 'Label', 'unstoppable-product-addons' ); ?></option>
                            <option value="heading"><?php esc_html_e( 'Heading', 'unstoppable-product-addons' ); ?></option>
                            <option value="hide"><?php esc_html_e( 'Hide', 'unstoppable-product-addons' ); ?></option>
                        </select>
                    </div>
                    
                    <div class="upad-form-row">
                        <label><?php esc_html_e( 'Description', 'unstoppable-product-addons' ); ?></label>
                        <textarea name="description" class="widefat" rows="3">{{ data.description }}</textarea>
                    </div>
                    
                    <div class="upad-form-row upad-checkboxes">
                        <label>
                            <input type="checkbox" name="required" value="1" <# if (data.required) { #>checked<# } #>>
                            <?php esc_html_e( 'Required', 'unstoppable-product-addons' ); ?>
                        </label>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <# if (data.enabled) { #>checked<# } #>>
                            <?php esc_html_e( 'Enabled', 'unstoppable-product-addons' ); ?>
                        </label>
                    </div>
                    
                    <div class="upad-options-section">
                        <h4><?php esc_html_e( 'Options', 'unstoppable-product-addons' ); ?></h4>
                        <div class="upad-options-list">
                            <!-- Options will be added here -->
                        </div>
                        <button type="button" class="button upad-add-option-btn">
                            <?php esc_html_e( 'Add Option', 'unstoppable-product-addons' ); ?>
                        </button>
                    </div>
                </form>
            </div>
            <div class="upad-modal-footer">
                <button type="button" class="button upad-modal-cancel"><?php esc_html_e( 'Cancel', 'unstoppable-product-addons' ); ?></button>
                <button type="button" class="button button-primary upad-save-addon"><?php esc_html_e( 'Save Add-on', 'unstoppable-product-addons' ); ?></button>
            </div>
        </div>
    </div>
</script>
