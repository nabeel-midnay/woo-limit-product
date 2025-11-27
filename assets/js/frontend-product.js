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

        // Check if limited edition field exists on this page
        if (!$limitedNumberInput.length) {
            return; // Exit if not a limited edition product page
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
                hasVariation = true;
            }

            variationSelected = hasVariation;
            updateFieldStates();
        }

        // Disable add to cart button initially if limited edition field exists
        $addToCartButton.prop("disabled", true).addClass("disabled");
        $addToCartButton.addClass("disabled");

        // If it's a variable product, disable the number field initially
        if (isVariableProduct) {
            $limitedNumberInput.prop("disabled", true).addClass("disabled");
            $limitedNumberInput.attr(
                "placeholder",
                "Please select a variation first"
            );
        }

        // Check initial variation state
        checkVariationSelected();

        // Handle variation selection for variable products
        if (isVariableProduct) {
            // Listen for WooCommerce variation events
            $form.on("found_variation", function (event, variation) {
                variationSelected = true;
                updateFieldStates();
            });

            $form.on("reset_data", function () {
                variationSelected = false;
                $limitedNumberInput
                    .val("")
                    .prop("disabled", true)
                    .addClass("disabled");
                $limitedNumberInput.attr(
                    "placeholder",
                    "Please select a variation first"
                );
                $addToCartButton.prop("disabled", true).addClass("disabled");
                window.IJWLP_Frontend_Common.hideError();
            });

            // Also listen to variation select changes directly
            $(".variations select").on("change", function () {
                // Small delay to let WooCommerce process the variation
                setTimeout(function () {
                    checkVariationSelected();
                }, 100);
            });
        }

        // Update field states based on current conditions
        function updateFieldStates() {
            if (!isVariableProduct || variationSelected) {
                // Enable number field if not variable product or variation is selected
                $limitedNumberInput
                    .prop("disabled", false)
                    .removeClass("disabled");
                $limitedNumberInput.attr("placeholder", "Enter edition number");

                // Availability check controls the add to cart button; update visuals
                checkAddToCartState();
            } else {
                // Disable number field if variation not selected
                $limitedNumberInput.prop("disabled", true).addClass("disabled");
                $limitedNumberInput.attr(
                    "placeholder",
                    "Please select a variation first"
                );
                $addToCartButton.prop("disabled", true).addClass("disabled");
            }
        }

        // Check if add to cart button should be enabled
        // Only enable when the number has been positively verified as available
        function checkAddToCartState() {
            if (!$limitedNumberInput.length) {
                return;
            }

            var hasAvailableClass = $limitedNumberInput.hasClass(
                "woo-limit-available"
            );

            // For variable products, require variation selected as well
            if (isVariableProduct && !variationSelected) {
                $addToCartButton.prop("disabled", true).addClass("disabled");
                return;
            }

            if (hasAvailableClass) {
                $addToCartButton
                    .prop("disabled", false)
                    .removeClass("disabled");
                window.IJWLP_Frontend_Common.hideError();
            } else {
                $addToCartButton.prop("disabled", true).addClass("disabled");
            }
        }

        // Get error div
        var $errorDiv = $(".woo-limit-message");

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
            });
        }

        // Enable/disable add to cart button based on input (basic validation)
        $limitedNumberInput.on("input change blur", function () {
            var value = $(this).val().trim();
            if (value === "") {
                $addToCartButton.prop("disabled", true).addClass("disabled");
            } else if (
                window.IJWLP_Frontend_Common.validateNumberFormat(value)
            ) {
                // Basic format validation passed, but availability check will enable/disable button
                // The availability check will handle button state
            } else {
                $addToCartButton.prop("disabled", true).addClass("disabled");
            }
        });

        // Function to submit add to cart form
        function submitAddToCartForm() {
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

            // AJAX request
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

                        // Hide availability message only after successful add to cart
                        window.IJWLP_Frontend_Common.hideError($errorDiv);

                        // Reset form
                        $limitedNumberInput
                            .val("")
                            .removeClass("woo-limit-error")
                            .removeClass("woo-limit-available");
                        $addToCartButton
                            .prop("disabled", true)
                            .addClass("disabled wc-variation-selection-needed");

                        $(".rtwpvs-wc-select").val("").trigger("change");

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
                    $addToCartButton
                        .prop("disabled", false)
                        .removeClass("woo-limit-loading")
                        .text(originalText);
                    checkAddToCartState();
                },
            });
        }

        // Handle form submit with AJAX
        $form.on("submit", function (e) {
            e.preventDefault();

            // Check variation selection for variable products
            if (isVariableProduct && !variationSelected) {
                window.IJWLP_Frontend_Common.showError(
                    "Please select a variation first.",
                    $errorDiv
                );
                return false;
            }

            var value = $limitedNumberInput.val().trim();

            if ($limitedNumberInput.length && value === "") {
                window.IJWLP_Frontend_Common.showError(
                    "Please enter a Limited Edition Number.",
                    $errorDiv
                );
                $limitedNumberInput.focus();
                return false;
            }

            // Basic format validation
            if (!window.IJWLP_Frontend_Common.validateNumberFormat(value)) {
                window.IJWLP_Frontend_Common.showError(
                    "Please enter a valid number.",
                    $errorDiv
                );
                return false;
            }

            // Check availability before submitting
            var productId =
                $form.find('input[name="add-to-cart"]').val() ||
                $form.find('input[name="product_id"]').val();
            var variationId =
                $form.find('input[name="variation_id"]').val() || 0;

            // Clear any pending timer
            var inputId = $limitedNumberInput.attr("id") || "default";
            if (window.IJWLP_Frontend_Common.checkTimers[inputId]) {
                clearTimeout(window.IJWLP_Frontend_Common.checkTimers[inputId]);
                delete window.IJWLP_Frontend_Common.checkTimers[inputId];
            }

            // Check availability immediately before submitting
            window.IJWLP_Frontend_Common.checkNumberAvailability({
                number: value,
                productId: productId,
                variationId: variationId,
                cartItemKey: "",
                $input: $limitedNumberInput,
                $button: $addToCartButton,
                $errorDiv: $errorDiv,
                onComplete: function (data) {
                    if (data.available) {
                        // Submit the form
                        submitAddToCartForm();
                    } else {
                        // Error already shown by checkNumberAvailability
                        $addToCartButton.prop("disabled", false);
                    }
                },
            });

            return false;
        });
    });
})(jQuery);
