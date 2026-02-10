/**
 * WooCommerce Limited Product - Frontend Checkout Page JavaScript
 *
 * Handles checkout page interactions for limited edition products
 *
 * @package WooCommerce Limited Product
 */

(function ($) {
    "use strict";

    $(document).ready(function () {
        // Update order summary when cart changes
        $(document.body).on("updated_cart_totals", function () {
            // Reload the page to update the order summary with latest cart data
            // This ensures all cart changes are reflected in the order summary
            setTimeout(function () {
                location.reload();
            }, 500);
        });

        // Update order summary when checkout form changes
        $(document.body).on("updated_checkout", function () {
            // Update totals in the custom order summary
            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: {
                    action: "get_cart_totals",
                    security: ijwlp_frontend.nonce,
                    billing_country: $("#billing_country").val(),
                    billing_state: $("#billing_state").val(),
                    billing_postcode: $("#billing_postcode").val(),
                    shipping_country: $("#shipping_country").val(),
                    shipping_state: $("#shipping_state").val(),
                    shipping_postcode: $("#shipping_postcode").val(),
                },
                success: function (response) {
                    if (
                        response.success &&
                        response.data &&
                        response.data.totals
                    ) {
                        var totals = response.data.totals;
                        
                        // Update delivery cost using formatted value from PHP
                        $(
                            '.custom-order-summary-wrapper .summary-line .label:contains("Delivery:")',
                        )
                            .next()
                            .html(totals.shipping_total_formatted);

                        // Update total using formatted value from PHP
                        $(
                            ".custom-order-summary-wrapper .total-line .value",
                        ).html(totals.total_formatted);
                        $(
                            ".custom-order-summary-wrapper .items-count .value",
                        ).html("Total: " + totals.total_formatted);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Cart totals AJAX error:', status, error, xhr.responseText);
                },
            });
        });

        // Smooth scroll to order summary when cart is updated
        $(document.body).on("added_to_cart", function () {
            $("html, body").animate(
                {
                    scrollTop: $("#custom-order-summary").offset().top - 100,
                },
                800,
            );
        });
    });
})(jQuery);
