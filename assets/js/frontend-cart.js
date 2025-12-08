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
        var cartAvailableTimer = null;

        // ==================== VALIDATION TRACKER ====================
        // Tracks the currently validating/editing field to enable sequential validation
        // Only one field can be edited and validated at a time
        var validationTracker = {
            currentlyValidating: null,
            lockedField: null,  // Track the actively editing field (locked immediately on focus)
            pendingNewField: null,  // Track newly added field that must be completed or removed

            // Lock a field immediately when user starts editing
            lockField: function (cartKey, index, $input) {
                this.lockedField = {
                    cartKey: cartKey,
                    index: index,
                    $input: $input
                };
                // Immediately disable all other fields and controls
                $(".woo-limit-cart-item input.woo-limit").not($input).prop("disabled", true);
                $(".quantity-btn").prop("disabled", true);
                $("input.qty").prop("disabled", true);
                $("button[name='update_cart'], input[name='update_cart']").prop("disabled", true);
                $(".checkout-button").addClass("disabled").prop("disabled", true);
            },

            // Unlock only when validation succeeds OR cart updates
            unlockField: function () {
                this.lockedField = null;
                // Re-enable all fields
                $(".woo-limit-cart-item input.woo-limit").prop("disabled", false);
                $("input.qty").prop("disabled", false);
                $(".checkout-button").removeClass("disabled").prop("disabled", false);
                // Restore button states based on current validation state
                $(".cart_item").each(function () { updateQtyButtonsState($(this)); });
            },

            // Check if editing is locked
            isLocked: function () {
                return this.lockedField !== null;
            },

            // Check if this specific input is the locked one
            isLockedInput: function ($input) {
                return this.lockedField && this.lockedField.$input.is($input);
            },

            // Set a pending new field (must be completed or will be removed)
            setPendingNewField: function (cartKey, $input, $wrapper, $qty) {
                this.pendingNewField = {
                    cartKey: cartKey,
                    $input: $input,
                    $wrapper: $wrapper,
                    $qty: $qty
                };
            },

            // Clear pending new field (called after successful validation)
            clearPendingNewField: function () {
                this.pendingNewField = null;
            },

            // Check if there's a pending new field
            hasPendingNewField: function () {
                return this.pendingNewField !== null;
            },

            // Check if this input is the pending new field
            isPendingInput: function ($input) {
                return this.pendingNewField && this.pendingNewField.$input.is($input);
            },

            // Remove the pending new field and revert quantity
            removePendingNewField: function () {
                if (!this.pendingNewField) return;

                var pending = this.pendingNewField;
                var $item = pending.$input.closest(".woo-limit-cart-item");
                var $qty = pending.$qty;
                var currentQty = safeInt($qty.val());

                // Remove the field
                $item.remove();

                // Revert quantity
                var newQty = Math.max(0, currentQty - 1);
                $qty.val(newQty);
                $qty.data("old-qty", newQty);

                this.pendingNewField = null;
                this.unlockField();
            },

            // Set a field as currently validating
            setValidating: function (cartKey, index, $input) {
                this.currentlyValidating = {
                    cartKey: cartKey,
                    index: index,
                    $input: $input
                };
            },

            // Clear the validating state
            clearValidating: function () {
                this.currentlyValidating = null;
            },

            // Check if a field is currently being validated
            isValidating: function () {
                return this.currentlyValidating !== null;
            },

            // Check if there are any empty fields in the cart that should have values
            hasEmptyFields: function () {
                var hasEmpty = false;
                $(".woo-limit-cart-item input.woo-limit").each(function () {
                    if ($(this).val().trim() === "") {
                        hasEmpty = true;
                        return false; // break
                    }
                });
                return hasEmpty;
            }
        };

        // ==================== UTILITY FUNCTIONS ====================

        // Parse integer with fallback
        function safeInt(val, fallback) {
            var parsed = parseInt(val || "", 10);
            return isNaN(parsed) ? (fallback || 0) : parsed;
        }

        // Get product name from various sources
        function getProductName($row, $wrapper) {
            var name = "";
            if ($row && $row.length) {
                name = $row.find(".product-name a").first().text().trim();
            }
            if (!name && $wrapper && $wrapper.length) {
                name = $wrapper.closest(".cart_item").find(".product-name a").first().text().trim();
            }
            if (!name) {
                name = $(".product_title").first().text().trim() || document.title || "Product";
            }
            return name;
        }

        // Show timed error message in element
        function showTimedError($el, message, duration, onHide) {
            if (!$el || !$el.length) return false;

            var timerId = $el.data("error-timer");
            if (timerId) {
                clearTimeout(timerId);
                $el.removeData("error-timer");
            }

            $el.text(message).addClass("woo-limit-error").show();

            var newTimerId = setTimeout(function () {
                $el.fadeOut(300, function () {
                    $(this).removeClass("woo-limit-error").removeData("error-timer");
                    if (typeof onHide === "function") onHide();
                });
            }, duration || 5000);
            $el.data("error-timer", newTimerId);
            return true;
        }

        // Show client notice at top of page
        function showClientNotice(message) {
            var $wrapper = $(".woocommerce-notices-wrapper");
            if (!$wrapper.length) {
                $wrapper = $('<div class="woocommerce-notices-wrapper" />').prependTo(".woocommerce");
            }
            var $msg = $('<div class="woocommerce-message" role="alert"></div>').text(message);
            $wrapper.prepend($msg);
            setTimeout(function () {
                $msg.fadeOut(300, function () { $(this).remove(); });
            }, 6000);
        }

        // Enforce groups and optionally trigger cart update
        function enforceAndUpdate($qty, triggerUpdate) {
            setTimeout(function () {
                var groups = collectGroups();
                if (!enforceGroups(groups, true, $qty) && triggerUpdate) {
                    triggerCartUpdate();
                }
            }, 50);
        }

        // ==================== CART FIELD CONTROL ====================

        function setCartFieldsState(disabled) {
            var state = !!disabled;
            $("input.qty").prop("disabled", state);
            $(".quantity-btn").prop("disabled", state);
            $(".woo-limit-cart-item input.woo-limit").prop("disabled", state);
            $("button[name='update_cart'], input[name='update_cart']").prop("disabled", state);
            $(".woo-coupon-btn").prop("disabled", state);

            if (state) {
                $(".checkout-button").addClass("disabled").prop("disabled", true);
                $(".quantity").css("pointer-events", "none");
            } else {
                $(".quantity").css("pointer-events", "");
                $(".checkout-button").removeClass("disabled").prop("disabled", false);
                $(".cart_item").each(function () { updateQtyButtonsState($(this)); });
            }
        }

        function disableAllCartFields() { setCartFieldsState(true); }
        function enableAllCartFields() { setCartFieldsState(false); }

        // ==================== LIMIT INPUT SETUP ====================

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
                getProductId: function () { return productId; },
                getVariationId: function () { return 0; },
                getCartItemKey: function () { return $input.data("cart-key"); },
                onStart: function () {
                    // Mark field as validating (lock should already be in place from focus)
                    var index = $input.data("index") || 0;
                    var cKey = $input.data("cart-key");
                    validationTracker.setValidating(cKey, index, $input);

                    // Ensure lock is in place (in case focus didn't trigger it)
                    if (!validationTracker.isLocked()) {
                        validationTracker.lockField(cKey, index, $input);
                    }
                },
                onEnd: function () {
                    // Clear validating state only - don't unlock yet
                    // Unlock happens on successful validation or cart update
                    validationTracker.clearValidating();
                },
                onComplete: function (data) {
                    if (data && data.available) {
                        $input.data("old-value", $input.val().trim());

                        // Clear pending new field status on successful validation
                        if (validationTracker.isPendingInput($input)) {
                            validationTracker.clearPendingNewField();
                        }

                        // Unlock and trigger cart update after successful validation
                        validationTracker.unlockField();
                        setTimeout(function () {
                            triggerCartUpdate();
                        }, 300);
                    }
                    // If validation fails, stay locked so user must fix the same field
                },
            });

            try {
                if (window.IJWLP_Frontend_Common && typeof window.IJWLP_Frontend_Common.attachAutocomplete === "function") {
                    window.IJWLP_Frontend_Common.attachAutocomplete($input);
                }
            } catch (e) { /* fail silently */ }
        }

        function initializeAllLimitInputs() {
            $(".woo-limit-cart-item-wrapper .woo-limit").each(function () {
                setupLimitInput($(this));
            });
        }

        initializeAllLimitInputs();
        $(document.body).on("updated_cart_totals", function () {
            // Unlock and clear all states on cart update
            validationTracker.unlockField();
            validationTracker.clearValidating();
            initializeAllLimitInputs();
        });

        // ==================== CART UPDATE TRIGGER ====================

        function triggerCartUpdate() {
            var $updateBtn = $("button[name='update_cart'], input[name='update_cart']").first();
            if ($updateBtn.length) {
                setTimeout(function () { $updateBtn.trigger("click"); }, 150);
                return;
            }
            var $cartForm = $(".woocommerce-cart-form").first();
            if ($cartForm.length) {
                setTimeout(function () { $cartForm.submit(); }, 150);
                return;
            }
            var $form = $("form.cart").first();
            if ($form.length) {
                setTimeout(function () { $form.submit(); }, 150);
            }
        }

        // ==================== GROUP COLLECTION & ENFORCEMENT ====================

        function collectGroups() {
            var groups = {};
            $(".woo-limit-field-wrapper").each(function () {
                var $w = $(this);
                var parentId = String($w.data("product-id"));
                var cartKey = $w.data("cart-item-key");
                var max = safeInt($w.data("max-quantity"), null);

                var $qty = $('input[name="cart[' + cartKey + '][qty]"]');
                if (!$qty.length) {
                    $qty = $w.closest(".cart_item").find("input.qty");
                }

                var qtyVal = $qty.length ? safeInt($qty.val()) : 0;

                if (!groups[parentId]) groups[parentId] = { items: [], max: max };
                groups[parentId].items.push({ cartKey: cartKey, $qty: $qty, qty: qtyVal, $wrapper: $w });
            });
            return groups;
        }

        function enforceGroups(groups, showMessage, $triggerTarget) {
            var limitEnforced = false;
            $.each(groups, function (parentId, info) {
                var max = info.max;
                if (!max) return;

                var total = 0;
                $.each(info.items, function (i, it) { total += it.qty; });
                if (total <= max) return;

                limitEnforced = true;
                var excess = total - max;
                var rev = info.items.slice().reverse();
                $.each(rev, function (i, it) {
                    if (excess <= 0 || !it.$qty.length) return;
                    var rowQty = safeInt(it.$qty.val());
                    if (rowQty <= 0) return;
                    var reduce = Math.min(rowQty, excess);
                    it.$qty.val(rowQty - reduce);
                    excess -= reduce;
                });

                if (showMessage) {
                    var targetItem = info.items[0];
                    if ($triggerTarget && info.items) {
                        $.each(info.items, function (i, it) {
                            if (it.$qty.is($triggerTarget)) { targetItem = it; return false; }
                        });
                    }

                    var prodName = getProductName(null, targetItem.$wrapper);
                    var msg = "Max quantity for " + prodName + " reached (" + max + ")";
                    var shownInline = false;

                    if (targetItem && targetItem.$wrapper && targetItem.$wrapper.length) {
                        var $rowErr = targetItem.$wrapper.find(".woo-limit-quantity-message").first();
                        shownInline = showTimedError($rowErr, msg, 5000, function () {
                            var $row = targetItem.$wrapper.closest(".cart_item");
                            if ($row.length) updateQtyButtonsState($row);
                        });
                    }
                    if (!shownInline) showClientNotice(msg);
                }
            });
            return limitEnforced;
        }

        // Capture phase listener
        if (document.body.addEventListener) {
            document.body.addEventListener("change", function (e) {
                if (!e.target || !e.target.classList.contains("qty")) return;
                var $target = $(e.target);
                var valBefore = $target.val();
                var groups = collectGroups();
                enforceGroups(groups, true, $target);
                var valAfter = $target.val();

                if (String(valBefore) !== String(valAfter)) {
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.preventDefault();
                    $target.data("old-qty", valAfter);
                }
            }, true);
        }

        $("input.qty").each(function () {
            $(this).data("old-qty", safeInt($(this).val()));
        });

        // ==================== REMOVAL MODAL ====================

        var pendingRemoval = null;

        function openRemoveModal(action) {
            pendingRemoval = action;
            var $modal = $("#field-selection-modal");
            var productName = action.productName || (action.wrapper ? action.wrapper.closest(".cart_item").find(".product-name a").text() : "");

            $modal.find(".field-selection-modal-content h3").text(productName);
            var $listContainer = $("#field-selection-list").empty();

            if (action.removeAll) {
                closeRemoveModal();
                return;
            }

            for (var i = 0; i < action.numbers.length; i++) {
                var num = action.numbers[i] || "";
                var esc = $("<div/>").text(num).html();
                var $option = $('<div class="field-option"></div>');
                var $flex = $('<div style="display: flex; align-items: center;"></div>');
                var $cb = $('<input type="checkbox" name="field-selection" class="woo-limit-modal-select" style="margin-right: 10px;" />').attr("data-number", num).val(num);
                var $labelDiv = $('<div><strong>' + esc + '</strong></div>');
                $flex.append($cb).append($labelDiv);
                $option.append($flex);
                $listContainer.append($option);
            }
            $modal.show();
        }

        function revertQty() {
            if (pendingRemoval && pendingRemoval.wrapper) {
                var $qty = pendingRemoval.wrapper.closest(".cart_item").find("input.qty");
                var oldQty = $qty.data("old-qty");
                if (oldQty !== undefined) $qty.val(oldQty);
            }
        }

        function closeRemoveModal() {
            $("#field-selection-modal").hide();
            pendingRemoval = null;
        }

        $(document).on("click", "#remove-selected-field", function () {
            if (!pendingRemoval) return closeRemoveModal();
            var action = pendingRemoval;
            var $wrapper = action.wrapper;
            var $qty = $wrapper.closest(".cart_item").find("input.qty");
            var originalCount = action.currentCount || $wrapper.find(".woo-limit-cart-item input.woo-limit").length;
            var $modal = $("#field-selection-modal");
            var $checked = $modal.find(".woo-limit-modal-select:checked");

            var performed = false;
            if ($checked.length) {
                var removed = 0;
                $checked.each(function () {
                    var num = $(this).data("number");
                    var $target = $wrapper.find(".woo-limit-cart-item input.woo-limit").filter(function () {
                        return String($(this).val()) === String(num);
                    }).closest(".woo-limit-cart-item");
                    if ($target.length) { $target.remove(); removed++; }
                });
                var newQty = Math.max(0, originalCount - removed);
                $qty.val(newQty).trigger("change");
                $qty.data("old-qty", newQty);
                performed = removed > 0;
            } else if (action.removeAll) {
                $wrapper.find(".woo-limit-cart-item").remove();
                $qty.val(0).trigger("change");
                $qty.data("old-qty", 0);
                performed = true;
            }

            closeRemoveModal();
            if (performed) triggerCartUpdate();
        });

        $(document).on("click", "#cancel-field-selection, .cancel-field-selection", function () {
            revertQty();
            closeRemoveModal();
        });

        $(document).on("click", "#field-selection-modal", function (e) {
            if (e.target.id === "field-selection-modal") {
                revertQty();
                $(this).hide();
                pendingRemoval = null;
            }
        });

        // ==================== QUANTITY CHANGE HANDLER ====================

        $(document).on("change", "input.qty", function () {
            var $qty = $(this);
            var name = $qty.attr("name") || "";
            var matches = name.match(/cart\[([^\]]+)\]\[qty\]/);
            var cartKey = matches ? matches[1] : null;
            var $wrapper = cartKey
                ? $('.woo-limit-field-wrapper[data-cart-item-key="' + cartKey + '"]')
                : $qty.closest(".cart_item").find(".woo-limit-field-wrapper");

            var newQty = safeInt($qty.val());
            var oldQty = safeInt($qty.data("old-qty"));

            function finalizeQty(q) { $qty.data("old-qty", q); }

            if (!$wrapper || !$wrapper.length) {
                finalizeQty(newQty);
                enforceAndUpdate($qty, false);
                return;
            }

            var isLimited = $wrapper.data("is-limited");
            if (isLimited !== 'yes') {
                finalizeQty(newQty);
                enforceAndUpdate($qty, true);
                return;
            }

            var max = safeInt($wrapper.data("max-quantity"), null);
            var $inputs = $wrapper.find(".woo-limit-cart-item input.woo-limit");
            var currentCount = $inputs.length;

            // Increase quantity
            if (newQty > currentCount) {
                var toAdd = newQty - currentCount;
                if (max !== null) toAdd = Math.min(toAdd, Math.max(0, max - currentCount));

                var emptyCount = $wrapper.find(".woo-limit-cart-item input.woo-limit").filter(function () {
                    return String($(this).val()).trim() === "";
                }).length;

                if (emptyCount > 0) {
                    showClientNotice(ijwlp_frontend.fill_all_inputs_message || "Please fill all existing Limited Edition Number inputs before increasing the quantity.");
                    $qty.val(oldQty);
                    finalizeQty(oldQty);
                    return;
                }

                // Only add one field at a time for limited products
                var index = currentCount;
                var $div = $('<div class="woo-limit-cart-item woo-input-single gt-2"></div>');
                var $inp = $('<input type="number" class="woo-limit woo-cart-items" name="woo_limit[' + cartKey + '][]" />');
                $inp.attr({ "data-cart-key": cartKey, "data-index": index, "data-old-value": "" });
                if ($wrapper.data("start")) $inp.attr("min", $wrapper.data("start"));
                if ($wrapper.data("end")) $inp.attr("max", $wrapper.data("end"));
                var $errDiv = $('<div class="woo-limit-message" style="display: none;"></div>');
                $div.append($inp).append($errDiv);
                var $avail = $wrapper.find(".woo-limit-available-numbers").first();
                if ($avail.length) $avail.before($div); else $wrapper.append($div);
                setupLimitInput($inp);

                // Mark as pending new field - must be completed or will be removed
                validationTracker.setPendingNewField(cartKey, $inp, $wrapper, $qty);

                // Lock to this field and auto-focus it
                validationTracker.lockField(cartKey, index, $inp);
                setTimeout(function () {
                    $inp.focus();
                }, 100);

                // Only increase qty by 1 (not toAdd)
                $qty.val(currentCount + 1);
                finalizeQty(currentCount + 1);
                return;
            }

            // Decrease quantity
            if (newQty < currentCount) {
                var removeCount = currentCount - newQty;
                var $allInputs = $wrapper.find(".woo-limit-cart-item input.woo-limit");
                var $lastInputs = $allInputs.slice(-removeCount);
                var lastEmpty = true;
                $lastInputs.each(function () {
                    if (String($(this).val()).trim() !== "") { lastEmpty = false; return false; }
                });

                if (removeCount > 0 && lastEmpty) {
                    $lastInputs.closest(".woo-limit-cart-item").remove();
                    $qty.data("old-qty", newQty);
                    $qty.val(newQty).trigger("change");

                    // Check if remaining fields all have values
                    // If any remaining field is empty, don't trigger cart update yet
                    var hasEmptyRemaining = validationTracker.hasEmptyFields();
                    if (hasEmptyRemaining) {
                        return;
                    }

                    enforceAndUpdate($qty, true);
                    return;
                }

                if (removeCount === 1 && currentCount === 1) {
                    $allInputs.last().closest(".woo-limit-cart-item").remove();
                    $qty.data("old-qty", newQty);
                    $qty.val(newQty).trigger("change");
                    enforceAndUpdate($qty, true);
                    return;
                }

                var allNumbers = [];
                $allInputs.each(function () { allNumbers.push(String($(this).val())); });
                var preChecked = [];
                if (removeCount > 0) {
                    $allInputs.slice(-removeCount).each(function () { preChecked.push(String($(this).val())); });
                }

                openRemoveModal({
                    wrapper: $wrapper,
                    numbers: allNumbers,
                    preChecked: preChecked,
                    removeCount: removeCount,
                    currentCount: currentCount,
                    removeAll: removeCount === currentCount,
                });
                return;
            }

            finalizeQty(newQty);
            enforceAndUpdate(null, false);
        });

        var groupsOnLoad = collectGroups();
        enforceGroups(groupsOnLoad, true);

        // ==================== QUANTITY BUTTONS STATE ====================

        function wrapperHasErrors($wrapper, ignoreTypeYourNumber) {
            if (!$wrapper || !$wrapper.length) return false;

            var checkErrors = function ($elements, getTextFn) {
                var hasErrors = false;
                $elements.each(function () {
                    var text = getTextFn ? getTextFn($(this)) : $(this).text();
                    if (!ignoreTypeYourNumber || text !== "Type your number") {
                        hasErrors = true;
                        return false;
                    }
                });
                return hasErrors;
            };

            var $err = $wrapper.find(".woo-limit-message.woo-limit-error:visible");
            if ($err.length && checkErrors($err)) return true;

            var $range = $wrapper.find(".woo-number-range.woo-limit-error");
            if ($range.length && !ignoreTypeYourNumber) return true;

            var $invalidInputs = $wrapper.find(".woo-limit.woo-limit-error");
            if ($invalidInputs.length) {
                return checkErrors($invalidInputs, function ($inp) {
                    return $inp.siblings(".woo-limit-message").text();
                });
            }
            return false;
        }

        function wrapperHasEmptyFields($wrapper) {
            if (!$wrapper || !$wrapper.length) return false;
            var empty = false;
            $wrapper.find(".woo-limit-cart-item input.woo-limit").each(function () {
                if (String($(this).val()).trim() === "") { empty = true; return false; }
            });
            return empty;
        }

        function updateQtyButtonsState($row) {
            var $wrapper = $row.find(".woo-limit-field-wrapper").first();
            var invalid = wrapperHasErrors($wrapper) || wrapperHasEmptyFields($wrapper);
            var $plus = $row.find('.quantity-btn.plus, .quantity-btn[data-action="plus"]').first();
            var $minus = $row.find('.quantity-btn.minus, .quantity-btn[data-action="minus"]').first();
            if ($plus.length) $plus.prop("disabled", invalid);
            if ($minus.length) $minus.prop("disabled", wrapperHasErrors($wrapper, true));
        }

        $(document).on("input change blur", ".woo-limit-cart-item input.woo-limit", function () {
            updateQtyButtonsState($(this).closest(".cart_item"));
        });

        // Lock field immediately on focus - prevents switching to other fields
        $(document).on("focus", ".woo-limit-cart-item input.woo-limit", function () {
            var $focused = $(this);

            // If there's a pending new field and user tries to focus a different field
            if (validationTracker.hasPendingNewField() && !validationTracker.isPendingInput($focused)) {
                // Remove the pending new field and revert quantity
                validationTracker.removePendingNewField();
                showClientNotice("New field removed - please complete one field at a time.");
                // Focus will now proceed to the clicked field
            }

            // If another field is locked (not pending), block focus completely
            if (validationTracker.isLocked() && !validationTracker.isLockedInput($focused)) {
                $focused.blur();
                showClientNotice("Please complete the current field first.");
                return false;
            }

            // Lock this field immediately
            var cartKey = $focused.data("cart-key");
            var index = $focused.data("index") || 0;
            validationTracker.lockField(cartKey, index, $focused);
        });

        // On blur: DON'T unlock - stay locked until validation succeeds or cart updates
        $(document).on("blur", ".woo-limit-cart-item input.woo-limit", function () {
            // Intentionally do nothing here - field stays locked
            // Unlock only happens via:
            // 1. Successful validation (onComplete with data.available)
            // 2. Cart update (updated_cart_totals event)
        });

        // ==================== QUANTITY BUTTON HANDLERS ====================

        function handleQtyButtonClick($btn, isPlus) {
            if ($btn.is(":disabled")) return;
            var $row = $btn.closest(".cart_item");
            var $qty = $row.find("input.qty").first();
            var $wrapper = $row.find(".woo-limit-field-wrapper").first();

            var checkErrors = isPlus ? (wrapperHasErrors($wrapper) || wrapperHasEmptyFields($wrapper)) : wrapperHasErrors($wrapper, true);
            if (checkErrors) {
                $btn.prop("disabled", true);
                showClientNotice(ijwlp_frontend.fix_errors_message || "Please fix errors and fill all Limited Edition Number inputs before changing the quantity.");
                return;
            }

            var current = safeInt($qty.val());

            if (isPlus) {
                var max = safeInt($wrapper.data("max-quantity"), null);
                var inputMax = safeInt($qty.attr("max"), null);
                var productId = String($wrapper.data("product-id"));
                var totalQty = current;

                if (productId) {
                    var groups = collectGroups();
                    if (groups[productId]) {
                        var groupTotal = 0;
                        $.each(groups[productId].items, function (i, it) { groupTotal += it.qty; });
                        totalQty = groupTotal;
                    }
                }

                var triggeredMax = null;
                if (max !== null && totalQty >= max) triggeredMax = max;
                else if (inputMax !== null && current >= inputMax) triggeredMax = inputMax;

                if (triggeredMax) {
                    var prodName = getProductName($row, $wrapper);
                    var msg = "Max quantity for " + prodName + " reached (" + triggeredMax + ")";
                    var $quantityErrorDiv = $wrapper.find(".woo-limit-quantity-message");
                    showTimedError($quantityErrorDiv, msg, 5000, function () { updateQtyButtonsState($row); });
                    return;
                }
                $qty.val(current + 1).trigger("change");
            } else {
                if (current <= 0) return;
                $qty.val(Math.max(0, current - 1)).trigger("change");
            }

            setTimeout(function () { updateQtyButtonsState($row); }, 50);
        }

        $(document).on("click", '.quantity-btn.plus, .quantity-btn[data-action="plus"]', function () {
            handleQtyButtonClick($(this), true);
        });

        $(document).on("click", '.quantity-btn.minus, .quantity-btn[data-action="minus"]', function () {
            handleQtyButtonClick($(this), false);
        });

        // ==================== CHECKOUT VALIDATION ====================

        $(document.body).on("click", ".checkout-button", function (e) {
            var isValid = true;
            var $firstError = null;

            $(".woo-limit-cart-item input.woo-limit").each(function () {
                var $input = $(this);
                var val = $input.val();
                var $msg = $input.siblings(".woo-limit-message");

                if (!val || val.trim() === "") {
                    isValid = false;
                    $msg.text("Type your number").addClass("woo-limit-error").show();
                    $input.addClass("woo-limit-error");
                    if (!$firstError) $firstError = $input;
                } else {
                    $msg.hide();
                    $input.removeClass("woo-limit-error");
                }
            });

            if (!isValid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if ($firstError) $firstError.focus();
                return false;
            }
        });

        // ==================== BACKORDER HELP ICON ====================

        function addBackorderHelpIconCart() {
            jQuery("p.backorder_notification").each(function () {
                var $notification = jQuery(this);
                if ($notification.find(".backorder-help-icon").length === 0) {
                    $notification.append('<span class="backorder-help-icon help-icon" data-tooltip="Available on backorder means that this particular product/size is currently not in stock. However, it can be ordered and will be delivered as soon as available (usually 10 days).">?</span>');
                }
            });
        }

        addBackorderHelpIconCart();

        jQuery(document.body).on("wc_fragments_refreshed added_to_cart updated_cart_item removed_from_cart", function () {
            setTimeout(addBackorderHelpIconCart, 500);
        });

        jQuery(document).on("submit", ".woocommerce-cart-form", function () {
            setTimeout(addBackorderHelpIconCart, 500);
        });
    });
})(jQuery);
