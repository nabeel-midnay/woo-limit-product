/**
 * WooCommerce Limited Product - Frontend Common JavaScript
 *
 * Common utilities and functions shared across pages
 *
 * @package WooCommerce Limited Product
 */

(function ($) {
    "use strict";

    // Common namespace for shared functions
    window.IJWLP_Frontend_Common = {
        // Debounce timers
        checkTimers: {},
        OUT_OF_STOCK_MESSAGES: {
            product: "This product is out of stock",
            variation: "This variation is out of stock",
        },
        /**
         * Get dynamic out-of-stock message
         * @param {boolean} isVariation - true if variation, false if product
         * @returns {string}
         */
        getOutOfStockMessage: function (isVariation) {
            return isVariation
                ? this.OUT_OF_STOCK_MESSAGES.variation
                : this.OUT_OF_STOCK_MESSAGES.product;
        },

        /**
         * Helper: Disable other limited inputs in the same wrapper
         * @param {jQuery} $input - Current input
         */
        _disableOtherInputs: function ($input) {
            var $wrapper = $input.closest(".woo-limit-field-wrapper");
            if ($wrapper && $wrapper.length) {
                $wrapper.find(".woo-limit").not($input).prop("disabled", true);
            }
        },

        /**
         * Helper: Enable other limited inputs in the same wrapper
         * @param {jQuery} $input - Current input
         */
        _enableOtherInputs: function ($input) {
            var $wrapper = $input.closest(".woo-limit-field-wrapper");
            if ($wrapper && $wrapper.length) {
                $wrapper.find(".woo-limit").not($input).prop("disabled", false);
            }
        },

        /**
         * Helper: Disable all cart action buttons
         */
        _disableCartActions: function () {
            $(".woo-coupon-btn").prop("disabled", true);
            $(".wc-forward").addClass("disabled").prop("disabled", true);
            $(".wc-forward").on("click.woo-limit", function (e) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            });
        },

        /**
         * Helper: Set input and button to error state
         * @param {jQuery} $input - Input element
         * @param {jQuery} $button - Button element (optional)
         */
        _setInputErrorState: function ($input, $button) {
            $input
                .addClass("woo-limit-error")
                .removeClass("woo-limit-available")
                .val("");
            if ($button && $button.length) {
                $button.prop("disabled", true).addClass("disabled");
            }
        },

        /**
         * Helper: Clear check timer for an input
         * @param {string} inputId - Input identifier
         */
        _clearCheckTimer: function (inputId) {
            if (this.checkTimers[inputId]) {
                clearTimeout(this.checkTimers[inputId]);
                delete this.checkTimers[inputId];
            }
        },

        /**
         * Helper: Build check options object for checkNumberAvailability
         * @param {object} baseOptions - Base options with callbacks
         * @param {string} value - Number value to check
         * @returns {object} - Complete options object
         */
        _buildCheckOptions: function (baseOptions, value) {
            return {
                number: value,
                productId: baseOptions.getProductId(),
                variationId: baseOptions.getVariationId ? baseOptions.getVariationId() : 0,
                cartItemKey: baseOptions.getCartItemKey ? baseOptions.getCartItemKey() : "",
                $input: baseOptions.$input,
                $button: baseOptions.$button,
                $errorDiv: baseOptions.$errorDiv,
                onStart: baseOptions.onStart,
                onEnd: baseOptions.onEnd,
                onComplete: baseOptions.onComplete,
            };
        },

        /**
         * Handle out-of-stock state: show message, disable button, add class
         * @param {boolean} isVariation - true if variation, false if product
         * @param {jQuery} $button - Button to disable
         * @param {jQuery} $input - Input to disable
         * @param {jQuery} $errorDiv - Error div to show message in
         */
        handleOutOfStock: function (isVariation, $button, $input, $errorDiv) {
            // Disable input
            if ($input && $input.length) {
                $input
                    .prop("disabled", true)
                    .addClass("disabled")
                    .addClass("woo-outofstock");
            }
            // Disable and mark button
            if ($button && $button.length) {
                $button
                    .prop("disabled", true)
                    .addClass("disabled")
                    .addClass("woo-outofstock");
            }
            // Show error message
            this.showError(this.getOutOfStockMessage(isVariation), $errorDiv);
        },

        /**
         * Show error message
         * @param {string} message - Error message to display
         * @param {jQuery} $errorDiv - Optional error div element
         * @param {number} timeout - Optional timeout in ms to auto-hide
         */
        showError: function (message, $errorDiv, timeout) {
            $errorDiv = $errorDiv || $(".woo-limit-message");
            if ($errorDiv.length) {
                // Clear any existing timer
                var timerId = $errorDiv.data("error-timer");
                if (timerId) {
                    clearTimeout(timerId);
                    $errorDiv.removeData("error-timer");
                }

                $errorDiv
                    .removeClass("woo-limit-info")
                    .addClass("woo-limit-error")
                    .text(message)
                    .show();

                if (timeout) {
                    var newTimerId = setTimeout(function () {
                        $errorDiv.fadeOut(300, function () {
                            $(this)
                                .removeClass("woo-limit-error")
                                .hide()
                                .removeData("error-timer");
                            $(this).closest('.woo-limit-cart-item').find('input').trigger('input');
                        });
                    }, timeout);
                    $errorDiv.data("error-timer", newTimerId);
                }
            }
        },

        /**
         * Show info message
         * @param {string} message - Info message to display
         * @param {jQuery} $errorDiv - Optional error div element
         */
        showInfo: function (message, $errorDiv) {
            $errorDiv = $errorDiv || $(".woo-limit-message");
            if ($errorDiv.length) {
                $errorDiv
                    .removeClass("woo-limit-error")
                    .addClass("woo-limit-info")
                    .text(message)
                    .show();
            }
        },

        /**
         * Hide error/info message
         * @param {jQuery} $errorDiv - Optional error div element
         */
        hideError: function ($errorDiv) {
            $errorDiv = $errorDiv || $(".woo-limit-message");

            // Clear any existing timer
            var timerId = $errorDiv.data("error-timer");
            if (timerId) {
                clearTimeout(timerId);
                $errorDiv.removeData("error-timer");
            }

            $errorDiv
                .hide()
                .removeClass("woo-limit-error woo-limit-info loading");
        },

        /**
         * Validate limited edition number format
         * @param {string} value - Number value to validate
         * @returns {boolean} - True if valid, false otherwise
         */
        validateNumberFormat: function (value) {
            if (!value || value.trim() === "") {
                return false;
            }

            // Check if it's a valid number
            var numValue = parseInt(value, 10);
            return !isNaN(numValue);
        },

        /**
         * Check if numbers are available
         * @returns {boolean} - True if numbers available, false otherwise
         */
        isNumbersAvailable: function () {
            var $availableInfo = $(".woo-limit-available-info");
            if (
                $availableInfo.length &&
                $availableInfo.hasClass("woo-limit-error")
            ) {
                return false;
            }
            return true;
        },

        /**
         * Update cart action buttons state based on whether any errors exist in limited inputs
         */
        updateCartActionButtons: function () {
            var hasErrors = false;

            // Check if any limited input has an error
            if ($(".woo-limit.woo-limit-error").length > 0) {
                hasErrors = true;
            }

            // Check if any error message is visible
            if ($(".woo-limit-message.woo-limit-error:visible").length > 0) {
                hasErrors = true;
            }

            // Check if any range info has error
            if ($(".woo-number-range.woo-limit-error").length > 0) {
                hasErrors = true;
            }

            // Disable or enable button based on error state
            $(".woo-coupon-btn").prop("disabled", hasErrors);

            // Handle .wc-forward link - add/remove disabled class
            var $wcForward = $(".wc-forward");
            if (hasErrors) {
                $wcForward.addClass("disabled").prop("disabled", true);
                $wcForward.on("click.woo-limit", function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                });
            } else {
                $wcForward.removeClass("disabled").prop("disabled", false);
                $wcForward.off("click.woo-limit");
            }
        },

        /*
         * @param {number} options.productId - Product ID
         * @param {number} options.variationId - Variation ID (optional)
         * @param {string} options.cartItemKey - Cart item key (optional, for cart page)
         * @param {jQuery} options.$input - Input field element
         * @param {jQuery} options.$button - Add to cart button element (optional)
         * @param {jQuery} options.$errorDiv - Error div element
         * @param {function} options.onComplete - Callback when check completes
         */
        checkNumberAvailability: function (options) {
            var self = this;
            var $input = options.$input;
            var $button = options.$button;
            var $errorDiv = options.$errorDiv;
            var number = options.number;

            if (!number && number !== 0 && number !== '0') {
                return;
            }

            var productId = options.productId;
            var variationId = options.variationId || 0;
            var cartItemKey = options.cartItemKey || "";
            // If silent is true, suppress user-facing messages (useful when submitting the form)
            var silent = options.silent || false;

            // Clear any existing timer for this input
            var inputId =
                $input.attr("id") || $input.data("cart-key") || "default";
            if (self.checkTimers[inputId]) {
                clearTimeout(self.checkTimers[inputId]);
                delete self.checkTimers[inputId];
            }

            // Call onStart callback if provided
            if (options.onStart) {
                options.onStart();
            }

            // Hide autocomplete immediately
            var ac = $input.data("autocomplete");
            if (ac) {
                ac.hide();
            }

            // Show loading state (suppress visible messages when silent)
            $input.addClass("woo-limit-loading").prop("disabled", true);
            if ($button && $button.length) {
                $button.addClass("woo-limit-loading").prop("disabled", true);
            }
            if (!silent) {
                $errorDiv.addClass("loading").text("Checking...").show();
            }

            // Make AJAX request
            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: {
                    action: "ijwlp_check_number_availability",
                    nonce: ijwlp_frontend.nonce,
                    product_id: productId,
                    variation_id: variationId,
                    woo_limit: number,
                    cart_item_key: cartItemKey,
                },
                success: function (response) {
                    // Check if the input value has changed since the request was made
                    // If so, ignore this response to prevent race conditions
                    if ($input.val().trim() !== String(number).trim()) {
                        return;
                    }

                    // Call onEnd callback if provided
                    if (options.onEnd) {
                        options.onEnd();
                    }

                    // Remove loading state
                    $input
                        .removeClass("woo-limit-loading")
                        .prop("disabled", false);
                    if ($button && $button.length) {
                        $button.removeClass("woo-limit-loading");
                    }
                    $errorDiv.removeClass("loading");

                    // Hide the autocomplete box after AJAX check completes
                    var ac = $input.data("autocomplete");
                    if (ac) {
                        ac.hide();
                    }

                    if (response.success && response.data) {
                        var data = response.data;

                        // Consider the number available only when the server reports status === 'available'
                        if (data.available && data.status === "available") {
                            // Number is available
                            if (!silent) {
                                var luckyMsg = "You are lucky! Click on Add to cart";
                                if ($('body').hasClass('woocommerce-cart')) {
                                    luckyMsg = "You are lucky!";
                                }
                                self.showInfo(
                                    luckyMsg,
                                    $errorDiv
                                );
                            }
                            $input
                                .removeClass("woo-limit-error")
                                .addClass("woo-limit-available");

                            // Enable button if provided
                            if ($button && $button.length) {
                                $button
                                    .prop("disabled", false)
                                    .removeClass("disabled");
                            }

                            // Re-enable all other limited inputs in the same wrapper on cart page
                            self._enableOtherInputs($input);

                            // Re-enable cart action buttons if no other errors exist
                            self.updateCartActionButtons();

                            data.available = true;
                        } else {
                            // Treat any non-'available' or unavailable as an error
                            // Special-case server-reported 'max_quantity' status so we can show
                            // a nicer message that includes the product name and the max value.
                            if (data && data.status === "max_quantity") {
                                var maxVal =
                                    data.max_quantity || data.max || "";

                                // Try to determine product name from DOM in a few ways
                                var prodName = "";
                                try {
                                    var $wrapper = $input.closest(
                                        ".woo-limit-field-wrapper"
                                    );
                                    var $row = $wrapper.closest(".cart_item");
                                    if ($row && $row.length) {
                                        prodName = $row
                                            .find(".product-name a")
                                            .first()
                                            .text()
                                            .trim();
                                    }
                                } catch (e) {
                                    prodName = "";
                                }

                                if (!prodName) {
                                    prodName =
                                        $(".product_title")
                                            .first()
                                            .text()
                                            .trim() ||
                                        $("h1.product_title")
                                            .first()
                                            .text()
                                            .trim() ||
                                        (document && document.title) ||
                                        "";
                                }

                                var friendly =
                                    "Max quantity for " +
                                    prodName +
                                    " reached (" +
                                    maxVal +
                                    ")";

                                self.showError(friendly, $errorDiv, 5000);

                                self._setInputErrorState($input, $button);
                                self._disableOtherInputs($input);
                                self._disableCartActions();

                                data.available = false;
                            } else {
                                var msg =
                                    data && data.message
                                        ? data.message
                                        : "This number is not available.";
                                if (!silent) {
                                    self.showError(msg, $errorDiv, 5000);
                                }
                                self._setInputErrorState($input, $button);
                                self._disableOtherInputs($input);
                                self._disableCartActions();

                                data.available = false;
                            }
                        }

                        // Call completion callback (with coerced available flag)
                        if (
                            options.onComplete &&
                            typeof options.onComplete === "function"
                        ) {
                            options.onComplete(data);
                        }
                    } else {
                        // Generic failure
                        self.showError(
                            response.data && response.data.message
                                ? response.data.message
                                : "Error checking availability.",
                            $errorDiv,
                            5000
                        );
                        $input
                            .removeClass("woo-limit-loading")
                            .prop("disabled", false);
                        if ($button && $button.length) {
                            $button
                                .removeClass("woo-limit-loading")
                                .prop("disabled", false)
                                .addClass("disabled");
                        }
                        self._setInputErrorState($input, $button);
                    }
                },
                error: function (xhr, status, error) {
                    // Check if the input value has changed since the request was made
                    if ($input.val().trim() !== String(number).trim()) {
                        return;
                    }

                    // Call onEnd callback if provided
                    if (options.onEnd) {
                        options.onEnd();
                    }

                    // Remove loading state
                    $input
                        .removeClass("woo-limit-loading")
                        .prop("disabled", false);
                    if ($button && $button.length) {
                        $button
                            .removeClass("woo-limit-loading")
                            .prop("disabled", false);
                    }
                    $errorDiv.removeClass("loading");

                    self.showError(
                        "An error occurred while checking availability. Please try again.",
                        $errorDiv,
                        5000
                    );
                },
            });
        },

        /**
         * Setup debounced number validation
         * @param {object} options - Configuration options
         * @param {jQuery} options.$input - Input field element
         * @param {jQuery} options.$button - Add to cart button element (optional)
         * @param {jQuery} options.$errorDiv - Error div element
         * @param {function} options.getProductId - Function to get product ID
         * @param {function} options.getVariationId - Function to get variation ID (optional)
         * @param {function} options.getCartItemKey - Function to get cart item key (optional)
         * @param {number} options.delay - Delay in milliseconds (default: 5000)
         * @param {function} options.onStart - Callback when check starts (optional)
         * @param {function} options.onEnd - Callback when check ends (before logic) (optional)
         */
        setupNumberValidation: function (options) {
            var self = this;
            var $input = options.$input;
            var $button = options.$button;
            var $errorDiv = options.$errorDiv;
            var delay = options.delay || 2000;
            var inputId =
                $input.attr("id") || $input.data("cart-key") || "default";

            // Range info element and start/end values (added to wrapper by PHP)
            var $wrapper = $input.closest(".woo-limit-field-wrapper");
            var $rangeInfo = $wrapper.find(".woo-number-range");
            var start = parseInt($wrapper.data("start"), 10);
            var end = parseInt($wrapper.data("end"), 10);
            if (isNaN(start)) {
                start = null;
            }
            if (isNaN(end)) {
                end = null;
            }

            // Initialize last valid value
            $input.data("last-valid-value", $input.val().replace(/[^0-9]/g, ''));

            // Clear error/available message on input
            $input.on("input", function () {
                var value = $(this).val();

                // Remove non-numeric characters
                var numericValue = value.replace(/[^0-9]/g, '');

                // Check max value if exists
                if (end !== null && numericValue !== "") {
                    var intValue = parseInt(numericValue, 10);
                    if (intValue > end) {
                        var lastValid = $input.data("last-valid-value");
                        if (typeof lastValid !== "undefined") {
                            numericValue = lastValid;
                        } else {
                            numericValue = String(end);
                        }
                    } else {
                        $input.data("last-valid-value", numericValue);
                    }
                } else {
                    $input.data("last-valid-value", numericValue);
                }

                // Update input if value changed
                if (value !== numericValue) {
                    $(this).val(numericValue);
                    value = numericValue;
                }

                value = value.trim();


                $input
                    .removeClass("woo-limit-error")
                    .removeClass("woo-limit-available");
                if ($rangeInfo.length) {
                    $rangeInfo.removeClass("woo-limit-error");
                }

                // Clear availability/info/error message when user starts typing
                // If there was an existing error, mark that we cleared it so the
                // debounced availability check is not immediately scheduled
                var hadError = $errorDiv.hasClass("woo-limit-error");
                if (hadError || $errorDiv.hasClass("woo-limit-info")) {
                    self.hideError($errorDiv);
                }

                if (hadError) {
                    // flag to avoid scheduling the debounced AJAX right away
                    $input.data("cleared-error", true);

                    // Re-enable all other limited inputs in the same wrapper when current input is edited
                    self._enableOtherInputs($input);

                    // Restore cart action buttons state and remove any click block on .wc-forward
                    self.updateCartActionButtons();
                    $(".wc-forward")
                        .removeClass("disabled")
                        .prop("disabled", false)
                        .off("click.woo-limit");
                }

                if (value === "") {
                    self.hideError($errorDiv);
                    if ($rangeInfo.length) {
                        $rangeInfo.removeClass("woo-limit-error");
                    }
                    $input.removeClass("woo-limit-available");
                    if ($button && $button.length) {
                        $button.prop("disabled", true).addClass("disabled");
                    }
                    // Re-enable all other limited inputs in the same wrapper when current input is cleared
                    self._enableOtherInputs($input);
                    // Update cart action buttons state
                    self.updateCartActionButtons();
                    // Clear any pending check
                    self._clearCheckTimer(inputId);
                    // Hide the autocomplete box when input is cleared
                    var ac = $input.data("autocomplete");
                    if (ac) {
                        ac.hide();
                    }
                }
            });

            // Check on Enter key
            $input.on("keypress", function (e) {
                if (e.which === 13) {
                    // Enter key
                    e.preventDefault();
                    var value = $(this).val().trim();
                    if (!value) {
                        return;
                    }

                    // If the value equals the stored old value, skip AJAX
                    var oldVal = $input.data("old-value");
                    if (
                        typeof oldVal !== "undefined" &&
                        String(value) === String(oldVal)
                    ) {
                        return;
                    }

                    // Validate range client-side (don't trigger AJAX if out of range)
                    if (start !== null && end !== null) {
                        var clean = parseInt(value, 10);
                        if (isNaN(clean) || clean < start || clean > end) {
                            if ($rangeInfo.length) {
                                if (clean === 0) {
                                    $input.val('');
                                }
                                $rangeInfo.addClass("woo-limit-error");
                            }
                            // Do not trigger availability AJAX
                            return;
                        } else if ($rangeInfo.length) {
                            $rangeInfo.removeClass("woo-limit-error");
                        }
                    }

                    // Clear any pending timer
                    self._clearCheckTimer(inputId);
                    // Check immediately
                    self.checkNumberAvailability(self._buildCheckOptions(options, value));
                }
            });

            // Debounced check on input change
            $input.on("input", function () {
                var value = $(this).val().trim();

                if (!value) {
                    return;
                }

                // If we just cleared an error while the user started editing,
                // clear the flag and any pending timers, but allow scheduling
                // a new check below (don't return early).
                if ($input.data("cleared-error")) {
                    $input.removeData("cleared-error");
                    // Clear any existing timer so we start fresh
                    self._clearCheckTimer(inputId);
                    // Don't return - let the code below schedule the timeout check
                }

                // If the value equals the stored old value, do not schedule an availability check
                var oldVal2 = $input.data("old-value");
                if (
                    typeof oldVal2 !== "undefined" &&
                    String(value) === String(oldVal2)
                ) {
                    // Clear any pending timer and bail out
                    self._clearCheckTimer(inputId);
                    return;
                }

                // Clear existing timer
                if (self.checkTimers[inputId]) {
                    clearTimeout(self.checkTimers[inputId]);
                }

                // Validate range client-side (don't trigger AJAX if out of range)
                if (start !== null && end !== null) {
                    var clean = parseInt(value, 10);
                    if (isNaN(clean) || clean < start || clean > end) {
                        if ($rangeInfo.length) {
                            $rangeInfo.addClass("woo-limit-error");
                            if (clean === 0) {
                                $input.val('');
                            }
                        }
                        // Do not schedule availability AJAX
                        return;
                    } else if ($rangeInfo.length) {
                        $rangeInfo.removeClass("woo-limit-error");
                    }
                }

                // Set new timer
                self.checkTimers[inputId] = setTimeout(function () {
                    self.checkNumberAvailability(self._buildCheckOptions(options, value));
                    delete self.checkTimers[inputId];
                }, delay);
            });
        },

        /**
         * Parse available numbers for a wrapper element.
         * Supports JSON array or CSV/whitespace-separated list.
         * @param {jQuery} $wrapper
         * @returns {Array<string>}
         */
        getAvailableNumbers: function ($wrapper) {
            if (!$wrapper || !$wrapper.length) return [];
            var $avail = $wrapper.find(".woo-limit-available-numbers").first();
            if (!$avail.length) return [];

            var raw = $avail.val() || $avail.attr("value") || "";
            raw = String(raw || "").trim();
            if (!raw) return [];

            try {
                if (raw.charAt(0) === "[") {
                    var parsed = JSON.parse(raw);
                    if ($.isArray(parsed)) return parsed.map(String);
                }
            } catch (e) {
                // fallback
            }

            var parts =
                raw.indexOf(",") !== -1 ? raw.split(",") : raw.split(/\s+/);
            var out = [];
            for (var i = 0; i < parts.length; i++) {
                var p = String(parts[i] || "").trim();
                if (p !== "") out.push(p);
            }
            return out;
        },

        /**
         * Build suggestions list for a given input value
         * @param {string} val - Current input value
         * @param {jQuery} $input - Input element
         * @returns {Array} - List of suggestions
         */
        buildSuggestions: function (val, $input) {
            var $wrapper = $input.closest(".woo-limit-field-wrapper");
            var avail = this.getAvailableNumbers($wrapper);
            if (!avail || !avail.length) return [];

            var used = $wrapper
                .find("input.woo-limit")
                .not($input)
                .map(function () {
                    return String($(this).val() || "").trim();
                })
                .get();

            return avail
                .filter(function (v) {
                    v = String(v);
                    if (used.indexOf(v) !== -1) return false;
                    if (!v.includes(val)) return false;
                    return true;
                })
                .slice(0, 10)
                .map(function (v) {
                    return { value: String(v) };
                });
        },

        /**
         * Refresh autocomplete suggestions for an input
         * @param {jQuery} $input
         */
        refreshAutocomplete: function ($input) {
            if (!$input || !$input.length) return;
            // Prevent refresh if input is disabled or loading
            if ($input.prop("disabled") || $input.hasClass("woo-limit-loading")) return;

            var inst = $input.data("autocomplete");
            if (!inst) return;

            var val = $input.val().trim();
            if (val === "") {
                inst.setOptions({ lookup: [] });
                inst.hide();
                return;
            }

            var suggestions = this.buildSuggestions(val, $input);
            inst.setOptions({ lookup: suggestions });
            inst.onValueChange();
        },

        /**
         * Attach Devbridge Autocomplete to a .woo-limit input.
         * Suggestion list limited to 10 and excludes used values.
         * Suggestions update dynamically on input and focus.
         * @param {jQuery} $input
         */
        attachAutocomplete: function ($input) {
            if (!$input || !$input.length) return;

            var self = this;
            var $wrapper = $input.closest(".woo-limit-field-wrapper");

            if ($input.data("ac-initialized")) return;
            $input.data("ac-initialized", true);

            var $customBox = $(
                "<div class='woo-limit-autocomplete-box'></div>"
            );
            $wrapper.append($customBox);

            function refresh() {
                self.refreshAutocomplete($input);
            }

            // First-time initialization
            var val = $input.val().trim();
            var suggestions = val ? self.buildSuggestions(val, $input) : [];

            $input.autocomplete({
                lookup: suggestions,
                minChars: 1,
                triggerSelectOnValidInput: false,
                appendTo: $customBox,
                containerClass:
                    "woo-limit-autocomplete autocomplete-suggestions",
                onSelect: function (s) {
                    console.log('Clicked suggestion value:', s.value);
                    $input.val(s.value).trigger("input").trigger("change");
                    // Trigger Enter keypress to check availability immediately (like pressing Enter)
                    // Use which, keyCode, and key for maximum browser compatibility
                    var enterEvent = $.Event("keypress", { which: 13, keyCode: 13, key: "Enter" });
                    $input.trigger(enterEvent);
                    setTimeout(function () {
                        var inst2 = $input.data("autocomplete");
                        if (inst2) inst2.hide();
                    }, 100);
                },
            });

            $input.on("input", refresh);
            $input.on("focus", refresh);
        },
    };
})(jQuery);

(function ($) {
    // Auto-attach autocomplete to any existing .woo-limit inputs on pages where common is loaded
    $(document).ready(function () {
        if (
            window.IJWLP_Frontend_Common &&
            typeof window.IJWLP_Frontend_Common.attachAutocomplete ===
            "function"
        ) {
            $(".woo-limit").each(function () {
                try {
                    window.IJWLP_Frontend_Common.attachAutocomplete($(this));
                } catch (e) {
                    // ignore
                }
            });
        }
    });

    // Re-attach autocomplete after WooCommerce cart AJAX updates
    function reinitAutocomplete() {
        if (
            window.IJWLP_Frontend_Common &&
            typeof window.IJWLP_Frontend_Common.attachAutocomplete ===
            "function"
        ) {
            $(".woo-limit").each(function () {
                // Remove previous ac-initialized flag to allow re-init
                $(this).removeData("ac-initialized");
                window.IJWLP_Frontend_Common.attachAutocomplete($(this));
            });
        }
    }

    // WooCommerce cart events for AJAX reloads
    // Note: frontend-cart.js handles updated_cart_totals specifically, so we don't bind it here to avoid duplicates
    $(document.body).on("updated_wc_div", function () {
        reinitAutocomplete();
    });
})(jQuery);
