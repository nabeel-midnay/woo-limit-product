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

        // State variables
        intervalId: null,
        timerData: {
            expiry: null,
            isActive: false,
        },

        /**
         * Initialize timer on page load
         * Check if timer exists and resume if active
         */
        init: function () {
            // Check if timer already exists in localStorage
            if (this.hasActiveTimer()) {
                this.resumeTimer();
            } else {
                // If no timer exists, check if cart has limited products and remove them
                this.removeProductsIfNoTimer();
            }

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
            const expiry = localStorage.getItem(this.STORAGE_KEYS.EXPIRY);
            const isActive = localStorage.getItem(this.STORAGE_KEYS.ACTIVE);

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
            const expiry = localStorage.getItem(this.STORAGE_KEYS.EXPIRY);

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

            // Store in localStorage
            localStorage.setItem(this.STORAGE_KEYS.EXPIRY, expiry.toString());
            localStorage.setItem(this.STORAGE_KEYS.ACTIVE, "true");

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

            localStorage.removeItem(this.STORAGE_KEYS.EXPIRY);
            localStorage.removeItem(this.STORAGE_KEYS.ACTIVE);

            this.timerData.expiry = null;
            this.timerData.isActive = false;

            // Hide timer display
            const $timerDisplay = $("#woo-limit-timer");
            if ($timerDisplay.length) {
                $timerDisplay.hide();
            }
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
            this.removeExpiredProducts(function() {
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
            const handleCartCheck = function() {
                self.checkCartHasLimitedProducts(function(hasLimited) {
                    if (!hasLimited) {
                        self.clearTimer();
                    }
                });
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
        removeExpiredProducts: function(callback) {
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
