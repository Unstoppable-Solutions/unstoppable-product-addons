/**
 * WooCommerce Options Studio - Admin JS
 */
(function($) {
    'use strict';

    var WCOS_Admin = {
        init: function() {
            this.bindEvents();
            this.initSortable();
            this.initConditionalLogic();
        },

        bindEvents: function() {
            var self = this;

            // Add group button
            $(document).on('click', '.wcos-add-group-btn', function(e) {
                e.preventDefault();
                self.openGroupModal();
            });

            // Edit group
            $(document).on('click', '.wcos-edit-group', function() {
                var card = $(this).closest('.wcos-group-card');
                var groupId = card.data('group-id');
                self.openGroupModal(groupId);
            });

            // Delete group
            $(document).on('click', '.wcos-delete-group', function() {
                if (!confirm(wcos_admin.i18n.confirm_delete_group)) return;
                
                var card = $(this).closest('.wcos-group-card');
                var groupId = card.data('group-id');

                $.post(wcos_admin.ajax_url, {
                    action: 'wcos_delete_group',
                    nonce: wcos_admin.nonce,
                    group_id: groupId
                }, function() {
                    card.fadeOut(function() { $(this).remove(); });
                });
            });

            // Add new addon button (in global groups)
            $(document).on('click', '.wcos-add-addon', function(e) {
                e.preventDefault();
                var groupId = $(this).data('group-id');
                self.openAddonModal(null, groupId, null);
            });

            // Edit addon
            $(document).on('click', '.wcos-edit-addon', function(e) {
                e.preventDefault();
                var row = $(this).closest('tr, .wcos-addon-item');
                var addonId = row.data('addon-id');
                var groupId = $(this).closest('.wcos-group-card').data('group-id') || null;
                var productId = $('.wcos-addons-list').data('product-id') || null;
                self.openAddonModal(addonId, groupId, productId);
            });

            // Delete addon
            $(document).on('click', '.wcos-delete-addon', function(e) {
                e.preventDefault();
                if (!confirm(wcos_admin.i18n.confirm_delete)) return;
                
                var row = $(this).closest('tr, .wcos-addon-item');
                var addonId = row.data('addon-id');

                $.post(wcos_admin.ajax_url, {
                    action: 'wcos_delete_addon',
                    nonce: wcos_admin.nonce,
                    addon_id: addonId
                }, function() {
                    row.fadeOut(function() { $(this).remove(); });
                });
            });

            // Duplicate addon
            $(document).on('click', '.wcos-duplicate-addon', function(e) {
                e.preventDefault();
                
                var $row = $(this).closest('tr, .wcos-addon-item');
                var addonId = $row.data('addon-id');
                var $btn = $(this);
                var originalText = $btn.text();
                
                $btn.text(wcos_admin.i18n.saving || 'Duplicating...');

                $.post(wcos_admin.ajax_url, {
                    action: 'wcos_duplicate_addon',
                    nonce: wcos_admin.nonce,
                    addon_id: addonId
                }, function(response) {
                    if (response.success && response.data.addon) {
                        var addon = response.data.addon;
                        var $newRow = $row.clone();
                        
                        // Update the new row with duplicated addon data
                        $newRow.attr('data-addon-id', addon.id);
                        $newRow.find('td:first, .wcos-addon-title').first().text(addon.title);
                        
                        // Insert after original and highlight briefly
                        $newRow.hide().insertAfter($row).fadeIn(400);
                        $newRow.css('background-color', '#fffde7');
                        setTimeout(function() {
                            $newRow.css('transition', 'background-color 0.5s');
                            $newRow.css('background-color', '');
                        }, 1000);
                        
                        $btn.text(originalText);
                    } else {
                        alert(response.data.message || wcos_admin.i18n.error);
                        $btn.text(originalText);
                    }
                }).fail(function() {
                    alert(wcos_admin.i18n.error || 'Error occurred.');
                    $btn.text(originalText);
                });
            });

            // Add addon type select (product page)
            $(document).on('change', '.wcos-addon-type-select', function() {
                var btn = $(this).siblings('.wcos-add-addon-btn');
                btn.prop('disabled', !$(this).val());
            });

            // Add addon button (product page)
            $(document).on('click', '.wcos-add-addon-btn', function(e) {
                e.preventDefault();
                var select = $(this).siblings('.wcos-addon-type-select');
                var addonType = select.val();
                var productId = $('.wcos-addons-list').data('product-id');
                
                if (addonType && productId) {
                    self.openAddonModal(null, null, productId, addonType);
                }
            });

            // Modal close
            $(document).on('click', '.wcos-modal-close, .wcos-modal-cancel', function() {
                $('.wcos-modal-overlay').remove();
            });

            // Close modal on overlay click
            $(document).on('click', '.wcos-modal-overlay', function(e) {
                if ($(e.target).hasClass('wcos-modal-overlay')) {
                    $(this).remove();
                }
            });

            // Modal save group
            $(document).on('click', '.wcos-save-group', function() {
                var modal = $(this).closest('.wcos-modal');
                var form = modal.find('form');
                self.saveGroup(form);
            });

            // Modal save addon
            $(document).on('click', '.wcos-save-addon', function() {
                var modal = $(this).closest('.wcos-modal');
                var form = modal.find('form');
                self.saveAddon(form);
            });

            // Add option row
            $(document).on('click', '.wcos-add-option-btn', function(e) {
                e.preventDefault();
                self.addOptionRow();
            });

            // Remove option row
            $(document).on('click', '.wcos-remove-option', function(e) {
                e.preventDefault();
                $(this).closest('.wcos-option-row').remove();
            });

            // Export
            $(document).on('click', '.wcos-export-group, .wcos-export-btn', function(e) {
                e.preventDefault();
                var groupId = $(this).closest('.wcos-group-card').data('group-id');
                var productId = $('.wcos-addons-list').data('product-id');

                $.post(wcos_admin.ajax_url, {
                    action: 'wcos_export_addons',
                    nonce: wcos_admin.nonce,
                    group_id: groupId || 0,
                    product_id: productId || 0
                }, function(response) {
                    if (response.success) {
                        prompt('Copy this export data:', response.data.data);
                    }
                });
            });

            // Import
            $(document).on('click', '.wcos-import-group, .wcos-import-btn', function(e) {
                e.preventDefault();
                var data = prompt('Paste import data:');
                if (!data) return;

                var groupId = $(this).closest('.wcos-group-card').data('group-id');
                var productId = $('.wcos-addons-list').data('product-id');

                $.post(wcos_admin.ajax_url, {
                    action: 'wcos_import_addons',
                    nonce: wcos_admin.nonce,
                    group_id: groupId || 0,
                    product_id: productId || 0,
                    import_data: data
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message || wcos_admin.i18n.error);
                    }
                });
            });

            // Addon type change in modal
            $(document).on('change', '.wcos-addon-type-field', function() {
                var $modal = $(this).closest('.wcos-addon-modal');
                var type = $(this).val();
                var hasOptions = ['multiple_choice', 'checkboxes'].indexOf(type) !== -1;
                
                if (hasOptions) {
                    $modal.find('.wcos-options-section').show();
                } else {
                    $modal.find('.wcos-options-section').hide();
                }
                
                // Show/hide type-specific settings
                $modal.find('.wcos-type-setting-group').hide();
                $modal.find('.wcos-type-setting-group[data-type="' + type + '"]').show();
            });
        },

        initSortable: function() {
            if ($('.wcos-addons-list').length && $.fn.sortable) {
                $('.wcos-addons-list').sortable({
                    handle: '.wcos-addon-drag-handle',
                    update: function(event, ui) {
                        var order = $(this).sortable('toArray', { attribute: 'data-addon-id' });
                        var productId = $(this).data('product-id');

                        $.post(wcos_admin.ajax_url, {
                            action: 'wcos_reorder_addons',
                            nonce: wcos_admin.nonce,
                            product_id: productId,
                            order: order
                        });
                    }
                });
            }

            // Sortable options in modal
            $(document).on('click', '.wcos-modal', function() {
                var $modal = $(this);
                var $optionsList = $modal.find('.wcos-options-list');
                if ($optionsList.length && $.fn.sortable) {
                    $optionsList.sortable({
                        handle: '.wcos-option-drag',
                        items: '.wcos-option-row'
                    });
                }
            });
        },

        initSelect2: function() {
            if ($.fn.select2) {
                var $modal = $('.wcos-modal-body');
                $('.wcos-category-select').select2({
                    placeholder: 'Select categories...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $modal.length ? $modal : $('body')
                });
            }
        },

        openGroupModal: function(groupId) {
            var self = this;
            var template = wp.template('wcos-group-modal');
            var data = {
                title: groupId ? wcos_admin.i18n.edit_group || 'Edit Group' : wcos_admin.i18n.add_group || 'Add New Group',
                group_id: groupId || 0,
                name: '',
                priority: 10,
                enabled: true,
                categories: []
            };

            if (groupId) {
                // Load existing group data
                $.ajax({
                    url: wcos_admin.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'wcos_get_group',
                        nonce: wcos_admin.nonce,
                        group_id: groupId
                    },
                    success: function(response) {
                        if (response.success) {
                            var group = response.data;
                            data.name = group.name || '';
                            data.priority = group.priority || 10;
                            data.enabled = parseInt(group.enabled) === 1;
                            data.categories = group.categories || [];
                            
                            $('body').append(template(data));
                            self.initSelect2();
                            
                            // Set the selected categories
                            if (data.categories.length > 0) {
                                $('.wcos-category-select').val(data.categories).trigger('change');
                            }
                            
                            // Set form values
                            $('input[name="name"]').val(data.name);
                            $('input[name="priority"]').val(data.priority);
                            $('input[name="enabled"]').prop('checked', data.enabled);
                        }
                    }
                });
            } else {
                $('body').append(template(data));
                this.initSelect2();
            }
        },

        openAddonModal: function(addonId, groupId, productId, addonType) {
            var self = this;
            var template = wp.template('wcos-addon-modal');
            
            var data = {
                title: addonId ? 'Edit Add-on' : 'Add New Add-on',
                addon_id: addonId || 0,
                group_id: groupId || 0,
                product_id: productId || 0,
                addon_type: addonType || 'multiple_choice',
                addon_title: '',
                title_format: 'label',
                description: '',
                required: false,
                enabled: true,
                options: [],
                settings: {}
            };

            // If editing, load existing data
            if (addonId) {
                $.ajax({
                    url: wcos_admin.ajax_url,
                    type: 'GET',
                    data: {
                        action: 'wcos_get_addon',
                        nonce: wcos_admin.nonce,
                        addon_id: addonId
                    },
                    success: function(response) {
                        if (response.success) {
                            var addon = response.data;
                            data.addon_type = addon.addon_type;
                            data.addon_title = addon.title;
                            data.title_format = addon.title_format || 'label';
                            data.description = addon.description;
                            data.required = parseInt(addon.required) === 1;
                            data.enabled = parseInt(addon.enabled) === 1;
                            data.options = addon.options || [];
                            data.settings = addon.settings || {};
                            
                            $('body').append(template(data));
                            
                            var $modal = $('.wcos-addon-modal');
                            
                            self.initSelect2();
                            self.populateOptions(data.options);
                            self.populateSettings(data.settings, data.addon_type);
                            
                            // Set form values AFTER template is added to DOM - scoped to modal
                            $modal.find('.wcos-addon-type-field').val(data.addon_type).trigger('change');
                            $modal.find('select[name="title_format"]').val(data.title_format);
                            $modal.find('input[name="title"]').val(data.addon_title);
                            $modal.find('textarea[name="description"]').val(data.description);
                            $modal.find('input[name="required"]').prop('checked', data.required);
                            $modal.find('input[name="enabled"]').prop('checked', data.enabled);
                        }
                    }
                });
            } else {
                $('body').append(template(data));
                this.initSelect2();
                $('.wcos-addon-type-field').trigger('change');
            }
        },
        
        populateSettings: function(settings, addonType) {
            if (!settings || typeof settings !== 'object') return;
            
            var $modal = $('.wcos-addon-modal');
            
            // Show the correct settings group
            $modal.find('.wcos-type-setting-group').hide();
            $modal.find('.wcos-type-setting-group[data-type="' + addonType + '"]').show();
            
            // Populate each setting value
            $.each(settings, function(key, value) {
                var $field = $modal.find('.wcos-type-setting[data-setting="' + key + '"]');
                if ($field.length) {
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', value == 1 || value === true || value === '1');
                    } else {
                        $field.val(value);
                    }
                }
            });
            
            // Handle apply_to_variations (multi-select)
            if (settings.apply_to_variations && Array.isArray(settings.apply_to_variations)) {
                var $variationSelect = $modal.find('select[name="apply_to_variations[]"]');
                if ($variationSelect.length) {
                    // Convert to strings for comparison
                    var varIds = settings.apply_to_variations.map(function(id) {
                        return id.toString();
                    });
                    $variationSelect.val(varIds);
                }
            }
        },

        populateOptions: function(options) {
            var $modal = $('.wcos-addon-modal');
            var $list = $modal.find('.wcos-options-list');
            $list.empty();
            
            if (options && options.length) {
                options.forEach(function(opt) {
                    var row = WCOS_Admin.createOptionRow(opt);
                    $list.append(row);
                });
            }
        },

        createOptionRow: function(data) {
            data = data || {};
            var html = '<div class="wcos-option-row">' +
                '<span class="wcos-option-drag dashicons dashicons-menu"></span>' +
                '<input type="hidden" name="options[][id]" value="' + (data.id || '') + '">' +
                '<input type="text" name="options[][label]" placeholder="Option label" value="' + (data.label || '') + '" class="wcos-option-label">' +
                '<input type="text" name="options[][price]" placeholder="Price" value="' + (data.price || '') + '" class="wcos-option-price" style="width:80px;">' +
                '<select name="options[][price_type]" class="wcos-option-price-type">' +
                '<option value="flat"' + (data.price_type === 'flat' ? ' selected' : '') + '>Flat</option>' +
                '<option value="quantity"' + (data.price_type === 'quantity' ? ' selected' : '') + '>Qty Based</option>' +
                '<option value="percentage"' + (data.price_type === 'percentage' ? ' selected' : '') + '>Percentage</option>' +
                '</select>' +
                '<label><input type="checkbox" name="options[][is_default]" value="1"' + (parseInt(data.is_default) ? ' checked' : '') + '> Default</label>' +
                '<button type="button" class="button-link wcos-remove-option" style="color:#a00;">Remove</button>' +
                '</div>';
            return html;
        },

        addOptionRow: function() {
            var $modal = $('.wcos-addon-modal');
            var row = this.createOptionRow();
            $modal.find('.wcos-options-list').append(row);
        },

        saveGroup: function(form) {
            var formData = {
                action: 'wcos_save_group',
                nonce: wcos_admin.nonce,
                group_id: form.find('[name="group_id"]').val(),
                name: form.find('[name="name"]').val(),
                priority: form.find('[name="priority"]').val(),
                categories: form.find('[name="categories[]"]').val() || [],
                enabled: form.find('[name="enabled"]').is(':checked') ? 1 : 0
            };

            $.post(wcos_admin.ajax_url, formData, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || wcos_admin.i18n.error);
                }
            });
        },

        saveAddon: function(form) {
            var self = this;
            
            // Collect options data properly
            var options = [];
            form.find('.wcos-option-row').each(function(index) {
                var $row = $(this);
                options.push({
                    id: $row.find('input[name="options[][id]"]').val(),
                    label: $row.find('input[name="options[][label]"]').val(),
                    price: $row.find('input[name="options[][price]"]').val(),
                    price_type: $row.find('select[name="options[][price_type]"]').val(),
                    is_default: $row.find('input[name="options[][is_default]"]').is(':checked') ? 1 : 0
                });
            });
            
            // Collect type-specific settings
            var settings = {};
            var addonType = form.find('[name="addon_type"]').val();
            form.find('.wcos-type-setting-group[data-type="' + addonType + '"] .wcos-type-setting').each(function() {
                var $field = $(this);
                var key = $field.data('setting');
                if ($field.is(':checkbox')) {
                    settings[key] = $field.is(':checked') ? 1 : 0;
                } else {
                    settings[key] = $field.val();
                }
            });
            
            // Collect apply_to_variations (for variable products)
            var applyToVariations = form.find('select[name="apply_to_variations[]"]').val() || [];

            // Collect conditional logic rules
            var conditionalLogic = {
                enabled: form.find('[name="conditional_logic[enabled]"]').is(':checked') ? 1 : 0,
                action: form.find('[name="conditional_logic[action]"]').val() || 'show',
                relation: form.find('[name="conditional_logic[relation]"]').val() || 'all',
                rules: []
            };
            
            form.find('.wcos-condition-rule').each(function() {
                var $rule = $(this);
                conditionalLogic.rules.push({
                    addon_id: $rule.find('.wcos-condition-addon').val(),
                    operator: $rule.find('.wcos-condition-operator').val(),
                    value: $rule.find('.wcos-condition-value').val()
                });
            });

            var formData = {
                action: 'wcos_save_addon',
                nonce: wcos_admin.nonce,
                addon_id: form.find('[name="addon_id"]').val(),
                group_id: form.find('[name="group_id"]').val(),
                product_id: form.find('[name="product_id"]').val(),
                addon_type: form.find('[name="addon_type"]').val(),
                title: form.find('[name="title"]').val(),
                title_format: form.find('[name="title_format"]').val(),
                description: form.find('[name="description"]').val(),
                required: form.find('[name="required"]').is(':checked') ? 1 : 0,
                enabled: form.find('[name="enabled"]').is(':checked') ? 1 : 0,
                options: options,
                settings: settings,
                apply_to_variations: applyToVariations,
                conditional_logic: conditionalLogic
            };

            $.post(wcos_admin.ajax_url, formData, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || wcos_admin.i18n.error);
                }
            });
        },
        
        /**
         * Initialize conditional logic UI
         */
        initConditionalLogic: function() {
            var self = this;
            
            // Toggle conditional rules visibility
            $(document).on('change', '.wcos-conditional-toggle', function() {
                var $section = $(this).closest('.wcos-conditional-logic-section');
                $section.find('.wcos-conditional-rules').toggle($(this).is(':checked'));
            });
            
            // Add condition rule
            $(document).on('click', '.wcos-add-condition-rule', function() {
                self.addConditionRule($(this).closest('.wcos-conditional-logic-section'));
            });
            
            // Remove condition rule
            $(document).on('click', '.wcos-remove-condition-rule', function() {
                $(this).closest('.wcos-condition-rule').remove();
            });
            
            // Update value field based on selected addon
            $(document).on('change', '.wcos-condition-addon', function() {
                self.updateConditionValueOptions($(this));
            });
        },
        
        /**
         * Add a new condition rule
         */
        addConditionRule: function($section) {
            var self = this;
            var $rulesList = $section.find('.wcos-condition-rules-list');
            var ruleIndex = $rulesList.find('.wcos-condition-rule').length;
            
            var ruleHtml = '<div class="wcos-condition-rule" data-index="' + ruleIndex + '">' +
                '<select class="wcos-condition-addon">' +
                '<option value="">' + wcos_admin.i18n.select_addon + '</option>' +
                '</select>' +
                '<select class="wcos-condition-operator">' +
                '<option value="is">' + wcos_admin.i18n.is + '</option>' +
                '<option value="is_not">' + wcos_admin.i18n.is_not + '</option>' +
                '<option value="is_empty">' + wcos_admin.i18n.is_empty + '</option>' +
                '<option value="is_not_empty">' + wcos_admin.i18n.is_not_empty + '</option>' +
                '<option value="contains">' + wcos_admin.i18n.contains + '</option>' +
                '<option value="greater_than">' + wcos_admin.i18n.greater_than + '</option>' +
                '<option value="less_than">' + wcos_admin.i18n.less_than + '</option>' +
                '</select>' +
                '<input type="text" class="wcos-condition-value" placeholder="' + wcos_admin.i18n.value + '">' +
                '<button type="button" class="button wcos-remove-condition-rule">&times;</button>' +
                '</div>';
            
            $rulesList.append(ruleHtml);
            
            // Populate addon options
            this.populateConditionAddons($rulesList.find('.wcos-condition-rule').last().find('.wcos-condition-addon'));
        },
        
        /**
         * Populate addon dropdown in condition rule
         */
        populateConditionAddons: function($select) {
            var currentAddonId = $('.wcos-addon-modal').find('[name="addon_id"]').val();
            var $modal = $('.wcos-addon-modal');
            var productId = $modal.find('[name="product_id"]').val();
            var groupId = $modal.find('[name="group_id"]').val();
            
            // Get addons from the page
            var addons = [];
            
            // From product addons list
            $('.wcos-addons-list tr[data-addon-id]').each(function() {
                var id = $(this).data('addon-id');
                var title = $(this).find('.wcos-addon-title').text().trim();
                if (id && id != currentAddonId) {
                    addons.push({ id: id, title: title });
                }
            });
            
            // From global group addons
            $('.wcos-group-card .wcos-addon-item').each(function() {
                var id = $(this).data('addon-id');
                var title = $(this).find('.wcos-addon-title').text().trim();
                if (id && id != currentAddonId) {
                    addons.push({ id: id, title: title });
                }
            });
            
            // Populate select
            addons.forEach(function(addon) {
                $select.append('<option value="' + addon.id + '">' + addon.title + '</option>');
            });
        },
        
        /**
         * Update condition value field based on selected addon type
         */
        updateConditionValueOptions: function($addonSelect) {
            var addonId = $addonSelect.val();
            var $rule = $addonSelect.closest('.wcos-condition-rule');
            var $valueField = $rule.find('.wcos-condition-value');
            
            // For now, just show text input. Can be enhanced to show dropdown for choice addons
            $valueField.show();
        },
        
        /**
         * Populate conditional logic from saved data
         */
        populateConditionalLogic: function(conditionalLogic) {
            if (!conditionalLogic || !conditionalLogic.enabled) return;
            
            var $modal = $('.wcos-addon-modal');
            var $section = $modal.find('.wcos-conditional-logic-section');
            
            $section.find('.wcos-conditional-toggle').prop('checked', true);
            $section.find('.wcos-conditional-rules').show();
            $section.find('[name="conditional_logic[action]"]').val(conditionalLogic.action || 'show');
            $section.find('[name="conditional_logic[relation]"]').val(conditionalLogic.relation || 'all');
            
            // Add rules
            if (conditionalLogic.rules && conditionalLogic.rules.length) {
                var self = this;
                conditionalLogic.rules.forEach(function(rule) {
                    self.addConditionRule($section);
                    var $lastRule = $section.find('.wcos-condition-rule').last();
                    $lastRule.find('.wcos-condition-addon').val(rule.addon_id);
                    $lastRule.find('.wcos-condition-operator').val(rule.operator);
                    $lastRule.find('.wcos-condition-value').val(rule.value);
                });
            }
        }
    };

    $(document).ready(function() {
        WCOS_Admin.init();
    });

})(jQuery);
