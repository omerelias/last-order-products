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
        
        // Only initialize hooks if enabled
        if ($this->is_enabled()) {
            $this->init_hooks();
        }
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

    private function is_enabled() {
        return $this->settings->get_setting('enabled', true);
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
        if (!is_user_logged_in() || !$this->is_enabled()) {
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
        if (!is_user_logged_in() || !$this->is_enabled()) {
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
                    <h2><?php echo esc_html($this->settings->get_setting('header_text', 'The Movement in One Click')); ?></h2>
                </div>
            </div>
            <div id="quick-order-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>

        <div class="modal-overlay"></div>
        <div id="cart-confirmation-modal" class="quick-order-modal">
            <p><?php printf(
                wp_kses_post($this->settings->get_setting('cart_confirmation_text', 'Your cart contains %d items. Would you like to keep them?')),
                WC()->cart->get_cart_contents_count()
            ); ?></p>
            <div class="modal-buttons">
                <button class="confirm-yes">
                    <?php echo wp_kses_post($this->settings->get_setting('cart_confirm_yes_text', 'Yes, keep them')); ?>
                </button>
                <button class="confirm-no">
                    <?php echo wp_kses_post($this->settings->get_setting('cart_confirm_no_text', 'No, remove them')); ?>
                </button>
            </div>
        </div>
        <?php
    }
}

new Quick_Order(); 