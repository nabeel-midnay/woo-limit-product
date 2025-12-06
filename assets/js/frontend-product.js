/**
 * WooCommerce Limited Product - Frontend Product Page JavaScript
 *
 * Handles product page interactions for limited edition products
 *
 * @package WooCommerce Limited Product
 */

(function ($) {
    "use strict";

    $(document).ready(function () {
        var $limitedNumberInput = $(".woo-limit");
        var $addToCartButton = $(
            'button.single_add_to_cart_button, button[name="add-to-cart"]'
        );
        var $form = $("form.cart");
        var isVariableProduct =
            $form.hasClass("variations_form") ||
            $(".variations select").length > 0;
        var variationSelected = false;

        // Enable AJAX for normal products too
        var isLimitedProduct = $limitedNumberInput.length > 0;

        // Get error div
        var $errorDiv = $(".woo-limit-message");
        var $selectionErrorDiv = $(".woo-limit-selection-message");

        // Stock quantities parsed from data attributes
        var stockVal = $(".woo-limit-stock-quantity").val();
        var stockQuantityRemaining =
            stockVal === "" ? Infinity : parseInt(stockVal);
        var variationStockQuantities = {};

        // Parse variation stock quantities from JSON
        var variationStockJson = $(".woo-limit-variation-quantities").val();
        if (variationStockJson) {
            try {
                variationStockQuantities = JSON.parse(variationStockJson);
            } catch (e) {
                variationStockQuantities = {};
            }
        }

        // Helper function to get variation attribute values from variation ID
        function getVariationAttributes(variationId) {
            // Try to get from WooCommerce's variation form data
            var $variationsForm = $form;
            if ($variationsForm.length && $variationsForm.data('product_variations')) {
                var variations = $variationsForm.data('product_variations');
                for (var i = 0; i < variations.length; i++) {
                    if (variations[i].variation_id == variationId) {
                        return variations[i].attributes;
                    }
                }
            }
            return null;
        }

        // Helper function to disable only a specific variation swatch
        function disableVariationSwatch(variationId) {
            var attrs = getVariationAttributes(variationId);
            if (!attrs) return;

            // For each attribute in this variation, find and disable that specific swatch
            $.each(attrs, function(attrName, attrValue) {
                if (!attrValue) return; // Skip "any" values
                
                // Only disable if this is the ONLY variation using this attribute value
                // and that variation is out of stock
                var shouldDisable = isOnlyVariationForAttributeOutOfStock(attrName, attrValue, variationId);
                if (!shouldDisable) return;

                // Find the swatch with this value
                // The swatch typically has data-value or data-term attribute matching the value
                $('.rtwpvs-terms-wrapper .rtwpvs-term').each(function() {
                    var $swatch = $(this);
                    var swatchValue = $swatch.data('value') || $swatch.data('term') || $swatch.attr('data-value');
                    
                    if (swatchValue && String(swatchValue).toLowerCase() === String(attrValue).toLowerCase()) {
                        $swatch.addClass('disabled out-of-stock');
                    }
                });

                // Also disable the option in the native select dropdown
                // The attribute name format is like "attribute_pa_size" or "attribute_pa_color"
                var selectName = attrName.replace('attribute_', '');
                $('.variations select').each(function() {
                    var $select = $(this);
                    var selectAttrName = $select.attr('name') || $select.attr('id') || $select.data('attribute_name');
                    
                    // Check if this select matches the attribute
                    if (selectAttrName && (selectAttrName === attrName || selectAttrName === selectName || selectAttrName.indexOf(selectName) !== -1)) {
                        // Find and disable the option with matching value
                        $select.find('option').each(function() {
                            var $option = $(this);
                            var optionValue = $option.val();
                            
                            if (optionValue && String(optionValue).toLowerCase() === String(attrValue).toLowerCase()) {
                                $option.remove();
                            }
                        });
                    }
                });
            });
        }

        // Check if this attribute value is ONLY used by out-of-stock variations
        function isOnlyVariationForAttributeOutOfStock(attrName, attrValue, currentVariationId) {
            var $variationsForm = $form;
            if (!$variationsForm.length || !$variationsForm.data('product_variations')) {
                return true; // Fall back to disabling if we can't check
            }
            
            var variations = $variationsForm.data('product_variations');
            
            // Find all variations that have this attribute value
            for (var i = 0; i < variations.length; i++) {
                var variation = variations[i];
                var varAttrs = variation.attributes;
                
                // Check if this variation uses the same attribute value
                if (varAttrs && varAttrs[attrName] && 
                    (String(varAttrs[attrName]).toLowerCase() === String(attrValue).toLowerCase() || varAttrs[attrName] === '')) {
                    
                    // If this is a different variation that's still in stock, don't disable
                    if (variation.variation_id != currentVariationId) {
                        var varStock = variationStockQuantities[variation.variation_id];
                        // If stock is null (infinite) or > 0, this attribute value is still usable
                        if (varStock === null || varStock === undefined || varStock > 0) {
                            return false;
                        }
                    }
                }
            }
            
            return true; // All variations with this attribute value are out of stock
        }

        // Check if all variations are out of stock
        function areAllVariationsOutOfStock() {
            // If no variation stock data, assume not all are out of stock
            if (!variationStockQuantities || Object.keys(variationStockQuantities).length === 0) {
                return false;
            }
            
            for (var varId in variationStockQuantities) {
                if (variationStockQuantities.hasOwnProperty(varId)) {
                    var stock = variationStockQuantities[varId];
                    // null means infinite stock, or stock > 0 means in stock
                    if (stock === null || stock > 0) {
                        return false;
                    }
                }
            }
            
            return true;
        }

        // User limit remaining
        var userLimitVal = $(".woo-limit-user-remaining").val();
        var userLimitRemaining = userLimitVal === "" ? Infinity : parseInt(userLimitVal);

        // Function to get current variation stock
        function getCurrentVariationStock() {
            var variationId =
                $form.find('input[name="variation_id"]').val() || 0;
            if (
                variationId &&
                variationStockQuantities[variationId] !== undefined
            ) {
                return variationStockQuantities[variationId] === null
                    ? Infinity
                    : variationStockQuantities[variationId];
            }
            return stockQuantityRemaining;
        }

        // Check if variation is already selected on page load
        function checkVariationSelected() {
            if (!isVariableProduct) {
                variationSelected = true; // Not a variable product, so always "selected"
                return;
            }

            // Check if any variation is selected
            var hasVariation = false;
            $(".variations select").each(function () {
                if ($(this).val() && $(this).val() !== "") {
                    hasVariation = true;
                    return false; // break
                }
            });

            // Also check if variation_id input exists and has value
            var $variationId = $('input[name="variation_id"]');
            if (
                $variationId.length &&
                $variationId.val() &&
                $variationId.val() !== ""
            ) {
                $selectionErrorDiv.find(".woo-limit-variation-error").slideUp();
                $selectionErrorDiv.hide();
                hasVariation = true;

                // Check stock availability for the selected variation on load
                if (isLimitedProduct) {
                    var currentStock = getCurrentVariationStock();
                    if (currentStock <= 0) {
                        // Handle out-of-stock state centrally
                        window.IJWLP_Frontend_Common.handleOutOfStock(
                            isVariableProduct,
                            $addToCartButton,
                            $limitedNumberInput,
                            $errorDiv
                        );
                    }
                }
            }

            variationSelected = hasVariation;
            updateFieldStates();
        }

        // Force enable add to cart button and remove WooCommerce disabling classes/attrs on load
        // Ensure we clear both the disabled prop/attribute and any aria-disabled
        setTimeout(function () {
            // Only force-enable the button if it's NOT intentionally marked as
            // out of stock or currently in our loading/checking state. Some
            // other plugins add disabling attributes which we want to strip,
            // but we must not override a deliberate out-of-stock state set by
            // `handleOutOfStock`.
            if (
                !$addToCartButton.hasClass("woo-limit-loading") &&
                !$addToCartButton.hasClass("woo-outofstock")
            ) {
                $addToCartButton
                    .prop("disabled", false)
                    .removeAttr("disabled")
                    .removeAttr("aria-disabled")
                    .removeClass("disabled wc-variation-selection-needed");
            }
        }, 500);

        // If it's a variable product, disable the number field initially
        if (isVariableProduct) {
            $limitedNumberInput.prop("disabled", true).addClass("disabled");
            // Some themes/plugins add disabled attributes or aria-disabled; clear them here as well
            // Respect intentional out-of-stock or loading states when clearing
            // third-party disabling markers.
            if (
                !$addToCartButton.hasClass("woo-limit-loading") &&
                !$addToCartButton.hasClass("woo-outofstock")
            ) {
                $addToCartButton
                    .prop("disabled", false)
                    .removeAttr("disabled")
                    .removeAttr("aria-disabled")
                    .removeClass("disabled wc-variation-selection-needed");
            }
        }

        // Check initial variation state
        checkVariationSelected();

        // Function to initialize out-of-stock swatches on page load
        function initializeOutOfStockSwatches() {
            if (!isVariableProduct) return;

            // First check: if user limit is already reached, disable all
            if (userLimitRemaining <= 0) {
                $(".variations select").prop("disabled", true);
                $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("disabled");
                return;
            }

            // Second check: if all variations are out of stock, disable all
            if (areAllVariationsOutOfStock()) {
                $(".variations select").prop("disabled", true);
                $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("disabled");
                return;
            }

            // Third check: disable specific out-of-stock variation swatches
            // Loop through all variations and disable swatches for out-of-stock ones
            for (var varId in variationStockQuantities) {
                if (variationStockQuantities.hasOwnProperty(varId)) {
                    var stock = variationStockQuantities[varId];
                    // If stock is 0 or less (and not null which means infinite)
                    if (stock !== null && stock <= 0) {
                        disableVariationSwatch(varId);
                    }
                }
            }
        }

        // Initialize out-of-stock swatches on page load (with small delay to ensure DOM is ready)
        setTimeout(function() {
            initializeOutOfStockSwatches();
        }, 100);

        // MutationObserver to block YITH Wishlist and other plugins from re-adding disabled classes
        // This watches the button and strips 'disabled' and 'wc-variation-selection-needed' classes
        // plus disabled/aria-disabled attributes whenever they're added by external plugins
        var buttonObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (
                    mutation.type === "attributes" ||
                    (mutation.type === "childList" &&
                        mutation.target === $addToCartButton[0])
                ) {
                    // Check if the problematic classes or attributes were added
                    var hasDisabledClass =
                        $addToCartButton.hasClass("disabled");
                    var hasVariationClass = $addToCartButton.hasClass(
                        "wc-variation-selection-needed"
                    );
                    var isDisabledAttr = $addToCartButton.prop("disabled");
                    var hasAriaDisabled =
                        $addToCartButton.attr("aria-disabled");

                    // New persistent out-of-stock marker — if present we must NOT
                    // remove the disabled state or classes. Also respect loading state.
                    var hasOutOfStock =
                        $addToCartButton.hasClass("woo-outofstock");

                    // If any of the problematic attributes/classes are present, remove them
                    // (unless we're in a loading/submitting state where button should legitimately be disabled)
                    // Only strip third-party disabling if we're NOT in a
                    // plugin-induced loading state and the button is not
                    // intentionally out of stock for this site.
                    if (
                        !$addToCartButton.hasClass("woo-limit-loading") &&
                        !hasOutOfStock &&
                        (hasDisabledClass ||
                            hasVariationClass ||
                            isDisabledAttr ||
                            hasAriaDisabled)
                    ) {
                        $addToCartButton
                            .prop("disabled", false)
                            .removeAttr("disabled")
                            .removeAttr("aria-disabled")
                            .removeClass(
                                "disabled wc-variation-selection-needed"
                            );
                    }
                }
            });
        });

        // Start observing the button for attribute and class changes
        var observerConfig = {
            attributes: true,
            attributeFilter: ["disabled", "aria-disabled", "class"],
            subtree: false,
        };
        buttonObserver.observe($addToCartButton[0], observerConfig);

        // Handle variation selection for variable products
        if (isVariableProduct) {
            // Listen for WooCommerce variation events

            $form.on("found_variation", function (event, variation) {
                // Clear any pending timer
                var inputId = $limitedNumberInput.attr("id") || "default";
                if (window.IJWLP_Frontend_Common && window.IJWLP_Frontend_Common.checkTimers && window.IJWLP_Frontend_Common.checkTimers[inputId]) {
                    clearTimeout(window.IJWLP_Frontend_Common.checkTimers[inputId]);
                    delete window.IJWLP_Frontend_Common.checkTimers[inputId];
                }

                variationSelected = true;
                checkVariationSelected();
                updateFieldStates();
                $addToCartButton
                    .prop("disabled", false)
                    .removeAttr("disabled")
                    .removeAttr("aria-disabled")
                    .removeClass("disabled wc-variation-selection-needed")
                    .removeClass("woo-outofstock");
                $limitedNumberInput
                    .val("") // Clear input field when variation changes
                    .prop("disabled", false)
                    .removeClass("disabled")
                    .removeClass("woo-outofstock")
                    .removeClass("woo-limit-error")
                    .removeClass("woo-limit-available")
                    .removeClass("woo-limit-error-highlight")
                    .removeClass("woo-limit-loading");
                $addToCartButton.removeClass("woo-limit-loading");
                // Clear all messages when variation changes
                window.IJWLP_Frontend_Common.hideError($errorDiv);
                $errorDiv.hide();
                $selectionErrorDiv.find(".woo-limit-variation-error").remove();
                $selectionErrorDiv.hide();
            });

            $form.on("reset_data", function () {
                variationSelected = false;
                $limitedNumberInput
                    .val("")
                    .prop("disabled", true)
                    .addClass("disabled")
                    .removeClass("woo-outofstock");
                $addToCartButton
                    .prop("disabled", false)
                    .removeAttr("disabled")
                    .removeAttr("aria-disabled")
                    .removeClass("disabled wc-variation-selection-needed")
                    .removeClass("woo-outofstock");
                window.IJWLP_Frontend_Common.hideError();
            });

            // Also listen to variation select changes directly
            $(".variations select").on("change", function () {
                // Clear any pending timer
                var inputId = $limitedNumberInput.attr("id") || "default";
                if (window.IJWLP_Frontend_Common && window.IJWLP_Frontend_Common.checkTimers && window.IJWLP_Frontend_Common.checkTimers[inputId]) {
                    clearTimeout(window.IJWLP_Frontend_Common.checkTimers[inputId]);
                    delete window.IJWLP_Frontend_Common.checkTimers[inputId];
                }

                // Clear input field and messages immediately when variation changes
                $limitedNumberInput
                    .val("")
                    .removeClass("woo-limit-error")
                    .removeClass("woo-limit-available")
                    .removeClass("woo-limit-error-highlight")
                    .removeClass("woo-limit-loading");
                $addToCartButton.removeClass("woo-limit-loading");
                window.IJWLP_Frontend_Common.hideError($errorDiv);
                $errorDiv.hide();
                $selectionErrorDiv.find(".woo-limit-variation-error").remove();
                $selectionErrorDiv.hide();

                // Small delay to let WooCommerce process the variation
                setTimeout(function () {
                    checkVariationSelected();
                    // Check stock for selected variation
                    var currentStock = getCurrentVariationStock();
                    if (currentStock <= 0) {
                        // Handle out-of-stock state centrally
                        window.IJWLP_Frontend_Common.handleOutOfStock(
                            isVariableProduct,
                            $addToCartButton,
                            $limitedNumberInput,
                            $errorDiv
                        );
                    }
                }, 100);
            });
        }

        // Update field states based on current conditions
        function updateFieldStates() {
            if (!isVariableProduct || variationSelected) {
                // Enable number field if not variable product or variation is selected
                $limitedNumberInput
                    .prop("disabled", false)
                    .removeClass("disabled")
                    .removeClass("woo-outofstock");
                // Only update visuals, do not disable button
                checkAddToCartState();
            } else {
                // Disable number field if variation not selected
                $limitedNumberInput.prop("disabled", true).addClass("disabled");
                // Do not disable add to cart button
            }
        }

        // Check if add to cart button should be enabled
        // Only enable when the number has been positively verified as available
        function checkAddToCartState() {
            // Only update visuals, do not disable button
            if (!$limitedNumberInput.length) {
                return;
            }
            var hasAvailableClass = $limitedNumberInput.hasClass(
                "woo-limit-available"
            );
            // No button disabling here
            if (hasAvailableClass) {
                window.IJWLP_Frontend_Common.hideError();
            }
        }

        // Setup number validation with debounce
        if ($limitedNumberInput.length && $errorDiv.length) {
            window.IJWLP_Frontend_Common.setupNumberValidation({
                $input: $limitedNumberInput,
                $button: $addToCartButton,
                $errorDiv: $errorDiv,
                delay: 2000, // 2 seconds
                getProductId: function () {
                    return (
                        $form.find('input[name="add-to-cart"]').val() ||
                        $form.find('input[name="product_id"]').val()
                    );
                },
                getVariationId: function () {
                    return $form.find('input[name="variation_id"]').val() || 0;
                },
                getCurrentStock: function () {
                    return getCurrentVariationStock();
                },
            });
        }

        // Mirror availability class from input to add-to-cart button so we can
        // gate adding to cart by presence of a class instead of using disabled props.
        if ($limitedNumberInput.length) {
            try {
                var inputObserver = new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (
                            mutation.type === "attributes" &&
                            mutation.attributeName === "class"
                        ) {
                            var hasAvailable = $limitedNumberInput.hasClass(
                                "woo-limit-available"
                            );
                            if (hasAvailable) {
                                $addToCartButton.addClass(
                                    "woo-limit-available"
                                );
                            } else {
                                $addToCartButton.removeClass(
                                    "woo-limit-available"
                                );
                            }
                        }
                    });
                });

                inputObserver.observe($limitedNumberInput[0], {
                    attributes: true,
                    attributeFilter: ["class"],
                    subtree: false,
                });

                // Initialize state
                if ($limitedNumberInput.hasClass("woo-limit-available")) {
                    $addToCartButton.addClass("woo-limit-available");
                } else {
                    $addToCartButton.removeClass("woo-limit-available");
                }
            } catch (e) {
                // If observation fails for some reason, fail silently and rely on
                // existing behavior — this is a non-fatal enhancement.
            }
        }

        // Enable/disable add to cart button based on input (basic validation)
        $limitedNumberInput.on("input change blur", function () {
            var value = $(this).val().trim();
            // Do not disable add to cart button, just show error if needed
        });

        // Function to submit add to cart form
        function submitAddToCartForm() {
            // Prevent submission if this is a limited product and availability
            // has not been confirmed via the 'woo-limit-available' class.
            // We intentionally avoid toggling disabled props — we rely on class
            // gating as requested.
            if (
                isLimitedProduct &&
                !$addToCartButton.hasClass("woo-limit-available")
            ) {
                window.IJWLP_Frontend_Common.showError(
                    "Please verify the limited edition number availability before adding to cart.",
                    $errorDiv
                );
                return false;
            }
            // Get form data
            var formData = $form.serializeArray();
            var productId =
                $form.find('input[name="add-to-cart"]').val() ||
                $form.find('input[name="product_id"]').val();
            var variationId =
                $form.find('input[name="variation_id"]').val() || 0;
            var quantity = $form.find('input[name="quantity"]').val() || 1;
            var value = $limitedNumberInput.val().trim();

            // Disable button and show loading state
            $addToCartButton
                .prop("disabled", true)
                .addClass("woo-limit-loading");
            var originalText = $addToCartButton.text();
            $addToCartButton.text("Adding...");

            // Disable swatches and variations to prevent changes during submission
            $(".variations select").prop("disabled", true);
            $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("disabled");

            // AJAX request
            var wasSuccessful = false;
            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: {
                    action: "ijwlp_add_to_cart",
                    nonce: ijwlp_frontend.nonce,
                    product_id: productId,
                    variation_id: variationId,
                    quantity: quantity,
                    woo_limit: value,
                },
                success: function (response) {
                    if (response.success) {
                        wasSuccessful = true;

                        // Update available numbers list and refresh suggestions
                        var $availableInput = $(".woo-limit-available-numbers");
                        if ($availableInput.length && window.IJWLP_Frontend_Common) {
                            var $wrapper = $limitedNumberInput.closest(
                                ".woo-limit-field-wrapper"
                            );
                            var currentAvailable =
                                window.IJWLP_Frontend_Common.getAvailableNumbers(
                                    $wrapper
                                );
                            var addedNumber = String(value).trim();
                            var newAvailable = currentAvailable.filter(
                                function (n) {
                                    return String(n).trim() !== addedNumber;
                                }
                            );
                            $availableInput.val(JSON.stringify(newAvailable));

                            // Refresh suggestions for the input
                            window.IJWLP_Frontend_Common.refreshAutocomplete(
                                $limitedNumberInput
                            );
                        }

                        // Update cart fragments
                        if (response.data.fragments) {
                            $.each(
                                response.data.fragments,
                                function (key, value) {
                                    $(key).replaceWith(value);
                                }
                            );
                        }

                        // Trigger cart updated event
                        $(document.body).trigger("added_to_cart", [
                            response.data.fragments,
                            response.data.cart_hash,
                            $addToCartButton,
                        ]);

                        // TIMER: Restart countdown when limited product is added from product page
                        var limitTime =
                            window.ijwlp_limit_time ||
                            parseInt(
                                $("[data-limit-time]").attr("data-limit-time")
                            ) ||
                            15;
                        if (
                            window.ijwlpTimer &&
                            typeof window.ijwlpTimer.restartTimer === "function"
                        ) {
                            window.ijwlpTimer.restartTimer(limitTime);
                        }

                        // Hide ALL messages immediately (checking, lucky, etc)
                        window.IJWLP_Frontend_Common.hideError($errorDiv);
                        $errorDiv.hide();

                        $addToCartButton.text("Added");

                        setTimeout(function () {
                            $addToCartButton.text(originalText);
                        }, 2000);

                        // Reset form and clear input
                        $limitedNumberInput
                            .val("")
                            .removeClass("woo-limit-error")
                            .removeClass("woo-limit-available");

                        // Hide and dispose current autocomplete instance
                        var ac = $limitedNumberInput.data("autocomplete");
                        if (ac) {
                            ac.hide();
                            ac.dispose();
                        }
                        // Clear autocomplete data and flags so it can be reinitialized
                        $limitedNumberInput.removeData("autocomplete");
                        $limitedNumberInput.removeData("ac-initialized");
                        // Remove the autocomplete box from DOM
                        var $wrapper = $limitedNumberInput.closest(".woo-limit-field-wrapper");
                        $wrapper.find(".woo-limit-autocomplete-box").remove();

                        // Re-attach autocomplete so it will work when user starts typing again
                        if (window.IJWLP_Frontend_Common && typeof window.IJWLP_Frontend_Common.attachAutocomplete === "function") {
                            setTimeout(function () {
                                window.IJWLP_Frontend_Common.attachAutocomplete($limitedNumberInput);
                            }, 100);
                        }

                        $addToCartButton
                            .prop("disabled", true)
                            .addClass("disabled woo-limit-loading");

                        // Update stock quantities
                        if (
                            variationId &&
                            variationStockQuantities[variationId] !== undefined
                        ) {
                            // Reduce variation-specific stock
                            if (variationStockQuantities[variationId] !== null) {
                                variationStockQuantities[variationId] = Math.max(
                                    0,
                                    variationStockQuantities[variationId] - 1
                                );
                                // If this variation is now out of stock, mark persistent state
                                if (variationStockQuantities[variationId] <= 0) {
                                    $addToCartButton
                                        .prop("disabled", true)
                                        .addClass("disabled")
                                        .addClass("woo-outofstock");
                                    $limitedNumberInput
                                        .prop("disabled", true)
                                        .addClass("disabled")
                                        .addClass("woo-outofstock");

                                    // Disable quantity input
                                    $form.find('input[name="quantity"]').prop("disabled", true);

                                    // Check if ALL variations are now out of stock
                                    if (areAllVariationsOutOfStock()) {
                                        // Disable all variation swatches
                                        $(".variations select").prop("disabled", true);
                                        $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("disabled");
                                    } else {
                                        // Only disable the specific variation swatch
                                        disableVariationSwatch(variationId);
                                    }

                                    // Show error message
                                    window.IJWLP_Frontend_Common.showError(
                                        "This variation is now out of stock.",
                                        $errorDiv
                                    );
                                } else {
                                    // Clear out-of-stock marker if stock remains
                                    $addToCartButton.removeClass("woo-outofstock");
                                    $limitedNumberInput.removeClass(
                                        "woo-outofstock"
                                    );
                                }
                            }
                        } else {
                            // Reduce main product stock
                            if (stockQuantityRemaining !== Infinity) {
                                stockQuantityRemaining = Math.max(
                                    0,
                                    stockQuantityRemaining - 1
                                );
                                if (stockQuantityRemaining <= 0) {
                                    $addToCartButton
                                        .prop("disabled", true)
                                        .addClass("disabled")
                                        .addClass("woo-outofstock");
                                    $limitedNumberInput
                                        .prop("disabled", true)
                                        .addClass("disabled")
                                        .addClass("woo-outofstock");

                                    // Disable quantity input
                                    $form.find('input[name="quantity"]').prop("disabled", true);

                                    // Show error message
                                    window.IJWLP_Frontend_Common.showError(
                                        "This product is now out of stock.",
                                        $errorDiv
                                    );
                                } else {
                                    $addToCartButton.removeClass("woo-outofstock");
                                    $limitedNumberInput.removeClass(
                                        "woo-outofstock"
                                    );
                                }
                            }
                        }

                        // Reduce user limit remaining
                        if (userLimitRemaining !== Infinity) {
                            userLimitRemaining = Math.max(0, userLimitRemaining - parseInt(quantity));

                            // Update hidden field
                            $(".woo-limit-user-remaining").val(userLimitRemaining);

                            if (userLimitRemaining <= 0) {
                                $addToCartButton
                                    .prop("disabled", true)
                                    .addClass("disabled")
                                    .addClass("woo-outofstock"); // Reuse outofstock class for simplicity in complete callback
                                $limitedNumberInput
                                    .prop("disabled", true)
                                    .addClass("disabled")
                                    .addClass("woo-outofstock");

                                // Disable quantity input
                                $form.find('input[name="quantity"]').prop("disabled", true);

                                // Disable variation swatches
                                $(".variations select").prop("disabled", true);
                                $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("disabled");
                                $(".reset_variations").removeClass("show");

                                // Show error message
                                var productName = $(".woo-limit-product-name").val() || "this product";

                                window.IJWLP_Frontend_Common.showError(
                                    "Max quantity for " + productName + " reached (" + userLimitVal + ")",
                                    $errorDiv
                                );
                            }
                        }

                        // Update the hidden fields with new stock values
                        $(".woo-limit-stock-quantity").val(
                            stockQuantityRemaining
                        );
                        $(".woo-limit-variation-quantities").val(
                            JSON.stringify(variationStockQuantities)
                        );

                        // Only reset swatches if we are NOT out of stock/limit reached
                        if (!$addToCartButton.hasClass("woo-outofstock")) {
                            $(".rtwpvs-wc-select").val("").trigger("change");
                        }

                        // Show success notice
                        if (typeof wc_add_to_cart_params !== "undefined") {
                            $(document.body).trigger("wc_fragment_refresh");
                        }

                    } else {
                        window.IJWLP_Frontend_Common.showError(
                            response.data.message ||
                            "Failed to add product to cart.",
                            $errorDiv
                        );
                    }
                },
                error: function (xhr, status, error) {
                    window.IJWLP_Frontend_Common.showError(
                        "An error occurred. Please try again.",
                        $errorDiv
                    );
                },
                complete: function () {
                    // Only reset the button state immediately if the request was NOT successful
                    // If successful, the success callback already handles the "Added" text for 2 seconds
                    if (!wasSuccessful) {
                        $addToCartButton.text(originalText);
                    }

                    // Check if we are out of stock before re-enabling
                    var isOutOfStock = $addToCartButton.hasClass("woo-outofstock");

                    if (!isOutOfStock) {
                        $addToCartButton
                            .prop("disabled", false)
                            .removeAttr("disabled")
                            .removeAttr("aria-disabled")
                            .removeClass(
                                "woo-limit-loading disabled wc-variation-selection-needed"
                            );

                        // Re-enable swatches and variations
                        $(".variations select").prop("disabled", false);
                        $(".rtwpvs-terms-wrapper .rtwpvs-term").removeClass("disabled");

                        // Re-apply disabled state to out-of-stock variations
                        initializeOutOfStockSwatches();

                        // Re-enable quantity input
                        $form.find('input[name="quantity"]').prop("disabled", false);

                        checkAddToCartState();
                    } else {
                        // Ensure loading class is removed even if out of stock
                        $addToCartButton.removeClass("woo-limit-loading");

                        // Even when current variation is out of stock, re-enable swatches
                        // for OTHER variations that are still in stock
                        $(".variations select").prop("disabled", false);
                        $(".rtwpvs-terms-wrapper .rtwpvs-term").removeClass("disabled");

                        // Re-apply disabled state only to out-of-stock variations
                        initializeOutOfStockSwatches();
                    }
                },
            });
        }

        // Handle form submit with AJAX for both limited and normal products
        $form.on("submit", function (e) {
            e.preventDefault();

            // Check variation selection for variable products

            if (isVariableProduct && !variationSelected) {
                // Find the first unselected variation
                var $unselected = null;
                var labelText = "";
                $(".variations select").each(function () {
                    if (!$(this).val() || $(this).val() === "") {
                        $unselected = $(this);
                        // Find label from parent tr > th > label
                        var $tr = $(this).closest("tr");
                        var $label = $tr.find("th label");
                        labelText = $label.length ? $label.text().trim().toLowerCase() : "";
                        return false;
                    }
                });
                if ($unselected) {
                    // Remove any previous error
                    $selectionErrorDiv
                        .find(".woo-limit-variation-error")
                        .remove();
                    // Insert error into message div and ensure it's visible
                    var errorMsg =
                        "Please select a " +
                        (labelText ? labelText : "a variation");
                    // Show error message instead of disabling button
                    $selectionErrorDiv
                        .append(
                            '<div class="woo-limit-variation-error">' +
                            errorMsg +
                            "</div>"
                        )
                        .slideDown();
                    return false;
                }
            }

            var productId =
                $form.find('input[name="add-to-cart"]').val() ||
                $form.find('input[name="product_id"]').val();
            var variationId =
                $form.find('input[name="variation_id"]').val() || 0;
            var quantity = $form.find('input[name="quantity"]').val() || 1;

            // Quantity validation (must be positive integer)
            if (!/^[1-9]\d*$/.test(quantity)) {
                window.IJWLP_Frontend_Common.showError(
                    "Please enter a valid quantity.",
                    $errorDiv
                );
                $form.find('input[name="quantity"]').focus();
                return false;
            }

            // Limited product logic
            if (isLimitedProduct) {

                var value = $limitedNumberInput.val().trim();
                if (value === "") {
                    // Highlight error after variation is selected
                    $limitedNumberInput.addClass("woo-limit-error-highlight");
                    // Remove any previous error
                    $('.woo-number-range').addClass('woo-limit-error');
                    $limitedNumberInput.focus();
                    return false;
                } else {
                    $('.woo-number-range').removeClass(
                        "woo-limit-error"
                    );
                }
                // Basic format validation
                if (!window.IJWLP_Frontend_Common.validateNumberFormat(value)) {
                    window.IJWLP_Frontend_Common.showError(
                        "Please enter a valid number.",
                        $errorDiv
                    );
                    return false;
                }


                if ($addToCartButton.hasClass("woo-limit-loading") || !$addToCartButton.hasClass("woo-limit-available")) {
                    return false;
                }

                // Clear any pending timer
                var inputId = $limitedNumberInput.attr("id") || "default";
                if (window.IJWLP_Frontend_Common.checkTimers[inputId]) {
                    clearTimeout(
                        window.IJWLP_Frontend_Common.checkTimers[inputId]
                    );
                    delete window.IJWLP_Frontend_Common.checkTimers[inputId];
                }
                // Check availability immediately before submitting
                window.IJWLP_Frontend_Common.checkNumberAvailability({
                    number: value,
                    productId: productId,
                    variationId: variationId,
                    silent: true,
                    cartItemKey: "",
                    $input: $limitedNumberInput,
                    $button: $addToCartButton,
                    $errorDiv: $errorDiv,
                    // Before starting the async check, mark the button as checking
                    // and remove any previous available marker so that any premature
                    // calls to submission will be blocked by submitAddToCartForm().
                    onStart: function () {
                        $addToCartButton
                            .addClass("woo-limit-checking")
                            .removeClass("woo-limit-available");
                        $('.rtwpvs-terms-wrapper .rtwpvs-term').addClass('disabled');
                    },
                    onComplete: function (data) {
                        $('.rtwpvs-terms-wrapper .rtwpvs-term').removeClass('disabled');
                        if (data.available) {
                            // Mark button as available (used as our gating mechanism)
                            $addToCartButton
                                .addClass("woo-limit-available")
                                .removeClass("woo-limit-checking");
                            // Hide the "You are lucky" message immediately and submit
                            window.IJWLP_Frontend_Common.hideError($errorDiv);
                            // Submit the form
                            submitAddToCartForm();
                        } else {
                            // Error already shown by checkNumberAvailability. Ensure button
                            // is not marked available and remove checking state.
                            $addToCartButton
                                .removeClass("woo-limit-available")
                                .removeClass("woo-limit-checking");
                            $addToCartButton.prop("disabled", false);
                        }
                    },
                });
            } else {
                // Normal product: submit via AJAX

                // Disable button and show loading state
                $addToCartButton
                    .prop("disabled", true)
                    .addClass("woo-limit-loading");
                var originalText = $addToCartButton.text();
                $addToCartButton.text("Adding...");
			

                var wasSuccessful = false;
                $.ajax({
                    url: ijwlp_frontend.ajax_url,
                    type: "POST",
                    data: {
                        action: "ijwlp_add_to_cart",
                        nonce: ijwlp_frontend.nonce,
                        product_id: productId,
                        variation_id: variationId,
                        quantity: quantity,
                    },
                    success: function (response) {
                        if (response.success) {
                            wasSuccessful = true;
                            if (response.data.fragments) {
                                $.each(
                                    response.data.fragments,
                                    function (key, value) {
                                        $(key).replaceWith(value);
                                    }
                                );
                            }

                            $addToCartButton.text("Added");
							
                            setTimeout(function () {
                                $addToCartButton.text(originalText);
                            }, 2000);

                            $(document.body).trigger("added_to_cart", [
                                response.data.fragments,
                                response.data.cart_hash,
                                $addToCartButton,
                            ]);
                            window.IJWLP_Frontend_Common.hideError($errorDiv);
                            $addToCartButton
                                .prop("disabled", true)
                                .addClass("disabled woo-limit-loading");

                            // Update user limit remaining locally
                            var enteredQuantity = parseInt(quantity);
                            if (userLimitRemaining !== Infinity) {
                                userLimitRemaining = Math.max(0, userLimitRemaining - enteredQuantity);
                                $(".woo-limit-user-remaining").val(userLimitRemaining);

                                if (userLimitRemaining <= 0) {
                                    $addToCartButton
                                        .prop("disabled", true)
                                        .addClass("disabled")
                                        .addClass("woo-outofstock"); // Reuse outofstock class for simplicity

                                    // Disable quantity input
                                    $form.find('input[name="quantity"]').prop("disabled", true);

                                    // Disable variation swatches
                                    $(".variations select").prop("disabled", true);
                                    $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("disabled");
                                    $(".reset_variations").removeClass("show");

                                    // Show error message
                                    var productName = $(".woo-limit-product-name").val() || "this product";
                                    window.IJWLP_Frontend_Common.showError(
                                        "Max quantity for " + productName + " reached (" + userLimitVal + ")",
                                        $errorDiv
                                    );
                                }
                            }

                            // Update stock locally
                            if (variationId && variationStockQuantities[variationId] !== undefined) {
                                if (variationStockQuantities[variationId] !== null) {
                                    variationStockQuantities[variationId] = Math.max(0, variationStockQuantities[variationId] - enteredQuantity);
                                    if (variationStockQuantities[variationId] <= 0) {
                                        // If this variation is now out of stock, mark persistent state
                                        $addToCartButton
                                            .prop("disabled", true)
                                            .addClass("disabled")
                                            .addClass("woo-outofstock");

                                        // Disable quantity input
                                        $form.find('input[name="quantity"]').prop("disabled", true);

                                        // Check if ALL variations are now out of stock
                                        if (areAllVariationsOutOfStock()) {
                                            // Disable all variation swatches
                                            $(".variations select").prop("disabled", true);
                                            $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("disabled");
                                        } else {
                                            // Only disable the specific variation swatch
                                            disableVariationSwatch(variationId);
                                        }

                                        // Show error message
                                        window.IJWLP_Frontend_Common.showError(
                                            "This variation is now out of stock.",
                                            $errorDiv
                                        );
                                    }
                                }
                            } else if (stockQuantityRemaining !== Infinity) {
                                stockQuantityRemaining = Math.max(0, stockQuantityRemaining - enteredQuantity);
                                if (stockQuantityRemaining <= 0) {
                                    $addToCartButton
                                        .prop("disabled", true)
                                        .addClass("disabled")
                                        .addClass("woo-outofstock");

                                    // Disable quantity input
                                    $form.find('input[name="quantity"]').prop("disabled", true);

                                    // Show error message
                                    window.IJWLP_Frontend_Common.showError(
                                        "This product is now out of stock.",
                                        $errorDiv
                                    );
                                }
                            }

                            if (typeof wc_add_to_cart_params !== "undefined") {
                                $(document.body).trigger("wc_fragment_refresh");
                            }

							// Only reset swatches if we are NOT out of stock/limit reached
							if (!$addToCartButton.hasClass("woo-outofstock")) {
								$(".rtwpvs-wc-select").val("").trigger("change");
							}

						} else {
                            window.IJWLP_Frontend_Common.showError(
                                response.data.message ||
                                "Failed to add product to cart.",
                                $errorDiv
                            );
                        }
                    },
                    error: function (xhr, status, error) {
                        window.IJWLP_Frontend_Common.showError(
                            "An error occurred. Please try again.",
                            $errorDiv
                        );
                    },
                    complete: function () {
                        if (!wasSuccessful) {
                            $addToCartButton.text(originalText);
                        }
                        // Check if we are out of stock/limit reached before re-enabling
                        var isOutOfStock = $addToCartButton.hasClass("woo-outofstock");

                        if (!isOutOfStock) {
                            $addToCartButton
                                .prop("disabled", false)
                                .removeAttr("disabled")
                                .removeAttr("aria-disabled")
                                .removeClass("woo-limit-loading");
                            checkAddToCartState();
                        } else {
                            $addToCartButton
                                .removeClass("woo-limit-loading");
                        }
                    },
                });
            }
            return false;
        });
    });
})(jQuery);
