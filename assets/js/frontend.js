/**
 * WooCommerce Options Studio - Frontend JS
 */
(function($) {
    'use strict';

    var WCOS_Frontend = {
        init: function() {
            this.wrapper = $('.upad-addons-wrapper');
            if (!this.wrapper.length) return;

            this.productId = this.wrapper.data('product-id');
            this.basePrice = parseFloat(this.wrapper.data('product-price')) || 0;
            this.isVariable = this.wrapper.data('is-variable') === 'true' || this.wrapper.data('is-variable') === true;

            this.bindEvents();
            this.initDatePickers();
            this.initFileUploads();
            this.initVariationHandling();
            this.initQuantityLinked();
            this.updateTotals();
        },

        bindEvents: function() {
            var self = this;

            // Field changes
            this.wrapper.on('change', '.upad-field', function() {
                self.updateTotals();
            });
            
            // Checkbox min/max selection enforcement
            this.wrapper.on('change', '.upad-checkbox', function() {
                self.enforceCheckboxLimits($(this));
            });
            
            // Form submission validation
            this.wrapper.closest('form.cart').on('submit', function(e) {
                if (!self.validateAddons()) {
                    e.preventDefault();
                    return false;
                }
            });

            // Quantity buttons
            this.wrapper.on('click', '.upad-qty-minus', function() {
                var input = $(this).siblings('.upad-quantity-input');
                var val = parseInt(input.val()) || 0;
                var min = parseInt(input.attr('min')) || 0;
                var step = parseInt(input.attr('step')) || 1;
                if (val - step >= min) {
                    input.val(val - step).trigger('change');
                }
            });

            this.wrapper.on('click', '.upad-qty-plus', function() {
                var input = $(this).siblings('.upad-quantity-input');
                var val = parseInt(input.val()) || 0;
                var max = parseInt(input.attr('max')) || Infinity;
                var step = parseInt(input.attr('step')) || 1;
                if (val + step <= max) {
                    input.val(val + step).trigger('change');
                }
            });

            // Character counter
            this.wrapper.on('input', '.upad-textarea[maxlength]', function() {
                var max = parseInt($(this).attr('maxlength'));
                var remaining = max - $(this).val().length;
                $(this).siblings('.upad-char-counter').find('.upad-chars-remaining').text(remaining);
            });
        },
        
        /**
         * Enforce checkbox max selection limit
         */
        enforceCheckboxLimits: function($checkbox) {
            var $group = $checkbox.closest('.upad-checkbox-group');
            var maxSelections = parseInt($group.data('max-selections')) || 0;
            
            if (maxSelections <= 0) return;
            
            var $checkboxes = $group.find('.upad-checkbox');
            var checkedCount = $group.find('.upad-checkbox:checked').length;
            
            if (checkedCount >= maxSelections) {
                // Disable unchecked checkboxes
                $checkboxes.not(':checked').prop('disabled', true);
            } else {
                // Re-enable all checkboxes
                $checkboxes.prop('disabled', false);
            }
        },
        
        /**
         * Validate all addons before add to cart
         */
        validateAddons: function() {
            var self = this;
            var isValid = true;
            var errorMessages = [];
            var $form = this.wrapper.closest('form.cart');
            var productQty = parseInt($form.find('input[name="quantity"]').val()) || 1;
            
            // Check each visible checkbox group
            this.wrapper.find('.upad-addon:visible .upad-checkbox-group').each(function() {
                var $group = $(this);
                var $addon = $group.closest('.upad-addon');
                var addonTitle = $addon.find('.upad-addon-title').text().trim();
                var linkToQty = $group.data('link-to-quantity') === 1 || $group.data('link-to-quantity') === '1';
                var allowSame = $group.data('allow-same') === 1 || $group.data('allow-same') === '1';
                
                // Remove previous error state
                $addon.removeClass('upad-addon-error');
                
                if (linkToQty) {
                    // Quantity-linked validation
                    if (allowSame) {
                        // Check if option quantities sum to product quantity
                        var totalSelected = self.getOptionQtyTotal($group);
                        if (totalSelected !== productQty) {
                            isValid = false;
                            $addon.addClass('upad-addon-error');
                            errorMessages.push(addonTitle + ': ' + wcos_params.i18n.select_qty_total.replace('%d', productQty).replace('%n', totalSelected));
                        }
                    } else {
                        // Standard checkbox count must match product quantity
                        var checkedCount = $group.find('.upad-checkbox:checked').length;
                        if (checkedCount !== productQty) {
                            isValid = false;
                            $addon.addClass('upad-addon-error');
                            errorMessages.push(addonTitle + ': ' + wcos_params.i18n.select_qty_options.replace('%d', productQty));
                        }
                    }
                } else {
                    // Standard min/max validation
                    var minSelections = parseInt($group.data('min-selections')) || 0;
                    var maxSelections = parseInt($group.data('max-selections')) || 0;
                    var checkedCount = $group.find('.upad-checkbox:checked').length;
                    
                    if (minSelections > 0 && checkedCount < minSelections) {
                        isValid = false;
                        $addon.addClass('upad-addon-error');
                        if (minSelections === maxSelections) {
                            errorMessages.push(addonTitle + ': ' + wcos_params.i18n.select_exactly.replace('%d', minSelections));
                        } else {
                            errorMessages.push(addonTitle + ': ' + wcos_params.i18n.select_minimum.replace('%d', minSelections));
                        }
                    }
                }
            });
            
            if (!isValid && errorMessages.length > 0) {
                alert(errorMessages.join('\n'));
                // Scroll to first error
                var $firstError = this.wrapper.find('.upad-addon-error').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 100
                    }, 300);
                }
            }
            
            return isValid;
        },

        initDatePickers: function() {
            if (!$.fn.datepicker) return;

            this.wrapper.find('.upad-datepicker').each(function() {
                $(this).datepicker({
                    dateFormat: 'yy-mm-dd',
                    minDate: 0
                });
            });
        },

        /**
         * Initialize variation handling - show/hide addons based on selected variation
         */
        initVariationHandling: function() {
            var self = this;
            
            // Only for variable products
            if (!this.isVariable) return;
            
            var $form = this.wrapper.closest('form.variations_form');
            if (!$form.length) {
                $form = $('form.variations_form');
            }
            if (!$form.length) return;
            
            // Listen for WooCommerce variation events
            $form.on('found_variation', function(event, variation) {
                self.handleVariationChange(variation.variation_id);
            });
            
            $form.on('reset_data', function() {
                self.handleVariationChange(null);
            });
            
            // Initial state - no variation selected, hide variation-specific addons
            this.handleVariationChange(null);
        },
        
        /**
         * Show/hide addons based on selected variation
         */
        handleVariationChange: function(variationId) {
            var self = this;
            
            this.wrapper.find('.upad-addon[data-variations]').each(function() {
                var $addon = $(this);
                var allowedVariations = $addon.data('variations').toString().split(',').map(function(id) {
                    return parseInt(id.trim());
                });
                
                if (!variationId) {
                    // No variation selected - hide variation-specific addons
                    $addon.hide().addClass('upad-addon-hidden');
                    // Clear values to prevent accidental submission
                    $addon.find('.upad-field').each(function() {
                        if ($(this).is('select')) {
                            $(this).val('');
                        } else if ($(this).is(':checkbox, :radio')) {
                            $(this).prop('checked', false);
                        } else {
                            $(this).val('');
                        }
                    });
                } else if (allowedVariations.indexOf(parseInt(variationId)) !== -1) {
                    // Variation matches - show addon
                    $addon.show().removeClass('upad-addon-hidden');
                } else {
                    // Variation doesn't match - hide addon
                    $addon.hide().addClass('upad-addon-hidden');
                    // Clear values
                    $addon.find('.upad-field').each(function() {
                        if ($(this).is('select')) {
                            $(this).val('');
                        } else if ($(this).is(':checkbox, :radio')) {
                            $(this).prop('checked', false);
                        } else {
                            $(this).val('');
                        }
                    });
                }
            });
            
            // Addons without data-variations attribute are always shown
            // Update totals after visibility changes
            this.updateTotals();
        },
        
        /**
         * Initialize quantity-linked checkbox groups
         */
        initQuantityLinked: function() {
            var self = this;
            var $form = this.wrapper.closest('form.cart');
            if (!$form.length) return;
            
            var $qtyInput = $form.find('input[name="quantity"]');
            if (!$qtyInput.length) return;
            
            var $linkedGroups = this.wrapper.find('.upad-checkbox-group[data-link-to-quantity="1"]');
            if (!$linkedGroups.length) return;
            
            // Update on quantity change
            $qtyInput.on('change input', function() {
                self.updateQuantityLinked(parseInt($(this).val()) || 1);
            });
            
            // Also listen for +/- buttons that WooCommerce themes often add
            $form.on('click', '.qty-btn, .quantity-btn, .plus, .minus, [data-action="increase"], [data-action="decrease"]', function() {
                setTimeout(function() {
                    self.updateQuantityLinked(parseInt($qtyInput.val()) || 1);
                }, 50);
            });
            
            // Handle option quantity +/- buttons (for allow_same mode)
            this.wrapper.on('click', '.upad-option-qty-minus', function() {
                var $input = $(this).siblings('.upad-option-qty-input');
                var val = parseInt($input.val()) || 0;
                if (val > 0) {
                    $input.val(val - 1).trigger('change');
                }
            });
            
            this.wrapper.on('click', '.upad-option-qty-plus', function() {
                var $input = $(this).siblings('.upad-option-qty-input');
                var $group = $input.closest('.upad-checkbox-group');
                var productQty = parseInt($qtyInput.val()) || 1;
                var currentTotal = self.getOptionQtyTotal($group);
                var val = parseInt($input.val()) || 0;
                
                if (currentTotal < productQty) {
                    $input.val(val + 1).trigger('change');
                }
            });
            
            // Update totals when option qty changes
            this.wrapper.on('change', '.upad-option-qty-input', function() {
                var $group = $(this).closest('.upad-checkbox-group');
                self.updateOptionQtyTotal($group);
                self.updateTotals();
            });
            
            // Initial update
            this.updateQuantityLinked(parseInt($qtyInput.val()) || 1);
        },
        
        /**
         * Update quantity-linked checkbox groups based on product quantity
         */
        updateQuantityLinked: function(productQty) {
            var self = this;
            
            this.wrapper.find('.upad-checkbox-group[data-link-to-quantity="1"]').each(function() {
                var $group = $(this);
                var allowSame = $group.data('allow-same') === 1 || $group.data('allow-same') === '1';
                
                if (allowSame) {
                    // Update required total display
                    $group.find('.upad-qty-total-required').text(productQty);
                    self.updateOptionQtyTotal($group);
                } else {
                    // Update min/max to match product quantity
                    $group.attr('data-min-selections', productQty);
                    $group.attr('data-max-selections', productQty);
                    
                    // Update hint text
                    var $hint = $group.find('.upad-qty-linked-hint');
                    if ($hint.length) {
                        $hint.text(wcos_params.i18n.select_qty_options.replace('%d', productQty));
                    }
                    
                    // Re-enforce limits
                    self.enforceCheckboxLimits($group.find('.upad-checkbox').first());
                }
            });
        },
        
        /**
         * Get total of all option quantities in a group
         */
        getOptionQtyTotal: function($group) {
            var total = 0;
            $group.find('.upad-option-qty-input').each(function() {
                total += parseInt($(this).val()) || 0;
            });
            return total;
        },
        
        /**
         * Update option quantity total display and button states
         */
        updateOptionQtyTotal: function($group) {
            var total = this.getOptionQtyTotal($group);
            var $form = this.wrapper.closest('form.cart');
            var productQty = parseInt($form.find('input[name="quantity"]').val()) || 1;
            
            // Update counter
            $group.find('.upad-qty-total-count').text(total);
            
            // Visual feedback
            var $totalDiv = $group.find('.upad-qty-total');
            $totalDiv.removeClass('upad-qty-complete upad-qty-incomplete');
            if (total === productQty) {
                $totalDiv.addClass('upad-qty-complete');
            } else {
                $totalDiv.addClass('upad-qty-incomplete');
            }
            
            // Disable + buttons if at limit
            if (total >= productQty) {
                $group.find('.upad-option-qty-plus').prop('disabled', true);
            } else {
                $group.find('.upad-option-qty-plus').prop('disabled', false);
            }
        },

        initFileUploads: function() {
            var self = this;

            this.wrapper.find('.upad-file-upload-wrapper').each(function() {
                var wrapper = $(this);
                var dropzone = wrapper.find('.upad-file-dropzone');
                var input = wrapper.find('.upad-file-input');
                var filesList = wrapper.find('.upad-uploaded-files-list');
                var maxFiles = parseInt(wrapper.data('max-files')) || 1;
                var addonId = wrapper.data('addon-id');

                // Drag & drop
                dropzone.on('dragover dragenter', function(e) {
                    e.preventDefault();
                    $(this).addClass('dragover');
                });

                dropzone.on('dragleave drop', function(e) {
                    e.preventDefault();
                    $(this).removeClass('dragover');
                });

                dropzone.on('drop', function(e) {
                    var files = e.originalEvent.dataTransfer.files;
                    self.handleFileUpload(files, wrapper, addonId);
                });

                input.on('change', function() {
                    self.handleFileUpload(this.files, wrapper, addonId);
                });
            });
        },

        handleFileUpload: function(files, wrapper, addonId) {
            var self = this;
            var filesList = wrapper.find('.upad-uploaded-files-list');
            var hashInput = wrapper.find('.upad-file-hash');

            $.each(files, function(i, file) {
                var formData = new FormData();
                formData.append('action', 'wcos_upload_file');
                formData.append('nonce', wcos_params.upload_nonce);
                formData.append('file', file);
                formData.append('addon_id', addonId);
                formData.append('product_id', self.productId);

                var fileItem = $('<div class="upad-uploaded-file uploading"><span class="file-name">' + file.name + '</span><span class="file-size">' + wcos_params.i18n.uploading + '</span></div>');
                filesList.append(fileItem);

                $.ajax({
                    url: wcos_params.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            fileItem.removeClass('uploading');
                            fileItem.find('.file-size').text(response.data.file_size);
                            fileItem.append('<span class="remove-file" data-hash="' + response.data.upload_hash + '">&times;</span>');
                            
                            // Add hash to hidden input
                            var hashes = hashInput.val() ? hashInput.val().split(',') : [];
                            hashes.push(response.data.upload_hash);
                            hashInput.val(hashes.join(','));
                            
                            self.updateTotals();
                        } else {
                            fileItem.remove();
                            alert(response.data.message || wcos_params.i18n.upload_error);
                        }
                    },
                    error: function() {
                        fileItem.remove();
                        alert(wcos_params.i18n.upload_error);
                    }
                });
            });
        },

        updateTotals: function() {
            var self = this;
            var addonTotal = 0;

            this.wrapper.find('.upad-addon').each(function() {
                var addon = $(this);
                
                // Skip hidden addons (variation-restricted)
                if (addon.hasClass('upad-addon-hidden') || !addon.is(':visible')) {
                    return;
                }
                
                var type = addon.data('addon-type');
                var price = 0;

                switch(type) {
                    case 'multiple_choice':
                        var selected = addon.find('.upad-field:checked, .upad-dropdown option:selected');
                        if (selected.length && selected.val()) {
                            price = parseFloat(selected.data('price')) || 0;
                        }
                        break;

                    case 'checkboxes':
                        addon.find('.upad-checkbox:checked').each(function() {
                            price += parseFloat($(this).data('price')) || 0;
                        });
                        break;

                    case 'customer_price':
                        price = parseFloat(addon.find('.upad-price-input').val()) || 0;
                        break;

                    case 'quantity':
                        var qty = parseInt(addon.find('.upad-quantity-input').val()) || 0;
                        var unitPrice = parseFloat(addon.find('.upad-field-price-info').text().replace(/[^0-9.-]/g, '')) || 0;
                        price = qty * unitPrice;
                        break;
                    
                    case 'image_swatches':
                        addon.find('.upad-swatch-input:checked').each(function() {
                            price += parseFloat($(this).data('price')) || 0;
                        });
                        break;
                    
                    case 'dimensions':
                        price = self.calculateDimensionPrice(addon);
                        break;
                    
                    case 'short_text':
                    case 'long_text':
                        price = self.calculateTextPrice(addon);
                        break;
                }

                addonTotal += price;
            });

            var grandTotal = this.basePrice + addonTotal;

            // Update display
            var totalsWrapper = $('.upad-totals-wrapper');
            if (addonTotal > 0) {
                totalsWrapper.show();
                totalsWrapper.find('.upad-options-total-value').html(this.formatPrice(addonTotal));
            } else {
                totalsWrapper.hide();
            }
            totalsWrapper.find('.upad-grand-total-value').html(this.formatPrice(grandTotal));

            // Update WooCommerce price if possible
            if ($('.single_variation_wrap .woocommerce-Price-amount').length) {
                // Variable product - update when variation changes
            } else {
                $('.product .price .woocommerce-Price-amount').first().html(this.formatPrice(grandTotal));
            }
        },

        formatPrice: function(price) {
            var formatted = price.toFixed(wcos_params.decimals);
            formatted = formatted.replace('.', wcos_params.decimal_sep);
            
            var format = wcos_params.price_format;
            return format.replace('%1$s', wcos_params.currency).replace('%2$s', formatted);
        },
        
        /**
         * Calculate dimension-based pricing
         */
        calculateDimensionPrice: function($addon) {
            var $wrapper = $addon.find('.upad-dimensions-wrapper');
            if (!$wrapper.length) return 0;
            
            var length = parseFloat($wrapper.find('.upad-dimension-length').val()) || 0;
            var width = parseFloat($wrapper.find('.upad-dimension-width').val()) || 0;
            var height = parseFloat($wrapper.find('.upad-dimension-height').val()) || 0;
            
            var formula = $wrapper.data('formula') || 'per_sq_unit';
            var pricePerUnit = parseFloat($wrapper.data('price-per-unit')) || 0;
            var customFormula = $wrapper.data('custom-formula') || '';
            var minPrice = parseFloat($wrapper.data('min-price')) || 0;
            
            var price = 0;
            
            switch (formula) {
                case 'per_unit':
                    price = length * pricePerUnit;
                    break;
                case 'per_sq_unit':
                    price = (length * width) * pricePerUnit;
                    break;
                case 'per_cubic_unit':
                    price = (length * width * height) * pricePerUnit;
                    break;
                case 'custom':
                    if (customFormula) {
                        try {
                            var formulaStr = customFormula
                                .replace(/\{length\}/g, length)
                                .replace(/\{width\}/g, width)
                                .replace(/\{height\}/g, height);
                            price = eval(formulaStr);
                        } catch (e) {
                            console.error('Formula error:', e);
                            price = 0;
                        }
                    }
                    break;
            }
            
            // Apply minimum price
            if (minPrice > 0 && price < minPrice) {
                price = minPrice;
            }
            
            // Update display
            $wrapper.find('.upad-dimension-price-value').html(this.formatPrice(price));
            
            return price;
        },
        
        /**
         * Calculate text-based pricing (per character/word/line)
         */
        calculateTextPrice: function($addon) {
            var $field = $addon.find('.upad-field');
            if (!$field.length) return 0;
            
            var text = $field.val() || '';
            var priceType = $addon.data('price-type') || 'flat';
            var price = parseFloat($addon.data('price')) || 0;
            var freeChars = parseInt($addon.data('free-chars')) || 0;
            
            if (!price || price === 0) return 0;
            
            var count = 0;
            
            switch (priceType) {
                case 'per_character':
                    count = text.length;
                    break;
                case 'per_word':
                    count = text.trim() ? text.trim().split(/\s+/).length : 0;
                    break;
                case 'per_line':
                    count = text.trim() ? text.trim().split(/\n/).length : 0;
                    break;
                default:
                    return price; // Flat fee
            }
            
            // Apply free characters/words/lines
            var chargeableCount = Math.max(0, count - freeChars);
            var totalPrice = chargeableCount * price;
            
            // Update display if exists
            var $priceInfo = $addon.find('.upad-char-price-info');
            if ($priceInfo.length) {
                $priceInfo.find('.upad-char-price-current').text(this.formatPrice(totalPrice));
            }
            
            return totalPrice;
        },
        
        /**
         * Initialize dimensions calculator
         */
        initDimensions: function() {
            var self = this;
            
            this.wrapper.on('input change', '.upad-dimension-length, .upad-dimension-width, .upad-dimension-height', function() {
                var $addon = $(this).closest('.upad-addon');
                self.calculateDimensionPrice($addon);
                self.updateTotals();
            });
            
            // Initial calculation
            this.wrapper.find('.upad-dimensions-wrapper').each(function() {
                var $addon = $(this).closest('.upad-addon');
                self.calculateDimensionPrice($addon);
            });
        },
        
        /**
         * Initialize sections/accordions
         */
        initSections: function() {
            var self = this;
            
            // Find section markers and wrap subsequent addons
            this.wrapper.find('.upad-section-marker').each(function() {
                var $marker = $(this);
                var sectionId = $marker.data('section-id');
                var collapsed = $marker.data('collapsed') === 1 || $marker.data('collapsed') === '1';
                var style = $marker.data('style');
                var showCount = $marker.data('show-count') === 1 || $marker.data('show-count') === '1';
                
                var $sectionAddon = $marker.closest('.upad-addon');
                var sectionTitle = $sectionAddon.find('.upad-addon-title').text();
                
                // Create section wrapper
                var $sectionWrapper = $('<div class="upad-section-wrapper' + (collapsed ? ' collapsed' : '') + '" data-section-id="' + sectionId + '">');
                var $header = $('<div class="upad-section-header"><h4 class="upad-section-title">' + sectionTitle + '</h4>' + (showCount ? '<span class="upad-section-count"></span>' : '') + '<span class="upad-section-toggle"></span></div>');
                var $content = $('<div class="upad-section-content"></div>');
                
                $sectionWrapper.append($header).append($content);
                
                // Find addons until next section or end
                var $nextAddons = $sectionAddon.nextUntil('.upad-addon:has(.upad-section-marker)');
                
                // Move addons into section content
                $content.append($nextAddons);
                
                // Replace section addon with wrapper
                $sectionAddon.replaceWith($sectionWrapper);
            });
            
            // Toggle functionality
            this.wrapper.on('click', '.upad-section-header', function() {
                var $wrapper = $(this).closest('.upad-section-wrapper');
                $wrapper.toggleClass('collapsed');
            });
            
            // Update selection count
            this.wrapper.on('change', '.upad-section-content .upad-field', function() {
                var $section = $(this).closest('.upad-section-wrapper');
                var $count = $section.find('.upad-section-count');
                if ($count.length) {
                    var selected = $section.find('.upad-field:checked, .upad-dropdown option:selected:not([value=""])').length;
                    $count.text(selected > 0 ? '(' + selected + ' selected)' : '');
                }
            });
        },
        
        /**
         * Initialize live product image preview
         */
        initImagePreview: function() {
            var self = this;
            
            // Listen for swatch/option changes that have linked images
            this.wrapper.on('change', '[data-product-image]', function() {
                var imageUrl = $(this).data('product-image');
                if (imageUrl && $(this).is(':checked') || $(this).is('option:selected')) {
                    self.updateProductImage(imageUrl);
                }
            });
        },
        
        /**
         * Update main product image
         */
        updateProductImage: function(imageUrl) {
            var $mainImage = $('.woocommerce-product-gallery__image img, .wp-post-image').first();
            if ($mainImage.length && imageUrl) {
                $mainImage.attr('src', imageUrl).attr('srcset', '');
            }
        },
        
        /**
         * Initialize conditional logic
         */
        initConditionalLogic: function() {
            // Initial evaluation
            this.evaluateConditionalLogic();
        },
        
        /**
         * Evaluate all conditional logic rules
         */
        evaluateConditionalLogic: function() {
            var self = this;
            
            this.wrapper.find('[data-conditions]').each(function() {
                var $addon = $(this);
                var conditions = $addon.data('conditions');
                
                if (!conditions || !conditions.rules || !conditions.rules.length) {
                    return;
                }
                
                var shouldShow = self.evaluateConditions(conditions);
                
                if (shouldShow) {
                    $addon.removeClass('upad-condition-hidden').slideDown(200);
                } else {
                    $addon.addClass('upad-condition-hidden').slideUp(200);
                    // Clear values when hidden
                    self.clearAddonValues($addon);
                }
            });
        },
        
        /**
         * Evaluate a set of conditions
         */
        evaluateConditions: function(conditions) {
            var self = this;
            var action = conditions.action || 'show';
            var relation = conditions.relation || 'all';
            var rules = conditions.rules || [];
            
            if (!rules.length) return true;
            
            var results = [];
            
            for (var i = 0; i < rules.length; i++) {
                var rule = rules[i];
                var result = self.evaluateRule(rule);
                results.push(result);
            }
            
            var passes;
            if (relation === 'all' || relation === 'and') {
                passes = results.indexOf(false) === -1;
            } else {
                passes = results.indexOf(true) !== -1;
            }
            
            return (action === 'show') ? passes : !passes;
        },
        
        /**
         * Evaluate a single condition rule
         */
        evaluateRule: function(rule) {
            var addonId = rule.addon_id;
            var operator = rule.operator || 'is';
            var expectedValue = rule.value || '';
            
            var $sourceAddon = this.wrapper.find('[data-addon-id="' + addonId + '"]');
            if (!$sourceAddon.length) return false;
            
            var actualValue = this.getAddonValue($sourceAddon);
            
            return this.compareValues(actualValue, operator, expectedValue);
        },
        
        /**
         * Get the current value of an addon
         */
        getAddonValue: function($addon) {
            var $field = $addon.find('.upad-field');
            
            // Checkboxes - return array of checked values
            if ($field.is(':checkbox')) {
                var values = [];
                $addon.find('.upad-field:checked').each(function() {
                    values.push($(this).val());
                });
                return values;
            }
            
            // Radio buttons
            if ($field.is(':radio')) {
                return $addon.find('.upad-field:checked').val() || '';
            }
            
            // Select dropdown
            if ($field.is('select')) {
                return $field.val() || '';
            }
            
            // Text/number inputs
            return $field.val() || '';
        },
        
        /**
         * Compare values based on operator
         */
        compareValues: function(actual, operator, expected) {
            // Array handling (checkboxes)
            if (Array.isArray(actual)) {
                switch (operator) {
                    case 'is_empty':
                        return actual.length === 0;
                    case 'is_not_empty':
                        return actual.length > 0;
                    case 'contains':
                    case 'is':
                        return actual.indexOf(expected) !== -1;
                    case 'not_contains':
                    case 'is_not':
                        return actual.indexOf(expected) === -1;
                    default:
                        return false;
                }
            }
            
            // String/number handling
            switch (operator) {
                case 'is':
                    return actual == expected;
                case 'is_not':
                    return actual != expected;
                case 'is_empty':
                    return actual === '' || actual === null || actual === undefined;
                case 'is_not_empty':
                    return actual !== '' && actual !== null && actual !== undefined;
                case 'contains':
                    return String(actual).indexOf(String(expected)) !== -1;
                case 'not_contains':
                    return String(actual).indexOf(String(expected)) === -1;
                case 'greater_than':
                    return parseFloat(actual) > parseFloat(expected);
                case 'less_than':
                    return parseFloat(actual) < parseFloat(expected);
                default:
                    return false;
            }
        },
        
        /**
         * Clear addon values when hidden by conditional logic
         */
        clearAddonValues: function($addon) {
            $addon.find('.upad-field').each(function() {
                var $field = $(this);
                if ($field.is(':checkbox, :radio')) {
                    $field.prop('checked', false);
                } else if ($field.is('select')) {
                    $field.val('');
                } else {
                    $field.val('');
                }
            });
        },
        
        /**
         * Calculate tiered pricing based on quantity
         */
        calculateTieredPrice: function(basePrice, tieredPrices, quantity) {
            if (!tieredPrices || !tieredPrices.length) {
                return basePrice;
            }
            
            // Sort tiers by min_qty descending to find the right tier
            var sortedTiers = tieredPrices.slice().sort(function(a, b) {
                return b.min_qty - a.min_qty;
            });
            
            for (var i = 0; i < sortedTiers.length; i++) {
                if (quantity >= sortedTiers[i].min_qty) {
                    return parseFloat(sortedTiers[i].price);
                }
            }
            
            return basePrice;
        },
        
        /**
         * Initialize dependent/cascading dropdowns
         */
        initDependentDropdowns: function() {
            var self = this;
            
            // Find addons that depend on others
            this.wrapper.find('[data-dependent-addon]').each(function() {
                var $addon = $(this);
                var parentAddonId = $addon.data('dependent-addon');
                var $parentAddon = self.wrapper.find('[data-addon-id="' + parentAddonId + '"]');
                
                if ($parentAddon.length) {
                    // Store original options
                    var $select = $addon.find('select.upad-field');
                    if ($select.length) {
                        $select.data('all-options', $select.find('option').clone());
                    }
                    
                    // Listen for parent changes
                    $parentAddon.on('change', '.upad-field', function() {
                        self.updateDependentOptions($addon, $(this).val());
                    });
                    
                    // Initial filter
                    var parentVal = $parentAddon.find('.upad-field').val();
                    if (parentVal) {
                        self.updateDependentOptions($addon, parentVal);
                    }
                }
            });
        },
        
        /**
         * Update dependent dropdown options based on parent selection
         */
        updateDependentOptions: function($addon, parentValue) {
            var $select = $addon.find('select.upad-field');
            if (!$select.length) return;
            
            var allOptions = $select.data('all-options');
            if (!allOptions) return;
            
            // Clear and repopulate
            $select.empty();
            $select.append('<option value="">' + wcos_params.i18n.select_option + '</option>');
            
            allOptions.each(function() {
                var $opt = $(this);
                var parentId = $opt.data('parent-option');
                
                // Show option if it matches parent or has no parent restriction
                if (!parentId || parentId == parentValue) {
                    $select.append($opt.clone());
                }
            });
        }
    };

    $(document).ready(function() {
        WCOS_Frontend.init();
    });

})(jQuery);
