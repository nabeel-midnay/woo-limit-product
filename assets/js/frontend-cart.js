/**
 * WooCommerce Limited Product - Frontend Cart Page JavaScript
 *
 * Handles cart page interactions for limited edition products
 *
 * @package WooCommerce Limited Product
 */

(function ($) {
    "use strict";

    $(document).ready(function () {
        // debounce timer to avoid repeated cart updates when availability checks fire rapidly
        var cartAvailableTimer = null;

        // Function to setup validation and event listeners for a single input
        function setupLimitInput($input) {
            var $wrapper = $input.closest(".woo-limit-field-wrapper");
            var $cartItem = $input.closest(".woo-limit-cart-item");
            var $errorDiv = $cartItem.find(".woo-limit-message");
            var productId = $wrapper.data("product-id");

            window.IJWLP_Frontend_Common.setupNumberValidation({
                $input: $input,
                $button: null,
                $errorDiv: $errorDiv,
                delay: 2000,
                getProductId: function () {
                    return productId;
                },
                getVariationId: function () {
                    return 0;
                },
                getCartItemKey: function () {
                    return $input.data("cart-key");
                },
                onComplete: function (data) {
                    // If server reports this number is available, update old-value and trigger cart update
                    if (data && data.available) {
                        var currentValue = $input.val().trim();
                        $input.data("old-value", currentValue);
                        if (cartAvailableTimer)
                            clearTimeout(cartAvailableTimer);
                        cartAvailableTimer = setTimeout(function () {
                            triggerCartUpdate();
                            cartAvailableTimer = null;
                        }, 200);
                    }
                },
            });

            // attach autocomplete to this input
            try {
                if (
                    window.IJWLP_Frontend_Common &&
                    typeof window.IJWLP_Frontend_Common.attachAutocomplete ===
                    "function"
                ) {
                    window.IJWLP_Frontend_Common.attachAutocomplete($input);
                }
            } catch (e) {
                // fail silently if autocomplete not available
            }
        }

        // Initialize all existing limit inputs
        function initializeAllLimitInputs() {
            $(".woo-limit-cart-item-wrapper .woo-limit").each(function () {
                setupLimitInput($(this));
            });
        }

        // Run initial setup on page load
        initializeAllLimitInputs();

        // Re-initialize after WooCommerce updates cart (AJAX refresh)
        $(document.body).on("updated_cart_totals", function () {
            initializeAllLimitInputs();
        });

        // Client-side enforcement: on load and when qty inputs change, ensure per-parent max is respected
        function showClientNotice(message) {
            var $wrapper = $(".woocommerce-notices-wrapper");
            if (!$wrapper.length) {
                $wrapper = $(
                    '<div class="woocommerce-notices-wrapper" />'
                ).prependTo(".woocommerce");
            }
            var $msg = $(
                '<div class="woocommerce-message" role="alert"></div>'
            ).text(message);
            $wrapper.prepend($msg);
            // Auto-remove after 6 seconds
            setTimeout(function () {
                $msg.fadeOut(300, function () {
                    $(this).remove();
                });
            }, 6000);
        }

        // Trigger WooCommerce cart update: click the update button or submit the cart form
        function triggerCartUpdate() {
            // Try clicking the update cart button if present
            var $updateBtn = $(
                "button[name='update_cart'], input[name='update_cart']"
            ).first();
            if ($updateBtn.length) {
                // give other events a moment to settle
                setTimeout(function () {
                    $updateBtn.trigger("click");
                }, 150);
                return;
            }

            // Fallback: submit the cart form if present
            var $cartForm = $(".woocommerce-cart-form").first();
            if ($cartForm.length) {
                setTimeout(function () {
                    $cartForm.submit();
                }, 150);
                return;
            }

            // Last resort: submit any form with class 'cart'
            var $form = $("form.cart").first();
            if ($form.length) {
                setTimeout(function () {
                    $form.submit();
                }, 150);
            }
        }

        function collectGroups() {
            var groups = {};
            $(".woo-limit-field-wrapper").each(function () {
                var $w = $(this);
                var parentId = String($w.data("product-id"));
                var cartKey = $w.data("cart-item-key");
                var max = parseInt($w.data("max-quantity") || "", 10);

                // Find qty input by cart key
                var $qty = $('input[name="cart[' + cartKey + '][qty]"]');
                if (!$qty.length) {
                    // fallback: nearest qty input in row
                    $qty = $w.closest(".cart_item").find("input.qty");
                }

                var qtyVal = 0;
                if ($qty.length) {
                    qtyVal = parseInt($qty.val() || 0, 10) || 0;
                }

                if (!groups[parentId])
                    groups[parentId] = {
                        items: [],
                        max: isNaN(max) ? null : max,
                    };
                groups[parentId].items.push({
                    cartKey: cartKey,
                    $qty: $qty,
                    qty: qtyVal,
                    $wrapper: $w,
                });
            });
            return groups;
        }

        function enforceGroups(groups, showMessage, $triggerTarget) {
            var limitEnforced = false;
            $.each(groups, function (parentId, info) {
                var max = info.max;
                if (!max) return; // no limit configured

                var total = 0;
                $.each(info.items, function (i, it) {
                    total += it.qty;
                });
                if (total <= max) return;

                limitEnforced = true;
                var excess = total - max;
                // reduce starting from last row
                var rev = info.items.slice().reverse();
                $.each(rev, function (i, it) {
                    if (excess <= 0) return;
                    if (!it.$qty.length) return;
                    var rowQty = parseInt(it.$qty.val() || 0, 10) || 0;
                    if (rowQty <= 0) return;
                    var reduce = Math.min(rowQty, excess);
                    var newQty = rowQty - reduce;
                    it.$qty.val(newQty);
                    // We do NOT trigger change here to avoid recursive loop and unwanted cart updates
                    // it.$qty.trigger("change");
                    excess -= reduce;
                });

                if (showMessage) {
                    // Try to find a place for inline message first
                    var shownInline = false;
                    var first = info.items && info.items.length ? info.items[0] : null;

                    // Determine which item to show the error on
                    var targetItem = first;
                    if ($triggerTarget && info.items) {
                        $.each(info.items, function (i, it) {
                            if (it.$qty.is($triggerTarget)) {
                                targetItem = it;
                                return false;
                            }
                        });
                    }

                    var maxVal = info.max;
                    var prodName = "";

                    if (targetItem && targetItem.$wrapper && targetItem.$wrapper.length) {
                        try {
                            prodName = targetItem.$wrapper
                                .closest(".cart_item")
                                .find(".product-name a")
                                .first()
                                .text()
                                .trim();
                        } catch (e) {
                            prodName = "";
                        }

                        var $rowErr = targetItem.$wrapper.find(".woo-limit-quantity-message").first();
                        if ($rowErr.length) {
                            if (!prodName) prodName = "Product";
                            var msg = "Max quantity for " + prodName + " reached (" + maxVal + ")";

                            // Clear existing timer
                            var timerId = $rowErr.data("error-timer");
                            if (timerId) {
                                clearTimeout(timerId);
                                $rowErr.removeData("error-timer");
                            }

                            $rowErr.text(msg).addClass("woo-limit-error").show();

                            var newTimerId = setTimeout(function () {
                                $rowErr.fadeOut(300, function () {
                                    $(this).removeClass("woo-limit-error").removeData("error-timer");
                                    // Re-enable buttons after error clears
                                    if (targetItem && targetItem.$wrapper) {
                                        var $row = targetItem.$wrapper.closest(".cart_item");
                                        if ($row.length) {
                                            updateQtyButtonsState($row);
                                        }
                                    }
                                });
                            }, 5000);
                            $rowErr.data("error-timer", newTimerId);
                            shownInline = true;
                        }
                    }

                    if (!shownInline) {
                        // Fallback to global notice
                        if (!prodName) {
                            prodName =
                                $(".product_title").first().text().trim() ||
                                document.title ||
                                "";
                        }
                        var msg =
                            "Max quantity for " +
                            prodName +
                            " reached (" +
                            maxVal +
                            ")";

                        showClientNotice(msg);
                    }
                }
            });
            return limitEnforced;
        }

        // Capture phase listener to intercept invalid changes before WooCommerce sees them
        if (document.body.addEventListener) {
            document.body.addEventListener("change", function (e) {
                // Only interest in qty inputs
                if (!e.target || !e.target.classList.contains("qty")) return;

                // We must use the same logic as enforceGroups to see if this specific input needs reverting
                // We can run enforceGroups and see if it modifies our target

                var $target = $(e.target);
                var valBefore = $target.val();

                var groups = collectGroups();
                var enforced = enforceGroups(groups, true, $target);

                var valAfter = $target.val();

                if (enforced && String(valBefore) !== String(valAfter)) {
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    // Also update old-qty to the reverted value so future checks are correct
                    $target.data("old-qty", valAfter);
                }
            }, true);
        }

        // Initialize quantity old values and checkbox UI
        $("input.qty").each(function () {
            var $q = $(this);
            $q.data("old-qty", parseInt($q.val() || 0, 10) || 0);
        });

        // Inline checkbox UI removed; selection now happens inside the modal only.

        // Open modal with removal details and set pending action
        var pendingRemoval = null;
        function openRemoveModal(action) {
            pendingRemoval = action;
            var $modal = $("#field-selection-modal");


            var productName =
                (action && action.productName) ||
                (action && action.wrapper
                    ? action.wrapper
                        .closest(".cart_item")
                        .find(".product-name a")
                        .text()
                    : "") ||
                "";

            // Set the title in h3
            $modal.find(".field-selection-modal-content h3").text(productName);

            var $listContainer = $("#field-selection-list");
            $listContainer.empty();

            if (action.removeAll) {
                // Don't show modal for single input removal - handle directly
                closeRemoveModal();
                return;
            } else {
                // build list with checkboxes inside modal
                var preChecked = action.preChecked || [];
                for (var i = 0; i < action.numbers.length; i++) {
                    var num = action.numbers[i] || "";
                    var esc = $("<div/>").text(num).html();

                    var $option = $('<div class="field-option"></div>');
                    var $flex = $('<div style="display: flex; align-items: center;"></div>');

                    var $cb = $(
                        '<input type="checkbox" name="field-selection" class="woo-limit-modal-select" style="margin-right: 10px;" />'
                    ).attr("data-number", num).val(num);

                    var $labelDiv = $('<div><strong>' + esc + '</strong></div>');

                    $flex.append($cb).append($labelDiv);
                    $option.append($flex);
                    $listContainer.append($option);
                }
            }

            $modal.show();
        }

        function revertQty() {
            if (pendingRemoval && pendingRemoval.wrapper) {
                var $wrapper = pendingRemoval.wrapper;
                var $qty = $wrapper.closest(".cart_item").find("input.qty");
                var oldQty = $qty.data("old-qty");
                if (oldQty !== undefined) {
                    $qty.val(oldQty); // Revert visual value
                    // We do NOT trigger change here to avoid loop, just reset value
                }
            }
        }

        function closeRemoveModal() {
            $("#field-selection-modal").hide();
            pendingRemoval = null;
        }

        // Modal confirm/cancel handlers
        $(document).on("click", "#remove-selected-field", function () {
            if (!pendingRemoval) return closeRemoveModal();
            var action = pendingRemoval;
            var $wrapper = action.wrapper;
            var $qty = $wrapper.closest(".cart_item").find("input.qty");
            var oldQty = $qty.data("old-qty") || 0;

            // original count of limited inputs before removal
            var originalCount =
                action.currentCount ||
                $wrapper.find(".woo-limit-cart-item input.woo-limit").length;

            // Determine which numbers are selected inside modal
            var $modal = $("#field-selection-modal");
            var $checked = $modal.find(".woo-limit-modal-select:checked");

            var performed = false;
            if ($checked.length) {
                var removed = 0;
                $checked.each(function () {
                    var num = $(this).data("number");
                    // find corresponding input in wrapper (by number) and remove its item
                    var $target = $wrapper
                        .find(".woo-limit-cart-item input.woo-limit")
                        .filter(function () {
                            return String($(this).val()) === String(num);
                        })
                        .closest(".woo-limit-cart-item");
                    if ($target.length) {
                        $target.remove();
                        removed++;
                    }
                });

                var newQty = Math.max(0, originalCount - removed);
                $qty.val(newQty).trigger("change");
                $qty.data("old-qty", newQty);
                performed = removed > 0;
            } else if (action.removeAll) {
                // no specific modal selection but action.removeAll -> remove all
                $wrapper.find(".woo-limit-cart-item").remove();
                $qty.val(0).trigger("change");
                $qty.data("old-qty", 0);
                performed = true;
            }

            closeRemoveModal();
            if (performed) {
                triggerCartUpdate();
            }
        });

        // Cancel button handler
        $(document).on("click", "#cancel-field-selection, .cancel-field-selection", function () {
            revertQty();
            closeRemoveModal();
        });

        // Click outside modal to close
        $(document).on("click", "#field-selection-modal", function (e) {
            if (e.target.id === "field-selection-modal") {
                revertQty();
                $(this).hide();
                pendingRemoval = null;
            }
        });

        // Append new limited inputs when qty increases, and prompt modal when qty decreased
        $(document).on("change", "input.qty", function () {
            var $qty = $(this);
            var name = $qty.attr("name") || "";
            var matches = name.match(/cart\[([^\]]+)\]\[qty\]/);
            var cartKey = matches ? matches[1] : null;
            var $wrapper = cartKey
                ? $(
                    '.woo-limit-field-wrapper[data-cart-item-key="' +
                    cartKey +
                    '"]'
                )
                : $qty.closest(".cart_item").find(".woo-limit-field-wrapper");

            var newQty = parseInt($qty.val() || 0, 10) || 0;
            var oldQty = parseInt($qty.data("old-qty") || 0, 10) || 0;

            // Update stored old-qty after handling
            function finalizeQty(q) {
                $qty.data("old-qty", q);
            }

            if (!$wrapper || !$wrapper.length) {
                finalizeQty(newQty);
                // still enforce groups globally
                setTimeout(function () {
                    var groups = collectGroups();
                    enforceGroups(groups, true, $qty);
                }, 50);
                return;
            }

            // Check if product is limited edition
            var isLimited = $wrapper.data("is-limited");
            if (isLimited !== 'yes') {
                finalizeQty(newQty);
                // still enforce groups globally (this will handle max quantity check)
                setTimeout(function () {
                    var groups = collectGroups();
                    if (!enforceGroups(groups, true, $qty)) {
                        triggerCartUpdate();
                    }
                }, 50);
                return;
            }

            var max = parseInt($wrapper.data("max-quantity") || "", 10) || null;
            var $inputs = $wrapper.find(".woo-limit-cart-item input.woo-limit");
            var currentCount = $inputs.length;

            if (newQty > currentCount) {
                // increase: append new inputs up to max
                var toAdd = newQty - currentCount;
                if (max !== null) {
                    toAdd = Math.min(toAdd, Math.max(0, max - currentCount));
                }
                // Prevent increasing while existing inputs are empty
                var $existingInputs = $wrapper.find(
                    ".woo-limit-cart-item input.woo-limit"
                );
                var emptyCount = $existingInputs.filter(function () {
                    return String($(this).val()).trim() === "";
                }).length;
                if (emptyCount > 0) {
                    showClientNotice(
                        ijwlp_frontend.fill_all_inputs_message ||
                        "Please fill all existing Limited Edition Number inputs before increasing the quantity."
                    );
                    // revert qty to old value
                    $qty.val(oldQty);
                    finalizeQty(oldQty);
                    return;
                }
                for (var i = 0; i < toAdd; i++) {
                    // create new input block
                    var index = currentCount + i;
                    var $div = $(
                        '<div class="woo-limit-cart-item woo-input-single gt-2"></div>'
                    );
                    var $inp = $(
                        '<input type="number" class="woo-limit woo-cart-items" name="woo_limit[' +
                        cartKey +
                        '][]" />'
                    );
                    $inp.attr("data-cart-key", cartKey);
                    $inp.attr("data-index", index);
                    $inp.attr("data-old-value", "");
                    if ($wrapper.data("start"))
                        $inp.attr("min", $wrapper.data("start"));
                    if ($wrapper.data("end"))
                        $inp.attr("max", $wrapper.data("end"));

                    // Add error message div for this input
                    var $errDiv = $('<div class="woo-limit-message" style="display: none;"></div>');
                    $div.append($inp).append($errDiv);
                    // append before available hidden input or message
                    var $avail = $wrapper
                        .find(".woo-limit-available-numbers")
                        .first();
                    if ($avail.length) {
                        $avail.before($div);
                    } else {
                        $wrapper.append($div);
                    }

                    // setup validation for new input
                    setupLimitInput($inp);
                }

                // No inline checkbox UI; modal-only selection will be used.

                finalizeQty(newQty);
                // re-run group enforcement without modal
                setTimeout(function () {
                    var groups = collectGroups();
                    enforceGroups(groups, true, $qty);
                }, 50);
                return;
            }

            if (newQty < currentCount) {
                // decrease: determine how many to remove
                var removeCount = currentCount - newQty;
                var $allInputs = $wrapper.find(
                    ".woo-limit-cart-item input.woo-limit"
                );

                // If the last N inputs are empty, remove them immediately without a modal
                var $lastInputs = $allInputs.slice(-removeCount);
                var lastEmpty = true;
                $lastInputs.each(function () {
                    if (String($(this).val()).trim() !== "") {
                        lastEmpty = false;
                        return false;
                    }
                });

                if (removeCount > 0 && lastEmpty) {
                    // remove the corresponding wrapper blocks
                    $lastInputs.closest(".woo-limit-cart-item").remove();

                    // update qty to reflect removals and trigger change so WooCommerce picks it up
                    $qty.data("old-qty", newQty);
                    $qty.val(newQty).trigger("change");

                    // re-run group enforcement without modal
                    setTimeout(function () {
                        var groups = collectGroups();
                        if (!enforceGroups(groups, true, $qty)) {
                            triggerCartUpdate();
                        }
                    }, 50);

                    return;
                }

                // otherwise, show modal listing ALL numbers and pre-check the last N
                // If only one input to remove AND it's the only one (1 -> 0), remove it directly without modal
                if (removeCount === 1 && currentCount === 1) {
                    $allInputs.last().closest(".woo-limit-cart-item").remove();
                    $qty.data("old-qty", newQty);
                    $qty.val(newQty).trigger("change");
                    setTimeout(function () {
                        var groups = collectGroups();
                        if (!enforceGroups(groups, true, $qty)) {
                            triggerCartUpdate();
                        }
                    }, 50);
                    return;
                }

                var allNumbers = [];
                $allInputs.each(function () {
                    allNumbers.push(String($(this).val()));
                });
                var preChecked = [];
                if (removeCount > 0) {
                    $allInputs.slice(-removeCount).each(function () {
                        preChecked.push(String($(this).val()));
                    });
                }

                // open modal to confirm removal; modal will show product name and list ALL numbers
                openRemoveModal({
                    wrapper: $wrapper,
                    numbers: allNumbers,
                    preChecked: preChecked,
                    removeCount: removeCount,
                    currentCount: currentCount,
                    removeAll: removeCount === currentCount,
                });

                // do not finalize old-qty yet; it'll be set on confirm. If user cancels, modal cancel handler will revert qty.
                return;
            }

            // no change in limited inputs needed
            finalizeQty(newQty);
            setTimeout(function () {
                var groups = collectGroups();
                enforceGroups(groups, true);
            }, 50);
        });

        // Run on load (without modal confirmations)
        var groupsOnLoad = collectGroups();
        enforceGroups(groupsOnLoad, true);

        // Inline checkbox UI removed; modal-only selection will be used on qty decrease

        // Quantity +/- buttons: handle clicks while respecting validation state
        function wrapperHasErrors($wrapper, ignoreTypeYourNumber) {
            if (!$wrapper || !$wrapper.length) return false;
            var $err = $wrapper.find(".woo-limit-message.woo-limit-error");
            if ($err.length && $err.is(":visible")) {
                if (ignoreTypeYourNumber) {
                    var hasOtherErrors = false;
                    $err.each(function () {
                        if ($(this).text() !== "Type your number") {
                            hasOtherErrors = true;
                            return false;
                        }
                    });
                    if (hasOtherErrors) return true;
                } else {
                    return true;
                }
            }
            var $range = $wrapper.find(".woo-number-range.woo-limit-error");
            if ($range.length && !ignoreTypeYourNumber) return true;
            // any inputs with validation class indicating error?
            var $invalidInputs = $wrapper.find(".woo-limit.woo-limit-error");
            if ($invalidInputs.length) {
                if (ignoreTypeYourNumber) {
                    var hasOtherInputErrors = false;
                    $invalidInputs.each(function () {
                        var $msg = $(this).siblings(".woo-limit-message");
                        if ($msg.text() !== "Type your number") {
                            hasOtherInputErrors = true;
                            return false;
                        }
                    });
                    if (hasOtherInputErrors) return true;
                } else {
                    return true;
                }
            }
            return false;
        }

        function wrapperHasEmptyFields($wrapper) {
            if (!$wrapper || !$wrapper.length) return false;
            var empty = false;
            $wrapper
                .find(".woo-limit-cart-item input.woo-limit")
                .each(function () {
                    if (String($(this).val()).trim() === "") {
                        empty = true;
                        return false;
                    }
                });
            return empty;
        }

        function updateQtyButtonsState($row) {
            var $wrapper = $row.find(".woo-limit-field-wrapper").first();
            var invalid =
                wrapperHasErrors($wrapper) || wrapperHasEmptyFields($wrapper);
            var $plus = $row
                .find('.quantity-btn.plus, .quantity-btn[data-action="plus"]')
                .first();
            var $minus = $row
                .find('.quantity-btn.minus, .quantity-btn[data-action="minus"]')
                .first();
            if ($plus && $plus.length) $plus.prop("disabled", invalid);
            if ($minus && $minus.length)
                $minus.prop("disabled", wrapperHasErrors($wrapper, true));
        }

        // Attach listeners to keep buttons up-to-date when limited inputs change
        $(document).on(
            "input change blur",
            ".woo-limit-cart-item input.woo-limit",
            function () {
                var $inp = $(this);
                var $row = $inp.closest(".cart_item");
                updateQtyButtonsState($row);
                // also re-enable buttons globally for that row if no issues
            }
        );

        // Click handlers for quantity +/- buttons
        $(document).on(
            "click",
            '.quantity-btn.plus, .quantity-btn[data-action="plus"]',
            function (e) {
                var $btn = $(this);
                if ($btn.is(":disabled")) return;
                var $row = $btn.closest(".cart_item");
                var $qty = $row.find("input.qty").first();
                var $wrapper = $row.find(".woo-limit-field-wrapper").first();

                // If errors or empty fields exist, do not allow clicking
                if (
                    wrapperHasErrors($wrapper) ||
                    wrapperHasEmptyFields($wrapper)
                ) {
                    $btn.prop("disabled", true);
                    showClientNotice(
                        ijwlp_frontend.fix_errors_message ||
                        "Please fix errors and fill all Limited Edition Number inputs before changing the quantity."
                    );
                    return;
                }

                var current = parseInt($qty.val() || 0, 10) || 0;
                var max =
                    parseInt($wrapper.data("max-quantity") || "", 10) || null;
                var inputMax = parseInt($qty.attr("max") || "", 10) || null;

                // Calculate total quantity for this product group
                var totalQty = current;
                var productId = String($wrapper.data("product-id"));
                if (productId) {
                    var groups = collectGroups();
                    if (groups[productId]) {
                        var info = groups[productId];
                        var groupTotal = 0;
                        $.each(info.items, function (i, it) {
                            groupTotal += it.qty;
                        });
                        totalQty = groupTotal;
                    }
                }

                var triggeredMax = null; // Variable to track which max was triggered

                if ((max !== null && !isNaN(max) && totalQty >= max)) {
                    triggeredMax = 'max-quantity'; // max-quantity was triggered
                } else if ((inputMax !== null && !isNaN(inputMax) && current >= inputMax)) {
                    triggeredMax = 'inputMax'; // inputMax was triggered
                }

                if (triggeredMax) {
                    // Show error in the woo-limit-quantity-message div
                    var $quantityErrorDiv = $wrapper.find(".woo-limit-quantity-message");
                    var prodName =
                        $row
                            .find(".product-name a")
                            .first()
                            .text()
                            .trim() ||
                        $(".product_title").first().text().trim() ||
                        document.title ||
                        "";

                    var msg =
                        "Max quantity for " +
                        prodName +
                        " reached (" +
                        (triggeredMax === 'max-quantity' ? max : inputMax) +
                        ")";

                    // Display error message
                    // Clear existing timer
                    var timerId = $quantityErrorDiv.data("error-timer");
                    if (timerId) {
                        clearTimeout(timerId);
                        $quantityErrorDiv.removeData("error-timer");
                    }

                    $quantityErrorDiv
                        .text(msg)
                        .addClass("woo-limit-error")
                        .show();

                    // Auto-hide after 5 seconds
                    var newTimerId = setTimeout(function () {
                        $quantityErrorDiv.fadeOut(300, function () {
                            $(this).removeClass("woo-limit-error").removeData("error-timer");
                            // Re-enable buttons after error clears
                            updateQtyButtonsState($row);
                        });
                    }, 5000);
                    $quantityErrorDiv.data("error-timer", newTimerId);

                    return;
                }


                $qty.val(current + 1).trigger("change");
                // re-check buttons after change
                setTimeout(function () {
                    updateQtyButtonsState($row);
                }, 50);
            }
        );

        $(document).on(
            "click",
            '.quantity-btn.minus, .quantity-btn[data-action="minus"]',
            function (e) {
                var $btn = $(this);
                if ($btn.is(":disabled")) return;
                var $row = $btn.closest(".cart_item");
                var $qty = $row.find("input.qty").first();
                var $wrapper = $row.find(".woo-limit-field-wrapper").first();

                // If errors or empty fields exist, do not allow clicking
                if (wrapperHasErrors($wrapper, true)) {
                    $btn.prop("disabled", true);
                    showClientNotice(
                        ijwlp_frontend.fix_errors_message ||
                        "Please fix errors and fill all Limited Edition Number inputs before changing the quantity."
                    );
                    return;
                }

                var current = parseInt($qty.val() || 0, 10) || 0;
                if (current <= 0) return;
                $qty.val(Math.max(0, current - 1)).trigger("change");
                // re-check buttons after change
                setTimeout(function () {
                    updateQtyButtonsState($row);
                }, 50);
            }
        );

        // Prevent checkout if any limited edition input is empty
        $(document.body).on("click", ".checkout-button", function (e) {
            var isValid = true;
            var $firstError = null;

            $(".woo-limit-cart-item input.woo-limit").each(function () {
                var $input = $(this);
                var val = $input.val();
                if (!val || val.trim() === "") {
                    isValid = false;
                    var $msg = $input.siblings(".woo-limit-message");
                    $msg.text("Type your number").show();
                    $msg.addClass("woo-limit-error");
                    $input.addClass("woo-limit-error");

                    if (!$firstError) {
                        $firstError = $input;
                    }
                } else {
                    // Clear error if user fixed it but didn't trigger other events yet
                    $input.siblings(".woo-limit-message").hide();
                    $input.removeClass("woo-limit-error");
                }
            });

            if (!isValid) {
                e.preventDefault();
                e.stopImmediatePropagation();

                if ($firstError) {
                    $firstError.focus();
                }
                return false;
            }
        });
		 // Add backorder help icon functionality for cart page
		function addBackorderHelpIconCart() {
			// Find backorder notifications on cart page
			var $backorderNotifications = jQuery("p.backorder_notification");

			$backorderNotifications.each(function () {
				var $notification = jQuery(this);

				// Check if help icon already exists to avoid duplicates
				if ($notification.find(".backorder-help-icon").length === 0) {
					// Create the help icon
					var helpIcon =
						'<span class="backorder-help-icon help-icon" data-tooltip="Available on backorder means that this particular product/size is currently not in stock. However, it can be ordered and will be delivered as soon as available (usually 10 days).">?</span>';

					// Add the help icon after the text
					$notification.append(helpIcon);
				}
			});
		}
		addBackorderHelpIconCart();

		jQuery(document.body).on("wc_fragments_refreshed added_to_cart updated_cart_item removed_from_cart ", function () {
			setTimeout(function () {
				addBackorderHelpIconCart();
			}, 200);
		});

		jQuery(document).on("submit", ".woocommerce-cart-form", function () {
			setTimeout(function () {
				addBackorderHelpIconCart();
			}, 500);
		});
	});
})(jQuery);
