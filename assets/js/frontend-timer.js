/**
 * WooCommerce Limited Product - Timer Manager JavaScript
 *
 * Manages countdown timer for limited-edition products using localStorage
 * - Persists timer state across page refreshes
 * - Handles timer start, resume, and expiry
 * - Removes expired products from cart
 * - Integrates with shortcode display
 *
 * @package WooCommerce Limited Product
 */

(function ($) {
    "use strict";

    /**
     * Timer Manager Object
     * Encapsulates all timer functionality
     */
    window.ijwlpTimer = {
        // Configuration constants
        CHECK_INTERVAL: 1000, // Check every 1 second (milliseconds)
        DANGER_THRESHOLD: 180, // 3 minutes in seconds
        STORAGE_KEYS: {
            EXPIRY: "ijwlp_timer_expiry",
            ACTIVE: "ijwlp_timer_active",
        },

        // Debounce configuration for cart checks
        CART_CHECK_DEBOUNCE: 500, // milliseconds
        cartCheckDebounceTimer: null,

        // State variables
        intervalId: null,
        timerData: {
            expiry: null,
            isActive: false,
        },

        /**
         * Safe localStorage getter with fallback
         * Handles private browsing, storage quota, and security errors
         * @param {string} key - Storage key
         * @returns {string|null}
         */
        safeGetStorage: function (key) {
            try {
                return localStorage.getItem(key);
            } catch (e) {
                console.warn('IJWLP Timer: localStorage unavailable:', e.message);
                return null;
            }
        },

        /**
         * Safe localStorage setter
         * @param {string} key - Storage key
         * @param {string} value - Value to store
         * @returns {boolean} - Success status
         */
        safeSetStorage: function (key, value) {
            try {
                localStorage.setItem(key, value);
                return true;
            } catch (e) {
                console.warn('IJWLP Timer: localStorage unavailable:', e.message);
                return false;
            }
        },

        /**
         * Safe localStorage remover
         * @param {string} key - Storage key
         */
        safeRemoveStorage: function (key) {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                // Ignore removal errors
            }
        },

        /**
         * Initialize timer on page load
         * First fetch timer from backend (source of truth), then fallback to localStorage
         * Only continue timer if cart actually has limited products
         */
        init: function () {
            const self = this;

            // Setup cross-tab reload listener
            window.addEventListener('storage', function (e) {
                if (e.key === 'ijwlp_reload_tabs_signal') {
                    location.reload();
                }
            });

            // Check local storage first for immediate expiration handling using visual masking
            // This prevents "flash" of active state while waiting for backend validation
            const localExpiry = this.safeGetStorage(this.STORAGE_KEYS.EXPIRY);
            const localActive = this.safeGetStorage(this.STORAGE_KEYS.ACTIVE);

            if (localExpiry && localActive === "true") {
                const expiryTime = parseInt(localExpiry);
                const currentTime = Math.floor(Date.now() / 1000);

                if (expiryTime <= currentTime) {
                    // Timer expired locally!
                    // Apply visual mask immediately but WAIT for backend to confirm
                    $('.woocommerce-cart-form, .cart-collaterals').css({
                        'opacity': '0.3',
                        'pointer-events': 'none'
                    });

                    // Add local loader to indicate processing
                    if ($('#woo-limit-local-loader').length === 0) {
                        $('body').append(
                            '<div id="woo-limit-local-loader">' +
                            '<div class="woo-spinner"></div>' +
                            '</div>'
                        );
                    }
                }
            }

            // Fetch timer from backend first (handles login/logout persistence)
            this.fetchTimerFromBackend(function (timerData) {
                const hasLimitedProducts = timerData && timerData.has_limited_products;

                // Helper to remove mask
                const unmaskUI = function () {
                    $('.woocommerce-cart-form, .cart-collaterals').css({
                        'opacity': '',
                        'pointer-events': ''
                    });
                    $('#woo-limit-local-loader').remove();
                };

                if (timerData && timerData.expiry > 0 && timerData.is_active && hasLimitedProducts) {
                    // Backend has valid timer AND cart has limited products - resume timer
                    unmaskUI();
                    self.safeSetStorage(self.STORAGE_KEYS.EXPIRY, timerData.expiry.toString());
                    self.safeSetStorage(self.STORAGE_KEYS.ACTIVE, "true");
                    self.timerData.expiry = timerData.expiry;
                    self.timerData.isActive = true;
                    self.startTimer();
                } else if (self.hasActiveTimer() && hasLimitedProducts) {
                    // Fallback: localStorage has valid timer AND cart has limited products
                    unmaskUI();
                    const localExpiry = parseInt(self.safeGetStorage(self.STORAGE_KEYS.EXPIRY));
                    self.saveTimerToBackend(localExpiry);
                    self.resumeTimer();
                } else if (hasLimitedProducts) {
                    // Cart has limited products but no valid timer - remove them
                    // Note: We don't unmask here immediately; we let the product removal (AJAX)
                    // and likely subsequent page update/reload handle the UI state.
                    self.removeExpiredProducts(function () {
                        location.reload();
                    });
                } else {
                    // No limited products in cart - clear any stale timer
                    unmaskUI();
                    self.clearTimer();
                }
            });

            // Attach cart change listeners
            this.attachCartListeners();
            // Watch for visibility and perform an initial cart check
            this.setupVisibilityWatcher();
        },



        /**
         * Remove limited products from cart if no active timer exists
         * This is a fail-safe to ensure limited products don't stay in cart without a timer
         */
        removeProductsIfNoTimer: function () {
            const self = this;

            // Don't remove if user is actively editing (race condition prevention)
            if (window.ijwlpCartValidating || window.ijwlpPendingField) {
                return;
            }

            // Check if cart has limited products
            this.checkCartHasLimitedProducts(function (hasLimited) {
                if (hasLimited) {
                    self.removeExpiredProducts();
                }
            });
        },

        /**
         * Check if valid timer exists in localStorage
         *
         * @returns {boolean}
         */
        hasActiveTimer: function () {
            const expiry = this.safeGetStorage(this.STORAGE_KEYS.EXPIRY);
            const isActive = this.safeGetStorage(this.STORAGE_KEYS.ACTIVE);

            if (!expiry || isActive !== "true") {
                return false;
            }

            const expiryTime = parseInt(expiry);
            const currentTime = Math.floor(Date.now() / 1000);

            // Timer is still valid if expiry is in the future
            return expiryTime > currentTime;
        },

        /**
         * Resume an existing timer from localStorage
         */
        resumeTimer: function () {
            const expiry = this.safeGetStorage(this.STORAGE_KEYS.EXPIRY);

            if (!expiry) {
                this.clearTimer();
                return;
            }

            this.timerData.expiry = parseInt(expiry);
            this.timerData.isActive = true;

            // Start the countdown interval
            this.startTimer();
        },

        /**
         * Start or restart the timer
         * Called when limited product is added from product page
         *
         * @param {number} limitTimeMinutes - Time limit in minutes from settings
         */
        restartTimer: function (limitTimeMinutes) {

            // Calculate expiry timestamp (Unix timestamp in seconds)
            const currentTime = Math.floor(Date.now() / 1000);
            const limitTimeSeconds = limitTimeMinutes * 60;
            const expiry = currentTime + limitTimeSeconds;

            // Store in localStorage using safe methods
            this.safeSetStorage(this.STORAGE_KEYS.EXPIRY, expiry.toString());
            this.safeSetStorage(this.STORAGE_KEYS.ACTIVE, "true");

            // Also save to backend (user meta or session) for login/logout persistence
            this.saveTimerToBackend(expiry);

            this.timerData.expiry = expiry;
            this.timerData.isActive = true;

            // Start countdown
            this.startTimer();
        },


        /**
         * Begin countdown interval (1 second checks)
         */
        startTimer: function () {
            // Clear any existing interval
            if (this.intervalId !== null) {
                clearInterval(this.intervalId);
            }

            const self = this;

            // Update display immediately
            this.updateDisplay();

            // Set interval to check timer every second
            this.intervalId = setInterval(function () {
                const remaining = self.getTimeRemaining();

                if (remaining <= 0) {
                    // Timer expired
                    self.onTimerExpiry();
                } else {
                    // Update display
                    self.updateDisplay();
                }
            }, this.CHECK_INTERVAL);
        },

        /**
         * Stop timer and clear interval
         */
        stopTimer: function () {
            if (this.intervalId !== null) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }

            // (visibilitychange handler moved to setupVisibilityWatcher)
        },

        /**
         * Clear timer from storage and UI
         */
        clearTimer: function () {
            this.stopTimer();

            // Clear debounce timer if exists
            if (this.cartCheckDebounceTimer) {
                clearTimeout(this.cartCheckDebounceTimer);
                this.cartCheckDebounceTimer = null;
            }

            this.safeRemoveStorage(this.STORAGE_KEYS.EXPIRY);
            this.safeRemoveStorage(this.STORAGE_KEYS.ACTIVE);

            // Also clear from backend storage
            this.clearTimerFromBackend();

            this.timerData.expiry = null;
            this.timerData.isActive = false;

            // Hide timer display
            const $timerDisplay = $("#woo-limit-timer");
            if ($timerDisplay.length) {
                $timerDisplay.hide();
            }
        },

        /**
         * Fetch timer data from backend (user meta or WC session)
         * This is the source of truth for timer persistence across login/logout
         * 
         * @param {function} callback - Callback with timer data
         */
        fetchTimerFromBackend: function (callback) {
            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: {
                    action: "ijwlp_get_timer_data",
                    nonce: ijwlp_frontend.nonce
                },
                success: function (response) {
                    if (response.success && response.data) {
                        callback(response.data);
                    } else {
                        callback(null);
                    }
                },
                error: function () {
                    callback(null);
                }
            });
        },

        /**
         * Save timer expiry to backend (user meta or WC session)
         * 
         * @param {number} expiry - Unix timestamp when timer expires
         */
        saveTimerToBackend: function (expiry) {
            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: {
                    action: "ijwlp_set_timer_data",
                    nonce: ijwlp_frontend.nonce,
                    expiry: expiry
                }
                // Fire and forget - no need to wait for response
            });
        },

        /**
         * Clear timer from backend storage
         */
        clearTimerFromBackend: function () {
            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: {
                    action: "ijwlp_set_timer_data",
                    nonce: ijwlp_frontend.nonce,
                    expiry: 0
                }
                // Fire and forget
            });
        },


        /**
         * Get remaining time in seconds
         *
         * @returns {number}
         */
        getTimeRemaining: function () {
            if (!this.timerData.expiry) {
                return 0;
            }

            const currentTime = Math.floor(Date.now() / 1000);
            const remaining = this.timerData.expiry - currentTime;

            return remaining > 0 ? remaining : 0;
        },

        /**
         * Format seconds to mm:ss format
         *
         * @param {number} seconds
         * @returns {string}
         */
        formatTime: function (seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;

            // Pad with leading zeros
            const paddedMinutes = String(minutes).padStart(2, "0");
            const paddedSecs = String(secs).padStart(2, "0");

            return paddedMinutes + ":" + paddedSecs;
        },

        /**
         * Update timer display in UI
         * Updates separate minutes and seconds boxes
         */
        updateDisplay: function () {
            const $timerDisplay = $("#woo-limit-timer");
            const $timerContainer = $(".timer-container");

            if (!$timerDisplay.length) {
                return;
            }

            const remaining = this.getTimeRemaining();
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;

            if (remaining <= 0) {
                // Display expired
                $("#timer-minutes", $timerDisplay).text("00");
                $("#timer-seconds", $timerDisplay).text("00");
                $timerDisplay.addClass("expired");
                return;
            }

            // Pad with leading zeros
            const paddedMinutes = String(minutes).padStart(2, "0");
            const paddedSeconds = String(seconds).padStart(2, "0");

            // Update minutes and seconds boxes separately
            $("#timer-minutes", $timerDisplay).text(paddedMinutes);
            $("#timer-seconds", $timerDisplay).text(paddedSeconds);

            // Add/remove danger class if less than 3 minutes
            if (remaining < this.DANGER_THRESHOLD) {
                $timerContainer.addClass("danger");
            } else {
                $timerContainer.removeClass("danger");
            }

            // Show timer if hidden
            if ($timerDisplay.is(":hidden")) {
                $timerDisplay.show();
            }
        },

        /**
         * Handle timer expiry - remove products and reload
         */
        onTimerExpiry: function () {
            this.stopTimer();

            // Use the helper method to remove products and reload
            this.removeExpiredProducts(function () {
                // Clear timer from localStorage
                window.ijwlpTimer.clearTimer();
                location.reload();
            });
        },

        /**
         * Attach listeners for cart changes
         * If user removes all limited items, clear timer
         */
        attachCartListeners: function () {
            const self = this;

            // Handler function to check cart and clear timer if needed
            // Uses debounce to prevent race conditions from rapid events
            const handleCartCheck = function () {
                // Clear any pending debounce timer
                if (self.cartCheckDebounceTimer) {
                    clearTimeout(self.cartCheckDebounceTimer);
                }

                // Debounce: wait before actually checking to let state settle
                self.cartCheckDebounceTimer = setTimeout(function () {
                    // Skip if cart.js is in the middle of validation (race condition prevention)
                    if (window.ijwlpCartValidating || window.ijwlpPendingField) {
                        return;
                    }

                    self.checkCartHasLimitedProducts(function (hasLimited) {
                        if (!hasLimited) {
                            self.clearTimer();
                        }
                    });
                }, self.CART_CHECK_DEBOUNCE);
            };

            // Listen for cart updated event (WooCommerce standard)
            $(document).on("wc_cart_emptied updated_cart_totals", handleCartCheck);

            // Listen for mini cart fragment updates (WooCommerce mini cart)
            $(document.body).on(
                "wc_fragments_loaded wc_fragments_refreshed",
                handleCartCheck
            );

            // Listen for product removed from cart event
            $(document).on("woocommerce_cart_item_removed", handleCartCheck);

            // Listen for product added to cart event to trigger reload in other tabs
            $(document.body).on("added_to_cart", function () {
                self.safeSetStorage('ijwlp_reload_tabs_signal', Date.now());
            });
        },

        /**
         * Watch for tab visibility changes and check cart for limited products
         * - On page load perform a cart check
         * - When user switches back to the tab, re-check the cart
         */
        setupVisibilityWatcher: function () {
            const self = this;

            // Initial check on load: if no limited products, clear timer
            this.checkCartHasLimitedProducts(function (hasLimited) {
                if (!hasLimited) {
                    self.clearTimer();
                }
            });

            // Listen for tab visibility changes (user switches back to tab)
            // Ensure we only add the handler once
            if (!this._visibilityHandlerAdded) {
                document.addEventListener("visibilitychange", function () {
                    if (document.visibilityState === "visible") {
                        // Skip checks if user is actively editing (race condition prevention)
                        if (window.ijwlpCartValidating || window.ijwlpPendingField) {
                            return;
                        }

                        // If no active timer exists, remove limited products from cart
                        if (!self.hasActiveTimer()) {
                            self.removeProductsIfNoTimer();
                        } else {
                            // If timer exists, just check if cart still has limited products
                            self.checkCartHasLimitedProducts(function (
                                hasLimited
                            ) {
                                if (!hasLimited) {
                                    self.clearTimer();
                                }
                            });
                        }
                    }
                });

                this._visibilityHandlerAdded = true;
            }
        },
        /**
         * Check if cart contains limited products
         * Utility method for other parts of the system
         *
         * @param {function} callback - Callback function (result: boolean)
         */
        checkCartHasLimitedProducts: function (callback) {
            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: {
                    action: "ijwlp_cart_has_limited_products",
                },
                success: function (response) {
                    if (typeof callback === "function") {
                        callback(response.success && response.data.has_limited);
                    }
                },
                error: function () {
                    if (typeof callback === "function") {
                        callback(false);
                    }
                },
            });
        },

        /**
         * Remove expired limited products from cart via AJAX
         * 
         * @param {function} callback - Optional callback on completion
         */
        removeExpiredProducts: function (callback) {
            // Check if nonce is available
            const nonce = ijwlp_frontend.nonce || "";

            if (!nonce) {
                if (typeof callback === "function") {
                    callback();
                }
                return;
            }

            // AJAX: Remove expired limited products
            $.ajax({
                url: ijwlp_frontend.ajax_url,
                type: "POST",
                data: {
                    action: "ijwlp_remove_expired_limited_products",
                    nonce: nonce,
                },
                success: function (response) {
                    // Trigger WooCommerce cart update event to refresh cart table
                    if (typeof $(document.body).trigger === "function") {
                        $(document.body).trigger("updated_cart_totals");
                    }

                    if (typeof callback === "function") {
                        callback();
                    }
                },
                error: function (error) {
                    // Even on error, we should probably proceed with callback (e.g. reload)
                    if (typeof $(document.body).trigger === "function") {
                        $(document.body).trigger("updated_cart_totals");
                    }

                    if (typeof callback === "function") {
                        callback();
                    }
                },
            });
        },
    };

    /**
     * Initialize timer when DOM is ready
     */
    $(document).ready(function () {
        window.ijwlpTimer.init();
    });
})(jQuery);
