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
                url: wc_checkout_params.ajax_url,
                type: "POST",
                data: {
                    action: "get_cart_totals",
                    security: wc_checkout_params.update_order_review_nonce,
                },
                success: function (response) {
                    if (response && response.totals) {
                        // Update delivery cost
                        var shippingTotal = response.totals.shipping_total;
                        var currencySymbol =
                            "<?php echo get_woocommerce_currency_symbol(); ?>";
                        var deliveryCost =
                            shippingTotal > 0
                                ? currencySymbol +
                                  parseFloat(shippingTotal).toFixed(2)
                                : "FREE";
                        $(
                            '.custom-order-summary-wrapper .summary-line .label:contains("Delivery:")'
                        )
                            .next()
                            .text(deliveryCost);

                        // Update total
                        var total = response.totals.total;
                        var currencySymbol =
                            "<?php echo get_woocommerce_currency_symbol(); ?>";
                        var totalFormatted =
                            currencySymbol + parseFloat(total).toFixed(2);
                        $(
                            ".custom-order-summary-wrapper .total-line .value"
                        ).text(totalFormatted);
                        $(
                            ".custom-order-summary-wrapper .items-count .value"
                        ).text("Total: " + totalFormatted);
                    }
                },
            });
        });

        // Smooth scroll to order summary when cart is updated
        $(document.body).on("added_to_cart", function () {
            $("html, body").animate(
                {
                    scrollTop: $("#custom-order-summary").offset().top - 100,
                },
                800
            );
        });
    });
})(jQuery);
