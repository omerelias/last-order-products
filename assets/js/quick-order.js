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

            // Add quantity input change handler
            this.content.on('change', '.quantity-input', (e) => {
                const input = $(e.currentTarget);
                const value = parseInt(input.val()) || 1;
                input.val(value); // Ensure valid number
                
                const productCheckbox = input.closest('.product-item').find('.product-checkbox');
                if (productCheckbox.is(':checked')) {
                    const productId = productCheckbox.data('product-id');
                    if (this.selectedItems.has(productId)) {
                        const itemData = this.selectedItems.get(productId);
                        itemData.quantity = value;
                        this.selectedItems.set(productId, itemData);
                        this.updateTotals();
                    }
                }
            });
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
                    }
                }
            });
        }

        handleQuantityChange(e) {
            const button = $(e.currentTarget);
            const input = button.siblings('input.quantity-input');
            const currentVal = parseInt(input.val()) || 1;
            const productCheckbox = button.closest('.product-item').find('.product-checkbox');
            
            if (button.hasClass('plus')) {
                input.val(currentVal + 1).trigger('change');
            } else if (button.hasClass('minus') && currentVal > 1) {
                input.val(currentVal - 1).trigger('change');
            }

            // Update selected item quantity if checked
            if (productCheckbox.is(':checked')) {
                const productId = productCheckbox.data('product-id');
                if (this.selectedItems.has(productId)) {
                    const itemData = this.selectedItems.get(productId);
                    itemData.quantity = parseInt(input.val());
                    this.selectedItems.set(productId, itemData);
                    this.updateTotals();
                }
            }
        }

        handleProductSelection(e) {
            const checkbox = $(e.currentTarget);
            const productId = checkbox.data('product-id');
            const productData = {
                id: productId,
                name: checkbox.data('name'),
                price: checkbox.data('price'),
                quantity: checkbox.closest('.product-item').find('.quantity-input').val()
            };

            // Add variation data if it exists
            if (checkbox.data('variation-id')) {
                productData.variation_id = checkbox.data('variation-id');
                productData.parent_id = checkbox.data('parent-id');
            }

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

        addToCart(keepExisting = null) {
            if (this.selectedItems.size === 0) return;

            const itemsArray = Array.from(this.selectedItems).map(([id, data]) => ({
                id: id,
                quantity: data.quantity
            }));

            $.ajax({
                url: quickOrderData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'quick_order_add_to_cart',
                    nonce: quickOrderData.nonce,
                    items: itemsArray,
                    ...(keepExisting !== null && { keep_existing: keepExisting })
                },
                success: (response) => {
                    if (response.success) {
                        if (response.data.needsConfirmation) {
                            this.showCartConfirmation();
                        } else {
                            this.minimize();
                            $(document.body).trigger('wc_fragment_refresh');
                        }
                    }
                }
            });
        }

        showCartConfirmation() {
            // Show the modal and overlay
            $('.modal-overlay').show();
            $('#cart-confirmation-modal').show();

            // Remove any existing event handlers
            $('#cart-confirmation-modal .confirm-yes, #cart-confirmation-modal .confirm-no').off('click');

            // Add new event handlers
            $('#cart-confirmation-modal .confirm-yes').on('click', () => {
                $('.modal-overlay').hide();
                $('#cart-confirmation-modal').hide();
                this.addToCart(true);
            });

            $('#cart-confirmation-modal .confirm-no').on('click', () => {
                $('.modal-overlay').hide();
                $('#cart-confirmation-modal').hide();
                this.addToCart(false);
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new QuickOrder();
    });

})(jQuery); 