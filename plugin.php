<?php
/*
Plugin Name: OC - Quick Order
Description: Displays user's last order with quick reorder functionality
Version: 1.0
Author: Original Concepts
Text Domain: oc-quick-order
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit;

class Quick_Order {
    private $settings;
    private $ajax;

    public function __construct() {
        $this->load_dependencies();
        $this->init_components();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once plugin_dir_path(__FILE__) . 'includes/class-quick-order-settings.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-quick-order-ajax.php';
    }

    private function init_components() {
        $this->settings = new Quick_Order_Settings();
        $this->ajax = new Quick_Order_Ajax($this->settings);
    }

    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_footer', array($this, 'render_quick_order_button'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function load_plugin_textdomain() {
        load_textdomain(
            'oc-quick-order',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('woocommerce_page_quick-order-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_script('selectWoo');
        wp_enqueue_style('select2');
        
        wp_enqueue_script(
            'quick-order-admin',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery', 'selectWoo'),
            '1.0.0',
            true
        );
    }

    public function enqueue_scripts() {
        if (!is_user_logged_in()) {
            return;
        }

        wp_enqueue_style(
            'quick-order-styles',
            plugin_dir_url(__FILE__) . 'assets/css/quick-order.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_script(
            'quick-order-script',
            plugin_dir_url(__FILE__) . 'assets/js/quick-order.js',
            array('jquery'),
            '1.0.0',
            true
        );

        wp_localize_script('quick-order-script', 'quickOrderData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quick-order-nonce'),
            'texts' => array(
                'noOrders' => $this->settings->get_setting('no_orders_text'),
                'recommendedProducts' => $this->settings->get_setting('recommended_text'),
                'greeting' => $this->settings->get_setting('greeting_text', 'Hi %s :)'),
                'lastOrder' => $this->settings->get_setting('last_order_text', 'My Last Order'),
                'allProducts' => $this->settings->get_setting('all_products_text', 'All Products I Bought'),
                'total' => $this->settings->get_setting('total_text', 'Total'),
                'itemsSelected' => $this->settings->get_setting('items_selected_text', '%d items selected'),
                'addToCart' => $this->settings->get_setting('add_to_cart_text', 'Add to Cart'),
                'proceedToPayment' => $this->settings->get_setting('proceed_to_payment_text', 'Proceed to Payment')
            )
        ));
    }

    public function render_quick_order_button() {
        if (!is_user_logged_in()) {
            return;
        }
        ?>
        <div id="quick-order-button" class="quick-order-minimized">
            <div class="minimized-content">
                <span class="title"><?php echo esc_html($this->settings->get_setting('button_text', 'Quick Order')); ?></span>
                <span class="total">₪<span class="amount">0</span></span>
                <span class="products-list"></span>
            </div>
        </div>

        <div id="quick-order-container" class="quick-order-container" style="display: none;">
            <div class="quick-order-header">
                <span class="close-button">×</span>
                <div class="header-content">
                    <img src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/plate-icon.svg'; ?>" alt="" class="header-icon">
                    <h2><?php echo esc_html($this->settings->get_setting('header_text', 'The Movement in One Click')); ?></h2>
                </div>
            </div>
            <div id="quick-order-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>

        <div class="modal-overlay"></div>
        <div id="cart-confirmation-modal" class="quick-order-modal">
            <p><?php esc_html_e('Your cart contains existing items. Would you like to keep them?', $this->text_domain); ?></p>
            <div class="modal-buttons">
                <button class="confirm-no"><?php esc_html_e('No, remove them', $this->text_domain); ?></button>
                <button class="confirm-yes"><?php esc_html_e('Yes, keep them', $this->text_domain); ?></button>
            </div>
        </div>
        <?php
    }

    private function render_no_orders_template() {
        ?>
        <div class="no-orders-message">
            <p><?php esc_html_e('No orders in your account yet', $this->text_domain); ?></p>
            <p><?php esc_html_e('Make an order and next time you can easily and quickly buy the products you loved again. Meanwhile, these are our most popular products', $this->text_domain); ?></p>
            
            <div class="recommended-products">
                <?php
                $recommended_products = $this->get_recommended_products();
                foreach ($recommended_products as $product) {
                    $this->render_product_item($product);
                }
                ?>
            </div>
        </div>
        <?php
    } 

    private function render_orders_template($orders) {
        return; 
        $latest_order = array_shift($orders); 
        $user = wp_get_current_user();
        ?>
        <div class="orders-content">
            <p class="greeting">
                <?php printf(esc_html__('Hi %s :)', $this->text_domain), esc_html($user->display_name)); ?>
            </p>

            <div class="last-order-section">
                <h3><?php esc_html_e('My Last Order', $this->text_domain); ?></h3>
                <?php
                foreach ($latest_order->get_items() as $item) {
                    $product = $item->get_product();
                    if ($product) {
                        $this->render_product_item($product, $item->get_quantity());
                    }
                }
                ?>
            </div>

            <?php if (!empty($orders)) : ?>
                <div class="previous-orders-section">
                    <h3><?php esc_html_e('All Products I Bought', $this->text_domain); ?></h3>
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
                    <span class="total-price">₪0</span>
                    <span class="items-selected"><?php esc_html_e('0 items selected', $this->text_domain); ?></span>
                </div>
                <div class="action-buttons">
                    <button class="add-to-cart"><?php esc_html_e('Add to Cart', $this->text_domain); ?></button>
                    <button class="proceed-to-payment"><?php esc_html_e('Proceed to Payment', $this->text_domain); ?></button>
                </div>
            </div>
        </div>

        <div class="modal-overlay"></div>
        <div id="cart-confirmation-modal" class="quick-order-modal">
            <p><?php esc_html_e('Your cart contains existing items. Would you like to keep them?', $this->text_domain); ?></p>
            <div class="modal-buttons">
                <button class="confirm-no"><?php esc_html_e('No, remove them', $this->text_domain); ?></button>
                <button class="confirm-yes"><?php esc_html_e('Yes, keep them', $this->text_domain); ?></button>
            </div>
        </div>
        <?php
    }

    private function render_product_item($product, $quantity = 1) {
        ?>
        <div class="product-item">
            <input type="checkbox" 
                   class="product-checkbox" 
                   data-product-id="<?php echo esc_attr($product->get_id()); ?>"
                   data-name="<?php echo esc_attr($product->get_name()); ?>"
                   data-price="<?php echo esc_attr($product->get_price()); ?>">
            
            <div class="product-image">
                <?php echo $product->get_image('thumbnail'); ?>
            </div>
            
            <div class="product-details">
                <div class="product-info">
                    <span class="product-name"><?php echo esc_html($product->get_name()); ?></span>
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
        // This could be enhanced with actual recommendation logic
        return wc_get_products(array(
            'limit' => 5,
            'orderby' => 'popularity',
            'order' => 'DESC'
        ));
    }
}

new Quick_Order(); 