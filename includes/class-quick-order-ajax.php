<?php
if (!defined('ABSPATH')) exit;

class Quick_Order_Ajax {
    private $text_domain = 'oc-quick-order';
    private $settings;

    public function __construct($settings) {
        $this->settings = $settings;
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('wp_ajax_get_quick_order_content', array($this, 'get_quick_order_content'));
        add_action('wp_ajax_quick_order_add_to_cart', array($this, 'add_to_cart'));
    }

    public function get_quick_order_content() {
        check_ajax_referer('quick-order-nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        ob_start();

        if (empty($orders)) {
            $this->render_no_orders_template();
        } else {
            $this->render_orders_template($orders);
        }

        $html = ob_get_clean();
        wp_send_json_success(array('html' => $html));
    }

    public function add_to_cart() {
        check_ajax_referer('quick-order-nonce', 'nonce');
        
        $items = isset($_POST['items']) ? $_POST['items'] : array();
        $cart_has_items = !WC()->cart->is_empty();
        $keep_existing = isset($_POST['keep_existing']) ? $_POST['keep_existing'] : null;
        
        if ($cart_has_items && $keep_existing === null) {
            wp_send_json_success(array(
                'needsConfirmation' => true
            ));
            return;
        }

        if ($keep_existing == 'false' || !$cart_has_items) {
            WC()->cart->empty_cart();
        }
        
        foreach ($items as $item) {
            if (isset($item['variation_id'])) {
                // Add variation to cart
                WC()->cart->add_to_cart($item['parent_id'], $item['quantity'], $item['variation_id']);
            } else {
                // Add simple product to cart
                WC()->cart->add_to_cart($item['id'], $item['quantity']);
            }
        }
        
        wp_send_json_success(array(
            'message' => __('Products added to cart successfully', $this->text_domain),
            'cart_count' => WC()->cart->get_cart_contents_count()
        ));
    }

    private function render_no_orders_template() {
        ?>
        <div class="no-orders-message">
            <div class="no-orders-message-content">
            <p class="p-centered"><span><?php echo wp_kses_post($this->settings->get_setting('no_orders_text')); ?></span>
            <span><?php echo wp_kses_post($this->settings->get_setting('no_orders_note')); ?></span></p>
            </div>
            <p class="p-centered"><?php echo wp_kses_post($this->settings->get_setting('recommended_text')); ?></p>
            
            <div class="recommended-products">
                <?php
                $recommended_products = $this->get_recommended_products();
                foreach ($recommended_products as $product) {
                    $this->render_product_item($product);
                }
                ?>
            </div>

            <div class="order-summary">
                <div class="total-section">
                  
                    <span class="items-selected">
                        <?php printf(
                            esc_html($this->settings->get_setting('items_selected_text')), 
                            0  // Initial count is 0
                        ); ?>
                    </span>
                    <div class="price-group">
                        <span class="total-label"><?php echo esc_html($this->settings->get_setting('total_text')); ?></span>
                        <span class="total-price">₪0</span>
                    </div>
                </div>
                <div class="action-buttons">
                    <button class="add-to-cart"><?php echo esc_html($this->settings->get_setting('add_to_cart_text')); ?></button>
                    <button class="proceed-to-payment"><?php echo esc_html($this->settings->get_setting('proceed_to_payment_text')); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_orders_template($orders) {
        $latest_order = array_shift($orders);
        $user = wp_get_current_user();
        ?>
        <div class="orders-content">
            <p class="greeting">
                <?php printf(esc_html($this->settings->get_setting('greeting_text')), esc_html($user->display_name)); ?>
            </p>

            <div class="last-order-section">
                <h3><?php echo esc_html($this->settings->get_setting('last_order_text')); ?></h3>
                <?php
                foreach ($latest_order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        $variation_attributes = $product->get_type() === 'variation' ? $item->get_meta('_variation_attributes', true) : array();
                        $this->render_product_item($product, $item->get_quantity(), $variation_attributes);
                    }
                }
                ?>
            </div>

            <?php if (!empty($orders)) : ?>
                <div class="previous-orders-section">
                    <h3><?php echo esc_html($this->settings->get_setting('all_products_text')); ?></h3>
                    <?php
                    $displayed_products = array();
                    foreach ($orders as $order) {
                        foreach ($order->get_items() as $item) {
                            $product = $item->get_product();
                            if ($product && !in_array($product->get_id(), $displayed_products)) {
                                $this->render_product_item($product, $item->get_quantity());
                                $displayed_products[] = $product->get_id();
                            }
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="order-summary">
                <div class="total-section">
                    <div class="price-group">
                        <span class="total-label"><?php echo esc_html($this->settings->get_setting('total_text')); ?></span>
                        <span class="total-price">₪0</span>
                    </div>
                    <span class="items-selected">
                        <?php printf(
                            esc_html($this->settings->get_setting('items_selected_text')), 
                            0  // Initial count is 0
                        ); ?>
                    </span>
                </div>
                <div class="action-buttons">
                    <button class="add-to-cart"><?php echo esc_html($this->settings->get_setting('add_to_cart_text')); ?></button>
                    <button class="proceed-to-payment"><?php echo esc_html($this->settings->get_setting('proceed_to_payment_text')); ?></button>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_product_item($product, $quantity = 1, $variation_attributes = array()) {
        $product_url = get_permalink($product->get_id());
        $is_variation = $product->get_type() === 'variation';
        ?>
        <div class="product-item">
            <input type="checkbox" 
                   class="product-checkbox" 
                   data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                   data-name="<?php echo esc_attr($product->get_name()); ?>"
                   data-price="<?php echo esc_attr($product->get_price()); ?>"
                   <?php if ($is_variation) : ?>
                       data-variation-id="<?php echo esc_attr($product->get_id()); ?>"
                       data-parent-id="<?php echo esc_attr($product->get_parent_id()); ?>"
                   <?php endif; ?>>
            
            <div class="product-image">
                <a href="<?php echo esc_url($product_url); ?>" target="_blank">
                    <?php echo $product->get_image('thumbnail'); ?>
                </a>
            </div>
            
            <div class="product-details">
                <div class="product-info">
                    <a href="<?php echo esc_url($product_url); ?>" target="_blank" class="product-name">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                    <?php if ($is_variation && !empty($variation_attributes)) : ?>
                        <div class="variation-attributes">
                            <?php 
                            $attributes = array();
                            foreach ($variation_attributes as $attribute => $value) {
                                $taxonomy = str_replace('attribute_', '', $attribute);
                                $term = get_term_by('slug', $value, $taxonomy);
                                $label = wc_attribute_label($taxonomy);
                                ?>
                                <span class="variation-attribute">
                                    <span class="attribute-label"><?php echo esc_html($label); ?>:</span>
                                    <span class="attribute-value"><?php echo esc_html($term ? $term->name : $value); ?></span>
                                </span>
                            <?php } ?>
                        </div>
                    <?php endif; ?>
                    <span class="product-meta">
                        <?php 
                        $weight = $product->get_weight();
                        echo $weight ? esc_html($weight . ' ' . get_option('woocommerce_weight_unit')) : '';
                        ?>
                    </span>
                </div>
                <span class="product-price">₪<?php echo esc_html($product->get_price()); ?></span>
            </div>
            
            <div class="quantity-controls">
                <button class="quantity-button minus" aria-label="Decrease quantity">-</button>
                <input type="number" class="quantity-input" value="<?php echo esc_attr($quantity); ?>" min="1">
                <button class="quantity-button plus" aria-label="Increase quantity">+</button>
            </div>
        </div>
        <?php
    }

    private function get_recommended_products() {
        // Get products selected in admin settings
        $selected_products = $this->settings->get_setting('recommended_products', array());
        
        if (!empty($selected_products)) {
            // Get products by IDs selected in admin
            return array_filter(array_map('wc_get_product', $selected_products));
        }

        // Fallback to popular products if no products selected in admin
        return wc_get_products(array(
            'limit' => 5,
            'orderby' => 'popularity',
            'order' => 'DESC'
        ));
    }

    // ... rest of the AJAX class methods ...
} 