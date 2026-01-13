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
        // ========================================
        // DOM Element References
        // ========================================
        var $limitedNumberInput = $(".woo-limit");
        var $addToCartButton = $(
            'button.single_add_to_cart_button, button[name="add-to-cart"]'
        );
        var $form = $("form.cart");
        var $errorDiv = $(".woo-limit-message");
        var $selectionErrorDiv = $(".woo-limit-selection-message");

        // ========================================
        // State Variables
        // ========================================
        var isVariableProduct =
            $form.hasClass("variations_form") ||
            $(".variations select").length > 0;
        var variationSelected = false;
        var isLimitedProduct = $limitedNumberInput.length > 0;

        // Stock quantities
        var stockVal = $(".woo-limit-stock-quantity").val();
        var stockQuantityRemaining =
            stockVal === "" ? Infinity : parseInt(stockVal);
        var variationStockQuantities = {};

        // Parse variation stock quantities
        var variationStockJson = $(".woo-limit-variation-quantities").val();
        if (variationStockJson) {
            try {
                variationStockQuantities = JSON.parse(variationStockJson);
            } catch (e) {
                variationStockQuantities = {};
            }
        }

        // User limit
        var userLimitVal = $(".woo-limit-user-remaining").val();
        var userLimitRemaining = userLimitVal === "" ? Infinity : parseInt(userLimitVal);

        // Original max quantity setting (for error messages)
        var maxQuantitySetting = $(".woo-limit-max-quantity").val();

        // Flag to prevent MutationObserver feedback loop when we're intentionally changing button state
        var isSettingButtonState = false;

        // ========================================
        // Helper Functions - DRY Utilities
        // ========================================

        /**
         * Enable the add-to-cart button and remove all disabling attributes
         */
        function enableButton() {
            isSettingButtonState = true;
            $addToCartButton
                .prop("disabled", false)
                .removeAttr("disabled")
                .removeAttr("aria-disabled")
                .removeClass("disabled wc-variation-selection-needed woo-outofstock");
            isSettingButtonState = false;
        }

        /**
         * Check if product is fully unavailable (user limit reached, all variants OOS, stock out, or no available numbers)
         * This is the central check for whether the product can be purchased at all.
         * @returns {boolean}
         */
        function isProductFullyUnavailable() {
            // User limit reached
            if (userLimitRemaining <= 0) {
                return true;
            }
            // Limited product: check if available count is zero (all numbers sold out)
            if (isLimitedProduct) {
                var availableCountVal = $(".woo-limit-available-count").val();
                var availableCount = availableCountVal === "" ? Infinity : parseInt(availableCountVal);
                if (availableCount <= 0) {
                    return true;
                }
            }
            // Variable product: all variations out of stock
            if (isVariableProduct && areAllVariationsOutOfStock()) {
                return true;
            }
            // Simple product: stock out
            if (!isVariableProduct && stockQuantityRemaining <= 0) {
                return true;
            }
            return false;
        }

        /**
         * Check if button should remain disabled (loading, unavailable, or just added)
         */
        function shouldButtonRemainDisabled() {
            return $addToCartButton.hasClass("woo-limit-loading") ||
                $addToCartButton.hasClass("woo-outofstock") ||
                $addToCartButton.hasClass("woo-limit-added") ||
                isProductFullyUnavailable();
        }

        /**
         * Safely enable the button only if not in a loading/out-of-stock state
         */
        function safeEnableButton() {
            if (!shouldButtonRemainDisabled()) {
                enableButton();
            }
        }

        /**
         * Set button and input to out-of-stock state
         */
        function setOutOfStockState() {
            isSettingButtonState = true;
            $addToCartButton
                .prop("disabled", true)
                .addClass("disabled woo-outofstock");
            $limitedNumberInput
                .prop("disabled", true)
                .addClass("disabled woo-outofstock");
            $form.find('input[name="quantity"]').prop("disabled", true);
            isSettingButtonState = false;
        }

        /**
         * Clear out-of-stock state from button and input
         */
        function clearOutOfStockState() {
            $addToCartButton.removeClass("woo-outofstock");
            $limitedNumberInput.removeClass("woo-outofstock");
        }

        /**
         * Set button to loading state
         * @param {string} loadingText - Text to show while loading
         * @returns {string} Original button text for restoration
         */
        function setButtonLoading(loadingText) {
            var originalText = $addToCartButton.text();
            $addToCartButton
                .prop("disabled", true)
                .addClass("woo-limit-loading")
                .text(loadingText || "Adding...");
            return originalText;
        }

        /**
         * Clear button loading state
         * @param {string} originalText - Text to restore (optional)
         */
        function clearButtonLoading(originalText) {
            $addToCartButton.removeClass("woo-limit-loading");
            if (originalText) {
                $addToCartButton.text(originalText);
            }
        }

        /**
         * Clear all swatch selections (reset to unselected state)
         * @param {boolean} silent - If true, do not trigger change events
         */
        function clearAllSwatchSelections(silent) {
            // Clear native select dropdowns
            var $selects = $(".variations select").val("");
            if (!silent) {
                $selects.trigger("change");
            }

            // Clear RTWPVS swatches selection
            $(".rtwpvs-terms-wrapper .rtwpvs-term").removeClass("selected");
            var $rtwpvsSelects = $(".rtwpvs-wc-select").val("");
            if (!silent) {
                $rtwpvsSelects.trigger("change");
            }

            // Clear variation ID
            $('input[name="variation_id"]').val("");
            variationSelected = false;
        }

        /**
         * Disable all variation swatches and selects
         * @param {boolean} clearSelections - Whether to also clear selections (default: false)
         * @param {boolean} silent - If true, do not trigger change events when clearing
         */
        function disableAllSwatches(clearSelections, silent) {
            if (clearSelections) {
                clearAllSwatchSelections(silent);
            }
            $(".variations select").prop("disabled", true);
            $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("disabled");
        }

        /**
         * Enable all variation swatches and selects (removes disabled class only)
         */
        function enableAllSwatches() {
            $(".variations select").prop("disabled", false);
            $(".rtwpvs-terms-wrapper .rtwpvs-term").removeClass("disabled");
        }

        /**
         * Set swatches to non-selectable state during AJAX operations
         * This is different from disabled - used for temporary loading states
         */
        function setSwatchesNonSelectable() {
            $(".variations select").prop("disabled", true);
            $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("non-selectable");
        }

        /**
         * Clear non-selectable state from swatches after AJAX operations complete
         */
        function clearSwatchesNonSelectable() {
            $(".variations select").prop("disabled", false);
            $(".rtwpvs-terms-wrapper .rtwpvs-term").removeClass("non-selectable");
        }

        /**
         * Clear all styling classes from the limited number input
         */
        function clearInputClasses() {
            $limitedNumberInput
                .removeClass("woo-limit-error woo-limit-available woo-limit-error-highlight woo-limit-loading");
            $addToCartButton.removeClass("woo-limit-loading");
            $(".woo-number-range").removeClass("woo-limit-error");
        }

        /**
         * Clear all error/info messages
         */
        function clearAllMessages() {
            window.IJWLP_Frontend_Common.hideError($errorDiv);
            $errorDiv.hide();
            $selectionErrorDiv.find(".woo-limit-variation-error").remove();
            $selectionErrorDiv.hide();
        }

        /**
         * Clear any pending availability check timer
         */
        function clearPendingTimer() {
            var inputId = $limitedNumberInput.attr("id") || "default";
            if (window.IJWLP_Frontend_Common &&
                window.IJWLP_Frontend_Common.checkTimers &&
                window.IJWLP_Frontend_Common.checkTimers[inputId]) {
                clearTimeout(window.IJWLP_Frontend_Common.checkTimers[inputId]);
                delete window.IJWLP_Frontend_Common.checkTimers[inputId];
            }
        }

        /**
         * Get product and variation IDs from the form
         * @returns {Object} { productId, variationId }
         */
        function getProductIds() {
            return {
                productId: $form.find('input[name="add-to-cart"]').val() ||
                    $form.find('input[name="product_id"]').val() ||
                    $form.find('button[name="add-to-cart"]').val() ||
                    $form.data('product_id'),
                variationId: $form.find('input[name="variation_id"]').val() || 0
            };
        }

        /**
         * Get quantity from the form
         * @returns {number}
         */
        function getQuantity() {
            return parseInt($form.find('input[name="quantity"]').val()) || 1;
        }

        /**
         * Get current variation stock (or main product stock if simple)
         * @returns {number}
         */
        function getCurrentVariationStock() {
            var variationId = $form.find('input[name="variation_id"]').val() || 0;
            if (variationId && variationStockQuantities[variationId] !== undefined) {
                return variationStockQuantities[variationId] === null
                    ? Infinity
                    : variationStockQuantities[variationId];
            }
            return stockQuantityRemaining;
        }

        /**
         * Update hidden fields with current stock values
         */
        function updateStockHiddenFields() {
            $(".woo-limit-stock-quantity").val(stockQuantityRemaining);
            $(".woo-limit-variation-quantities").val(JSON.stringify(variationStockQuantities));
        }

        /**
         * Show user limit reached message
         */
        function showUserLimitReachedMessage() {
            var productName = $(".woo-limit-product-name").val() || "this product";
            window.IJWLP_Frontend_Common.showError(
                "Max quantity for " + productName + " reached (" + maxQuantitySetting + ")",
                $errorDiv
            );
        }

        /**
         * Handle user limit reached state
         * Note: Does NOT add 'disabled' class to swatches - that's reserved for out-of-stock only
         */
        function handleUserLimitReached() {
            setOutOfStockState();
            if (isVariableProduct) {
                // Clear selections and disable selects, but don't add 'disabled' class to swatches
                clearAllSwatchSelections(true); // Silent mode to prevent recursion
                $(".variations select").prop("disabled", true);
                $(".rtwpvs-terms-wrapper .rtwpvs-term").addClass("non-selectable");
                $(".reset_variations").removeClass("show");
            }
            showUserLimitReachedMessage();
        }

        /**
         * Handle all variants out of stock state (for variable products)
         */
        function handleAllVariantsOutOfStock() {
            setOutOfStockState();
            disableAllSwatches(true, true); // Silent mode to prevent recursion
            $(".reset_variations").removeClass("show");
            window.IJWLP_Frontend_Common.showError(
                "All variations are out of stock.", $errorDiv
            );
        }

        /**
         * Apply disabled state based on current product availability.
         * This is the central handler for applying disabled states - use this instead of 
         * calling individual handlers to ensure consistency.
         * @returns {boolean} True if product is unavailable and state was applied
         */
        function applyFullyUnavailableState() {
            if (userLimitRemaining <= 0) {
                handleUserLimitReached();
                return true;
            }
            if (isVariableProduct && areAllVariationsOutOfStock()) {
                handleAllVariantsOutOfStock();
                return true;
            }
            if (!isVariableProduct && stockQuantityRemaining <= 0) {
                window.IJWLP_Frontend_Common.handleOutOfStock(
                    false, $addToCartButton, $limitedNumberInput, $errorDiv
                );
                return true;
            }
            return false;
        }

        // ========================================
        // Variation Helpers
        // ========================================

        /**
         * Get variation attributes from variation ID
         */
        function getVariationAttributes(variationId) {
            if ($form.length && $form.data('product_variations')) {
                var variations = $form.data('product_variations');
                for (var i = 0; i < variations.length; i++) {
                    if (variations[i].variation_id == variationId) {
                        return variations[i].attributes;
                    }
                }
            }
            return null;
        }

        /**
         * Check if attribute value is only used by out-of-stock variations
         */
        function isOnlyVariationForAttributeOutOfStock(attrName, attrValue, currentVariationId) {
            if (!$form.length || !$form.data('product_variations')) {
                return true;
            }

            var variations = $form.data('product_variations');
            for (var i = 0; i < variations.length; i++) {
                var variation = variations[i];
                var varAttrs = variation.attributes;

                if (varAttrs && varAttrs[attrName] &&
                    (String(varAttrs[attrName]).toLowerCase() === String(attrValue).toLowerCase() ||
                        varAttrs[attrName] === '')) {

                    if (variation.variation_id != currentVariationId) {
                        var varStock = variationStockQuantities[variation.variation_id];
                        if (varStock === null || varStock === undefined || varStock > 0) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }

        /**
         * Check if all variations are out of stock
         */
        function areAllVariationsOutOfStock() {
            if (!variationStockQuantities || Object.keys(variationStockQuantities).length === 0) {
                return false;
            }

            for (var varId in variationStockQuantities) {
                if (variationStockQuantities.hasOwnProperty(varId)) {
                    var stock = variationStockQuantities[varId];
                    if (stock === null || stock > 0) {
                        return false;
                    }
                }
            }
            return true;
        }

        /**
         * Disable a specific variation swatch
         */
        function disableVariationSwatch(variationId) {
            var attrs = getVariationAttributes(variationId);
            if (!attrs) return;

            $.each(attrs, function (attrName, attrValue) {
                if (!attrValue) return;

                if (!isOnlyVariationForAttributeOutOfStock(attrName, attrValue, variationId)) return;

                // Disable swatch
                $('.rtwpvs-terms-wrapper .rtwpvs-term').each(function () {
                    var $swatch = $(this);
                    var swatchValue = $swatch.data('value') || $swatch.data('term') || $swatch.attr('data-value');
                    if (swatchValue && String(swatchValue).toLowerCase() === String(attrValue).toLowerCase()) {
                        $swatch.addClass('disabled out-of-stock');
                    }
                });

                // Disable select option
                var selectName = attrName.replace('attribute_', '');
                $('.variations select').each(function () {
                    var $select = $(this);
                    var selectAttrName = $select.attr('name') || $select.attr('id') || $select.data('attribute_name');

                    if (selectAttrName && (selectAttrName === attrName ||
                        selectAttrName === selectName ||
                        selectAttrName.indexOf(selectName) !== -1)) {
                        $select.find('option').each(function () {
                            var $option = $(this);
                            if ($option.val() && String($option.val()).toLowerCase() === String(attrValue).toLowerCase()) {
                                $option.remove();
                            }
                        });
                    }
                });
            });
        }

        /**
         * Initialize out-of-stock swatches on page load
         * Note: Only disables swatches for stock issues, NOT for user limit reached.
         * User limit reached should NOT add 'disabled' class to swatches (per design).
         */
        function initializeOutOfStockSwatches() {
            if (!isVariableProduct) return;

            // If user limit is reached, do NOT disable swatches - just return
            // The handleUserLimitReached() function handles this case separately
            // and intentionally does not add 'disabled' class to swatches
            if (userLimitRemaining <= 0) {
                return;
            }

            // Only disable all swatches if ALL variations are out of stock
            if (areAllVariationsOutOfStock()) {
                disableAllSwatches();
                return;
            }

            // Disable specific out-of-stock variation swatches
            for (var varId in variationStockQuantities) {
                if (variationStockQuantities.hasOwnProperty(varId)) {
                    var stock = variationStockQuantities[varId];
                    if (stock !== null && stock <= 0) {
                        disableVariationSwatch(varId);
                    }
                }
            }
        }

        // ========================================
        // Stock Update Handlers
        // ========================================

        /**
         * Update stock after successful add-to-cart
         * @param {number|string} variationId
         * @param {number} quantity
         * @returns {boolean} Whether product is now out of stock
         */
        function updateStockAfterPurchase(variationId, quantity) {
            var isNowOutOfStock = false;

            if (variationId && variationStockQuantities[variationId] !== undefined) {
                if (variationStockQuantities[variationId] !== null) {
                    variationStockQuantities[variationId] = Math.max(
                        0, variationStockQuantities[variationId] - quantity
                    );

                    if (variationStockQuantities[variationId] <= 0) {
                        isNowOutOfStock = true;
                        setOutOfStockState();

                        if (areAllVariationsOutOfStock()) {
                            disableAllSwatches(true, true); // Clear selections silently when all variations out of stock
                        } else {
                            disableVariationSwatch(variationId);
                        }

                        window.IJWLP_Frontend_Common.showError(
                            "This variation is now out of stock.", $errorDiv
                        );
                    }
                }
            } else if (stockQuantityRemaining !== Infinity) {
                stockQuantityRemaining = Math.max(0, stockQuantityRemaining - quantity);

                if (stockQuantityRemaining <= 0) {
                    isNowOutOfStock = true;
                    setOutOfStockState();
                    window.IJWLP_Frontend_Common.showError(
                        "This product is now out of stock.", $errorDiv
                    );
                }
            }

            if (!isNowOutOfStock) {
                clearOutOfStockState();
            }

            updateStockHiddenFields();
            return isNowOutOfStock;
        }

        /**
         * Update user limit after successful add-to-cart
         * @param {number} quantity
         * @returns {boolean} Whether user limit is now reached
         */
        function updateUserLimitAfterPurchase(quantity) {
            if (userLimitRemaining === Infinity) return false;

            userLimitRemaining = Math.max(0, userLimitRemaining - quantity);
            $(".woo-limit-user-remaining").val(userLimitRemaining);

            if (userLimitRemaining <= 0) {
                handleUserLimitReached();
                return true;
            }
            return false;
        }

        // ========================================
        // Variation Selection Handling
        // ========================================

        function checkVariationSelected() {
            if (!isVariableProduct) {
                variationSelected = true;
                return;
            }

            var hasVariation = false;
            $(".variations select").each(function () {
                if ($(this).val() && $(this).val() !== "") {
                    hasVariation = true;
                    return false;
                }
            });

            var $variationId = $('input[name="variation_id"]');
            if ($variationId.length && $variationId.val() && $variationId.val() !== "") {
                $selectionErrorDiv.find(".woo-limit-variation-error").hide();
                $selectionErrorDiv.hide();
                hasVariation = true;

                if (isLimitedProduct && getCurrentVariationStock() <= 0) {
                    window.IJWLP_Frontend_Common.handleOutOfStock(
                        isVariableProduct, $addToCartButton, $limitedNumberInput, $errorDiv
                    );
                }
            }

            variationSelected = hasVariation;
            updateFieldStates();
        }

        function updateFieldStates() {
            // Don't modify field states if product is fully unavailable
            if (isProductFullyUnavailable()) {
                return;
            }

            if (!isVariableProduct || variationSelected) {
                $limitedNumberInput
                    .prop("disabled", false)
                    .removeClass("disabled woo-outofstock");
                checkAddToCartState();
            } else {
                $limitedNumberInput.prop("disabled", true).addClass("disabled");
            }
        }

        function checkAddToCartState() {
            if (!$limitedNumberInput.length) return;
            // Don't hide error if product is fully unavailable
            if (isProductFullyUnavailable()) return;

            if ($limitedNumberInput.hasClass("woo-limit-available")) {
                window.IJWLP_Frontend_Common.hideError();
            }
        }

        // ========================================
        // AJAX Cart Handler (Unified)
        // ========================================

        /**
         * Unified AJAX add-to-cart handler
         * @param {Object} options - { isLimited, wooLimit, onSuccess }
         */
        function ajaxAddToCart(options) {
            var isLimited = options.isLimited || false;
            var wooLimit = options.wooLimit || "";

            var ids = getProductIds();
            var quantity = getQuantity();
            var originalText = setButtonLoading("Adding...");
            var wasSuccessful = false;

            // Set swatches non-selectable during AJAX submission
            setSwatchesNonSelectable();

            var ajaxData = {
                action: "ijwlp_add_to_cart",
                nonce: ijwlp_frontend.nonce,
                product_id: ids.productId,
                variation_id: ids.variationId,
                quantity: quantity
            };

            if (isLimited) {
                ajaxData.woo_limit = wooLimit;
            }

            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: ajaxData,
                success: function (response) {
                    if (response.success) {
                        wasSuccessful = true;
                        handleSuccessResponse(response, originalText, isLimited, wooLimit, ids.variationId, quantity);
                    } else {
                        window.IJWLP_Frontend_Common.showError(
                            response.data.message || "Failed to add product to cart.",
                            $errorDiv
                        );
                    }
                },
                error: function () {
                    window.IJWLP_Frontend_Common.showError(
                        "An error occurred. Please try again.",
                        $errorDiv
                    );
                },
                complete: function () {
                    // Only handle non-success case here
                    // Success case re-enabling is handled in the 2-second timeout
                    if (!wasSuccessful) {
                        clearButtonLoading(originalText);

                        // Use unified check to cover all unavailable states
                        if (!isProductFullyUnavailable() && !$addToCartButton.hasClass("woo-outofstock")) {
                            enableButton();
                            $addToCartButton.removeClass("woo-limit-loading disabled");
                            clearSwatchesNonSelectable();
                            initializeOutOfStockSwatches();
                            $form.find('input[name="quantity"]').prop("disabled", false);
                            checkAddToCartState();
                        } else {
                            $addToCartButton.removeClass("woo-limit-loading");
                            // Explicitly ensure button stays disabled when product is fully unavailable
                            if (isProductFullyUnavailable()) {
                                $addToCartButton.prop("disabled", true).addClass("disabled");
                            } else {
                                // Only re-enable swatches for specific variant OOS case
                                clearSwatchesNonSelectable();
                                initializeOutOfStockSwatches();
                            }
                        }
                    }
                }
            });
        }

        /**
         * Handle successful add-to-cart response
         */
        function handleSuccessResponse(response, originalText, isLimited, wooLimit, variationId, quantity) {
            // Track if available count reached zero
            var availableCountReachedZero = false;

            // Update limited product available numbers
            if (isLimited && wooLimit) {
                updateAvailableNumbersList(wooLimit);

                // Check if count reached zero after update
                var $availableCountInput = $(".woo-limit-available-count");
                if ($availableCountInput.length) {
                    var newCount = parseInt($availableCountInput.val());
                    if (newCount <= 0) {
                        availableCountReachedZero = true;
                    }
                }
            }

            // Update cart fragments
            if (response.data.fragments) {
                $.each(response.data.fragments, function (key, value) {
                    $(key).replaceWith(value);
                });
            }

            // Trigger WooCommerce events
            $(document.body).trigger("added_to_cart", [
                response.data.fragments,
                response.data.cart_hash,
                $addToCartButton
            ]);

            // Restart timer for limited products
            if (isLimited) {
                restartLimitTimer();
            }

            // Clear messages and show success
            clearAllMessages();

            // Show available count message if it reached zero
            if (availableCountReachedZero) {
                var productName = $(".woo-limit-product-name").val() || "this product";
                window.IJWLP_Frontend_Common.showError(
                    "Available count for " + productName + " reached (0)",
                    $errorDiv
                );
            }

            $addToCartButton.text("Added").addClass("woo-limit-added");
            setTimeout(function () {
                $addToCartButton.text(originalText).removeClass("woo-limit-added");
                // Re-enable button after text reverts (if product is still available)
                // Use the unified check to cover all unavailable states
                if (!isProductFullyUnavailable() && !$addToCartButton.hasClass("woo-outofstock")) {
                    enableButton();
                    $addToCartButton.removeClass("woo-limit-loading disabled");
                    clearSwatchesNonSelectable();
                    initializeOutOfStockSwatches();
                    $form.find('input[name="quantity"]').prop("disabled", false);
                    checkAddToCartState();
                } else {
                    $addToCartButton.removeClass("woo-limit-loading");
                    // Explicitly ensure button stays disabled when product is fully unavailable
                    if (isProductFullyUnavailable()) {
                        $addToCartButton.prop("disabled", true).addClass("disabled");
                    } else {
                        // Only re-enable swatches for specific variant OOS case
                        clearSwatchesNonSelectable();
                        initializeOutOfStockSwatches();
                    }
                }
            }, 2000);

            // Reset limited input
            if (isLimited) {
                resetLimitedInput();
            }

            // Mark button as loading/processing
            $addToCartButton.prop("disabled", true).addClass("disabled woo-limit-loading");

            // Update stock and user limit
            var isNowOutOfStock = updateStockAfterPurchase(variationId, quantity);
            if (!isNowOutOfStock) {
                updateUserLimitAfterPurchase(quantity);
            }

            // Reset swatches if not out of stock
            if (!$addToCartButton.hasClass("woo-outofstock")) {
                $(".rtwpvs-wc-select").val("").trigger("change");
            }

            // Trigger WooCommerce refresh
            if (typeof wc_add_to_cart_params !== "undefined") {
                $(document.body).trigger("wc_fragment_refresh");
            }
        }

        /**
         * Update available numbers list after purchase
         */
        function updateAvailableNumbersList(addedNumber) {
            var $availableInput = $(".woo-limit-available-numbers");
            if ($availableInput.length && window.IJWLP_Frontend_Common) {
                var $wrapper = $limitedNumberInput.closest(".woo-limit-field-wrapper");
                var currentAvailable = window.IJWLP_Frontend_Common.getAvailableNumbers($wrapper);
                var newAvailable = currentAvailable.filter(function (n) {
                    return String(n).trim() !== String(addedNumber).trim();
                });
                $availableInput.val(JSON.stringify(newAvailable));
                window.IJWLP_Frontend_Common.refreshAutocomplete($limitedNumberInput);

                // Update available count
                var $availableCountInput = $(".woo-limit-available-count");
                if ($availableCountInput.length) {
                    var newCount = newAvailable.length;
                    $availableCountInput.val(newCount);

                    // If count reaches zero, set out of stock state
                    // Message will be shown in handleSuccessResponse
                    if (newCount <= 0) {
                        setOutOfStockState();
                    }
                }
            }
        }

        /**
         * Restart the limit timer
         */
        function restartLimitTimer() {
            var limitTime = window.ijwlp_limit_time ||
                parseInt($("[data-limit-time]").attr("data-limit-time")) || 15;
            if (window.ijwlpTimer && typeof window.ijwlpTimer.restartTimer === "function") {
                window.ijwlpTimer.restartTimer(limitTime);
            }
        }

        /**
         * Reset the limited number input after successful purchase
         */
        function resetLimitedInput() {
            $limitedNumberInput.val("").removeClass("woo-limit-error woo-limit-available");

            // Dispose autocomplete
            var ac = $limitedNumberInput.data("autocomplete");
            if (ac) {
                ac.hide();
                ac.dispose();
            }
            $limitedNumberInput.removeData("autocomplete").removeData("ac-initialized");
            $limitedNumberInput.closest(".woo-limit-field-wrapper")
                .find(".woo-limit-autocomplete-box").remove();

            // Re-attach autocomplete
            if (window.IJWLP_Frontend_Common &&
                typeof window.IJWLP_Frontend_Common.attachAutocomplete === "function") {
                setTimeout(function () {
                    window.IJWLP_Frontend_Common.attachAutocomplete($limitedNumberInput);
                }, 100);
            }
        }

        // ========================================
        // Initialization
        // ========================================

        // Initial button state - force enable after delay (only if product is available)
        setTimeout(function () {
            safeEnableButton();
        }, 500);

        // Disable number input initially for variable products
        if (isVariableProduct) {
            $limitedNumberInput.prop("disabled", true).addClass("disabled");
            safeEnableButton();
        }

        // Check initial variation state
        checkVariationSelected();

        // Check initial stock and user limit on page load (handles both simple and variable products)
        // Use the unified handler to apply disabled state if product is unavailable
        // This is called BEFORE the timeout below to set state early
        applyFullyUnavailableState();

        // Initialize out-of-stock swatches (for specific variations only, not for user limit reached)
        setTimeout(function () {
            initializeOutOfStockSwatches();
        }, 100);

        // Final safeguard: Re-apply unavailable state after WooCommerce events may have fired
        // This catches any WooCommerce variation reset or similar events that might clear the state
        setTimeout(function () {
            if (isProductFullyUnavailable()) {
                applyFullyUnavailableState();
            }
        }, 200);

        // ========================================
        // Mutation Observer - Block third-party disabling
        // ========================================

        if ($addToCartButton.length) {
            var buttonObserver = new MutationObserver(function (mutations) {
                // Skip if we're intentionally changing button state to prevent feedback loop
                if (isSettingButtonState) {
                    return;
                }
                mutations.forEach(function (mutation) {
                    if (mutation.type === "attributes" ||
                        (mutation.type === "childList" && mutation.target === $addToCartButton[0])) {

                        var hasProblematicState =
                            $addToCartButton.hasClass("disabled") ||
                            $addToCartButton.hasClass("wc-variation-selection-needed") ||
                            $addToCartButton.prop("disabled") ||
                            $addToCartButton.attr("aria-disabled");

                        if (!shouldButtonRemainDisabled() && hasProblematicState) {
                            enableButton();
                        }
                    }
                });
            });

            buttonObserver.observe($addToCartButton[0], {
                attributes: true,
                attributeFilter: ["disabled", "aria-disabled", "class"],
                subtree: false
            });
        }

        // ========================================
        // Event Handlers - Variable Products
        // ========================================

        if (isVariableProduct) {
            $form.on("found_variation", function (event, variation) {
                // If product is fully unavailable, re-apply the state and don't allow interaction
                if (isProductFullyUnavailable()) {
                    // Re-apply the unavailable state to ensure message is shown
                    applyFullyUnavailableState();
                    return;
                }
                clearPendingTimer();
                variationSelected = true;
                checkVariationSelected();
                updateFieldStates();
                safeEnableButton();
                $limitedNumberInput
                    .val("")
                    .prop("disabled", false)
                    .removeClass("disabled");
                clearInputClasses();
                clearAllMessages();
                // Re-apply out-of-stock states after variation change
                setTimeout(function () {
                    initializeOutOfStockSwatches();
                }, 50);
            });

            $form.on("reset_data", function () {
                // If product is fully unavailable, re-apply the state and don't allow any interaction
                if (isProductFullyUnavailable()) {
                    // Re-apply the unavailable state to ensure message is shown
                    applyFullyUnavailableState();
                    return;
                }
                variationSelected = false;
                $limitedNumberInput
                    .val("")
                    .prop("disabled", true)
                    .addClass("disabled")
                    .removeClass("woo-outofstock");
                $(".woo-number-range").removeClass("woo-limit-error");
                safeEnableButton();
                window.IJWLP_Frontend_Common.hideError();
                // Re-apply out-of-stock states after reset
                setTimeout(function () {
                    initializeOutOfStockSwatches();
                }, 50);
            });

            $(".variations select").on("change", function () {
                // If product is fully unavailable, re-apply state and block changes
                if (isProductFullyUnavailable()) {
                    applyFullyUnavailableState();
                    return;
                }

                clearPendingTimer();
                $limitedNumberInput.val("");
                clearInputClasses();
                clearAllMessages();

                setTimeout(function () {
                    checkVariationSelected();
                    // Re-apply out-of-stock states after selection change
                    initializeOutOfStockSwatches();
                    if (getCurrentVariationStock() <= 0) {
                        window.IJWLP_Frontend_Common.handleOutOfStock(
                            isVariableProduct, $addToCartButton, $limitedNumberInput, $errorDiv
                        );
                    }
                }, 100);
            });
        }

        // ========================================
        // Setup Number Validation
        // ========================================

        if ($limitedNumberInput.length && $errorDiv.length) {
            window.IJWLP_Frontend_Common.setupNumberValidation({
                $input: $limitedNumberInput,
                $button: $addToCartButton,
                $errorDiv: $errorDiv,
                delay: 2000,
                getProductId: function () {
                    return getProductIds().productId;
                },
                getVariationId: function () {
                    return getProductIds().variationId;
                },
                getCurrentStock: function () {
                    return getCurrentVariationStock();
                }
            });
        }

        // Mirror availability class to button
        if ($limitedNumberInput.length) {
            try {
                var inputObserver = new MutationObserver(function (mutations) {
                    mutations.forEach(function (mutation) {
                        if (mutation.type === "attributes" && mutation.attributeName === "class") {
                            if ($limitedNumberInput.hasClass("woo-limit-available")) {
                                $addToCartButton.addClass("woo-limit-available");
                            } else {
                                $addToCartButton.removeClass("woo-limit-available");
                            }
                        }
                    });
                });

                inputObserver.observe($limitedNumberInput[0], {
                    attributes: true,
                    attributeFilter: ["class"],
                    subtree: false
                });

                // Initialize
                if ($limitedNumberInput.hasClass("woo-limit-available")) {
                    $addToCartButton.addClass("woo-limit-available");
                }
            } catch (e) {
                // Fail silently
            }
        }

        // ========================================
        // Form Submit Handler
        // ========================================

        $form.on("submit", function (e) {
            e.preventDefault();

            // Variable product validation - check if variation_id is set (only happens when ALL attributes selected)
            if (isVariableProduct) {
                var $variationIdInput = $('input[name="variation_id"]');
                var variationIdValue = $variationIdInput.length ? $variationIdInput.val() : "";

                // If variation_id is not set or empty, check which attributes are missing
                if (!variationIdValue || variationIdValue === "" || variationIdValue === "0") {
                    var unselectedLabels = [];

                    $(".variations select").each(function () {
                        if (!$(this).val() || $(this).val() === "") {
                            var $label = $(this).closest("tr").find("th label");
                            var labelText = $label.length ? $label.text().trim().toLowerCase() : "";
                            if (labelText) {
                                unselectedLabels.push(labelText);
                            }
                        }
                    });

                    if (unselectedLabels.length > 0) {
                        $selectionErrorDiv.find(".woo-limit-variation-error").remove();
                        var errorMsg;
                        if (unselectedLabels.length === 1) {
                            errorMsg = "Please select " + unselectedLabels[0];
                        } else {
                            // Join all labels with commas and "and" for the last one
                            var lastLabel = unselectedLabels.pop();
                            errorMsg = "Please select " + unselectedLabels.join(", ") + " and " + lastLabel;
                        }
                        $selectionErrorDiv.append(
                            '<div class="woo-limit-variation-error">' + errorMsg + "</div>"
                        ).show();
                        return false;
                    } else {
                        // All selects have values but variation_id not set - invalid combination
                        $selectionErrorDiv.find(".woo-limit-variation-error").remove();
                        $selectionErrorDiv.append(
                            '<div class="woo-limit-variation-error">Please select a valid variation combination</div>'
                        ).show();
                        return false;
                    }
                }
            }

            var quantity = getQuantity();

            // Quantity validation
            if (!/^[1-9]\d*$/.test(quantity)) {
                window.IJWLP_Frontend_Common.showError("Please enter a valid quantity.", $errorDiv);
                $form.find('input[name="quantity"]').focus();
                return false;
            }

            // Limited product submission
            if (isLimitedProduct) {
                var value = $limitedNumberInput.val().trim();

                if (value === "") {
                    $limitedNumberInput.addClass("woo-limit-error-highlight");
                    $(".woo-number-range").addClass("woo-limit-error");
                    $limitedNumberInput.focus();
                    return false;
                } else {
                    $(".woo-number-range").removeClass("woo-limit-error");
                }

                if (!window.IJWLP_Frontend_Common.validateNumberFormat(value)) {
                    window.IJWLP_Frontend_Common.showError("Please enter a valid number.", $errorDiv);
                    return false;
                }

                if ($addToCartButton.hasClass("woo-limit-loading") ||
                    !$addToCartButton.hasClass("woo-limit-available")) {
                    return false;
                }

                clearPendingTimer();

                var ids = getProductIds();

                // Verify availability before submitting
                window.IJWLP_Frontend_Common.checkNumberAvailability({
                    number: value,
                    productId: ids.productId,
                    variationId: ids.variationId,
                    silent: true,
                    cartItemKey: "",
                    $input: $limitedNumberInput,
                    $button: $addToCartButton,
                    $errorDiv: $errorDiv,
                    onStart: function () {
                        $addToCartButton.addClass("woo-limit-checking").removeClass("woo-limit-available");
                        setSwatchesNonSelectable();
                    },
                    onComplete: function (data) {
                        clearSwatchesNonSelectable();
                        if (data.available) {
                            $addToCartButton.addClass("woo-limit-available").removeClass("woo-limit-checking");
                            window.IJWLP_Frontend_Common.hideError($errorDiv);
                            ajaxAddToCart({ isLimited: true, wooLimit: value });
                        } else {
                            $addToCartButton.removeClass("woo-limit-available woo-limit-checking");
                            $addToCartButton.prop("disabled", false);
                        }
                    }
                });
            } else {
                // Normal product submission
                ajaxAddToCart({ isLimited: false });
            }

            return false;
        });
    });
})(jQuery);
