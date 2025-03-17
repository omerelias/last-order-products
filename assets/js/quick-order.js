(function($) {
    'use strict';

    class QuickOrder {
        constructor() {
            this.button = $('#quick-order-button');
            this.container = $('#quick-order-container');
            this.content = $('#quick-order-content');
            this.selectedItems = new Map();
            this.totalPrice = 0;

            this.initializeEvents();
            this.loadOrderContent();
        }

        initializeEvents() {
            // Toggle container visibility
            this.button.on('click', () => this.toggleContainer());
            
            // Close button
            this.container.find('.close-button').on('click', () => this.minimize());

            // Delegate events for dynamic content
            this.content.on('click', '.quantity-button', (e) => this.handleQuantityChange(e));
            this.content.on('change', '.product-checkbox', (e) => this.handleProductSelection(e));
            this.content.on('click', '.add-to-cart', () => this.addToCart());
        }

        toggleContainer() {
            if (this.container.is(':visible')) {
                this.minimize();
            } else {
                this.expand();
            }
        }

        minimize() {
            this.container.hide();
            this.button.show();
            this.updateMinimizedView();
        }

        expand() {
            this.container.show();
            this.button.hide();
        }

        updateMinimizedView() {
            if (this.selectedItems.size > 0) {
                let productNames = Array.from(this.selectedItems.keys())
                    .map(id => this.selectedItems.get(id).name)
                    .join(', ');
                
                this.button.html(`
                    <span>Quick Order</span>
                    <span>₪${this.totalPrice}</span>
                    <span>${productNames}</span>
                `);
            }
        }

        loadOrderContent() {
            $.ajax({
                url: quickOrderData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_quick_order_content',
                    nonce: quickOrderData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.content.html(response.data.html);
                        this.initializeQuantityInputs();
                    }
                }
            });
        }

        handleQuantityChange(e) {
            const button = $(e.currentTarget);
            const input = button.siblings('input');
            const currentVal = parseInt(input.val());
            
            if (button.hasClass('plus')) {
                input.val(currentVal + 1);
            } else if (button.hasClass('minus') && currentVal > 1) {
                input.val(currentVal - 1);
            }

            this.updateTotals();
        }

        handleProductSelection(e) {
            const checkbox = $(e.currentTarget);
            const productId = checkbox.data('product-id');
            const productData = {
                name: checkbox.data('name'),
                price: checkbox.data('price'),
                quantity: checkbox.closest('.product-item').find('.quantity-input').val()
            };

            if (checkbox.is(':checked')) {
                this.selectedItems.set(productId, productData);
            } else {
                this.selectedItems.delete(productId);
            }

            this.updateTotals();
        }

        updateTotals() {
            this.totalPrice = 0;
            let totalItems = 0;

            this.selectedItems.forEach(item => {
                this.totalPrice += item.price * item.quantity;
                totalItems += parseInt(item.quantity);
            });

            $('.total-price').text(`₪${this.totalPrice}`);
            $('.items-selected').text(
                quickOrderData.texts.itemsSelected.replace('%d', totalItems)
            );
        }

        addToCart() {
            if (this.selectedItems.size === 0) return;

            $.ajax({
                url: quickOrderData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'quick_order_add_to_cart',
                    nonce: quickOrderData.nonce,
                    items: Array.from(this.selectedItems)
                },
                success: (response) => {
                    if (response.success) {
                        // Handle existing cart items confirmation if needed
                        if (response.data.needsConfirmation) {
                            this.showCartConfirmation();
                        } else {
                            window.location.href = response.data.cartUrl;
                        }
                    }
                }
            });
        }

        showCartConfirmation() {
            // We'll need to create this modal in the PHP render function
            const modal = $('#cart-confirmation-modal');
            modal.show();

            modal.find('.confirm-yes').on('click', () => {
                this.addToCart(true);
                modal.hide();
            });

            modal.find('.confirm-no').on('click', () => {
                modal.hide();
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new QuickOrder();
    });

})(jQuery); 