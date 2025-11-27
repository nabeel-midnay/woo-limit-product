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

        /**
         * Show error message
         * @param {string} message - Error message to display
         * @param {jQuery} $errorDiv - Optional error div element
         */
        showError: function (message, $errorDiv) {
            $errorDiv = $errorDiv || $(".woo-limit-message");
            if ($errorDiv.length) {
                $errorDiv
                    .removeClass("woo-limit-info")
                    .addClass("woo-limit-error")
                    .text(message)
                    .show();
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
            if ($(".woo-limit-range-info.woo-limit-error").length > 0) {
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
            var productId = options.productId;
            var variationId = options.variationId || 0;
            var cartItemKey = options.cartItemKey || "";

            // Clear any existing timer for this input
            var inputId =
                $input.attr("id") || $input.data("cart-key") || "default";
            if (self.checkTimers[inputId]) {
                clearTimeout(self.checkTimers[inputId]);
                delete self.checkTimers[inputId];
            }

            // Show loading state
            $input.addClass("woo-limit-loading").prop("disabled", true);
            if ($button && $button.length) {
                $button.addClass("woo-limit-loading").prop("disabled", true);
            }
            $errorDiv.addClass("loading").text("Checking...").show();

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
                            self.showInfo("You are lucky!", $errorDiv);
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
                            var $wrapper = $input.closest(
                                ".woo-limit-field-wrapper"
                            );
                            if ($wrapper && $wrapper.length) {
                                $wrapper
                                    .find(".woo-limit")
                                    .not($input)
                                    .prop("disabled", false);
                            }

                            // Re-enable cart action buttons if no other errors exist
                            self.updateCartActionButtons();

                            data.available = true;
                        } else {
                            // Treat any non-'available' or unavailable as an error
                            var msg =
                                data && data.message
                                    ? data.message
                                    : "This number is not available.";
                            self.showError(msg, $errorDiv);
                            $input
                                .addClass("woo-limit-error")
                                .removeClass("woo-limit-available");

                            if ($button && $button.length) {
                                $button
                                    .prop("disabled", true)
                                    .addClass("disabled");
                            }

                            // Disable all other limited inputs in the same wrapper on cart page
                            var $wrapper = $input.closest(
                                ".woo-limit-field-wrapper"
                            );
                            if ($wrapper && $wrapper.length) {
                                $wrapper
                                    .find(".woo-limit")
                                    .not($input)
                                    .prop("disabled", true);
                            }

                            // Disable all cart action buttons when error occurs
                            $(".woo-coupon-btn").prop("disabled", true);
                            $(".wc-forward")
                                .addClass("disabled")
                                .prop("disabled", true);
                            $(".wc-forward").on(
                                "click.woo-limit",
                                function (e) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    return false;
                                }
                            );

                            data.available = false;
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
                            $errorDiv
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
                        $input
                            .removeClass("woo-limit-available")
                            .addClass("woo-limit-error");
                    }
                },
                error: function (xhr, status, error) {
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
                        $errorDiv
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
            var $rangeInfo = $wrapper.find(".woo-limit-range-info");
            var start = parseInt($wrapper.data("start"), 10);
            var end = parseInt($wrapper.data("end"), 10);
            if (isNaN(start)) {
                start = null;
            }
            if (isNaN(end)) {
                end = null;
            }

            // Clear error/available message on input
            $input.on("input", function () {
                var value = $(this).val().trim();
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
                    var $wrapper = $input.closest(".woo-limit-field-wrapper");
                    if ($wrapper && $wrapper.length) {
                        $wrapper
                            .find(".woo-limit")
                            .not($input)
                            .prop("disabled", false);
                    }

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
                    var $wrapper = $input.closest(".woo-limit-field-wrapper");
                    if ($wrapper && $wrapper.length) {
                        $wrapper
                            .find(".woo-limit")
                            .not($input)
                            .prop("disabled", false);
                    }
                    // Update cart action buttons state
                    self.updateCartActionButtons();
                    // Clear any pending check
                    if (self.checkTimers[inputId]) {
                        clearTimeout(self.checkTimers[inputId]);
                        delete self.checkTimers[inputId];
                    }
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
                                $rangeInfo.addClass("woo-limit-error");
                            }
                            // Do not trigger availability AJAX
                            return;
                        } else if ($rangeInfo.length) {
                            $rangeInfo.removeClass("woo-limit-error");
                        }
                    }

                    // Clear any pending timer
                    if (self.checkTimers[inputId]) {
                        clearTimeout(self.checkTimers[inputId]);
                        delete self.checkTimers[inputId];
                    }
                    // Check immediately
                    self.checkNumberAvailability({
                        number: value,
                        productId: options.getProductId(),
                        variationId: options.getVariationId
                            ? options.getVariationId()
                            : 0,
                        cartItemKey: options.getCartItemKey
                            ? options.getCartItemKey()
                            : "",
                        $input: $input,
                        $button: $button,
                        $errorDiv: $errorDiv,
                        onComplete: options.onComplete,
                    });
                }
            });

            // Debounced check on input change
            $input.on("input", function () {
                var value = $(this).val().trim();

                if (!value) {
                    return;
                }

                // If we just cleared an error while the user started editing,
                // don't schedule an availability AJAX right away — let the user
                // continue editing. Enter key will still trigger an immediate check.
                if ($input.data("cleared-error")) {
                    $input.removeData("cleared-error");
                    if (self.checkTimers[inputId]) {
                        clearTimeout(self.checkTimers[inputId]);
                        delete self.checkTimers[inputId];
                    }
                    return;
                }

                // If the value equals the stored old value, do not schedule an availability check
                var oldVal2 = $input.data("old-value");
                if (
                    typeof oldVal2 !== "undefined" &&
                    String(value) === String(oldVal2)
                ) {
                    // Clear any pending timer and bail out
                    if (self.checkTimers[inputId]) {
                        clearTimeout(self.checkTimers[inputId]);
                        delete self.checkTimers[inputId];
                    }
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
                        }
                        // Do not schedule availability AJAX
                        return;
                    } else if ($rangeInfo.length) {
                        $rangeInfo.removeClass("woo-limit-error");
                    }
                }

                // Set new timer
                self.checkTimers[inputId] = setTimeout(function () {
                    self.checkNumberAvailability({
                        number: value,
                        productId: options.getProductId(),
                        variationId: options.getVariationId
                            ? options.getVariationId()
                            : 0,
                        cartItemKey: options.getCartItemKey
                            ? options.getCartItemKey()
                            : "",
                        $input: $input,
                        $button: $button,
                        $errorDiv: $errorDiv,
                        onComplete: options.onComplete,
                    });
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

            function buildSuggestions(val) {
                var avail = self.getAvailableNumbers($wrapper);
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
            }

            function refresh() {
                var inst = $input.data("autocomplete");
                var val = $input.val().trim();

                if (val === "") {
                    if (inst) {
                        inst.setOptions({ lookup: [] });
                        inst.hide();
                    }
                    return;
                }

                var suggestions = buildSuggestions(val);

                if (inst) {
                    inst.setOptions({ lookup: suggestions });
                    return;
                }

                // First-time initialization
                $input.autocomplete({
                    lookup: suggestions,
                    minChars: 1,
                    triggerSelectOnValidInput: false,
                    appendTo: $customBox,
                    containerClass: "woo-limit-autocomplete",
                    onSelect: function (s) {
                        $input.val(s.value).trigger("input").trigger("change");
                        setTimeout(function () {
                            var inst2 = $input.data("autocomplete");
                            if (inst2) inst2.hide();
                        }, 100);
                    },
                });

                if (!$("body").hasClass("woocommerce-cart")) {
                    // Force suggestions to display immediately on first typing
                    setTimeout(function () {
                        var inst2 = $input.data("autocomplete");
                        if (inst2) {
                            inst2.setOptions({ lookup: suggestions });
                            inst2.onValueChange();
                        }
                    }, 0);
                }
            }

            refresh();
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
    $(document.body).on("updated_cart_totals updated_wc_div", function () {
        reinitAutocomplete();
    });
})(jQuery);
