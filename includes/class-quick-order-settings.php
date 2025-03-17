<?php
if (!defined('ABSPATH')) exit;

class Quick_Order_Settings {
    private $option_name = 'quick_order_settings';
    private $text_domain = 'oc-quick-order';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function enqueue_admin_styles($hook) {
        if ('woocommerce_page_quick-order-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('quick-order-admin', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css');
        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_script('selectWoo');
        wp_enqueue_style('select2');
        
        wp_enqueue_script(
            'quick-order-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
            array('jquery', 'selectWoo'),
            '1.0.0',
            true
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ('woocommerce_page_quick-order-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('quick-order-admin', plugin_dir_url(dirname(__FILE__)) . 'assets/css/admin.css');
        wp_enqueue_style('woocommerce_admin_styles');
        wp_enqueue_script('selectWoo');
        wp_enqueue_style('select2');
        
        wp_enqueue_script(
            'quick-order-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/admin.js',
            array('jquery', 'selectWoo'),
            '1.0.0',
            true
        );

        // Add this block to provide the nonce
        wp_localize_script('quick-order-admin', 'woocommerce_admin', array(
            'search_products_nonce' => wp_create_nonce('search-products')
        ));
    }

    public function add_menu_page() {
        add_submenu_page(
            'woocommerce',
            __('Quick Order Settings', $this->text_domain),
            __('Quick Order', $this->text_domain),
            'manage_options',
            'quick-order-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting($this->option_name, $this->option_name);

        // Text Settings Section
        add_settings_section(
            'text_settings',
            __('Text Settings', $this->text_domain),
            null,
            'quick-order-settings'
        );

        // Add all text fields
        $this->add_text_field('button_text', 'Quick Order Button Text', 'Quick Order');
        $this->add_text_field('header_text', 'Header Text', 'The Movement in One Click');
        $this->add_text_field('no_orders_text', 'No Orders Message', 'No orders in your account yet');
        $this->add_text_field('recommended_text', 'Recommended Products Text', 'Meanwhile, these are our most popular products');
        $this->add_text_field('greeting_text', 'Greeting Text', 'Hi %s :)');
        $this->add_text_field('last_order_text', 'Last Order Text', 'My Last Order');
        $this->add_text_field('all_products_text', 'All Products Text', 'All Products I Bought');
        $this->add_text_field('total_text', 'Total Text', 'Total');
        $this->add_text_field('items_selected_text', 'Items Selected Text', '%d items selected');
        $this->add_text_field('add_to_cart_text', 'Add to Cart Text', 'Add to Cart');
        $this->add_text_field('proceed_to_payment_text', 'Proceed to Payment Text', 'Proceed to Payment');

        // Add confirmation modal texts
        $this->add_text_field('cart_confirmation_text', 'Cart Confirmation Message', 'Your cart contains existing items. Would you like to keep them?');
        $this->add_text_field('cart_confirm_yes_text', 'Keep Items Button Text', 'Yes, keep them');
        $this->add_text_field('cart_confirm_no_text', 'Remove Items Button Text', 'No, remove them');

        // Product Settings Section
        add_settings_section(
            'product_settings',
            __('Product Settings', $this->text_domain),
            null,
            'quick-order-settings'
        );

        add_settings_field(
            'recommended_products',
            __('Recommended Products', $this->text_domain),
            array($this, 'render_product_selector'),
            'quick-order-settings',
            'product_settings'
        );
    }

    private function add_text_field($id, $title, $default) {
        add_settings_field(
            $id,
            __($title, $this->text_domain),
            array($this, 'render_textarea_field'),
            'quick-order-settings',
            'text_settings',
            array(
                'id' => $id,
                'default' => $default,
                'help' => $this->get_help_text($id)
            )
        );
    }

    private function get_help_text($id) {
        $help_texts = array(
            'greeting_text' => 'Use %s for the customer\'s name',
            'items_selected_text' => 'Use %d for the number of items',
            'cart_confirmation_text' => 'Use %d for the number of items in cart. You can use <b>tags</b> for bold text',
            'cart_confirm_yes_text' => 'Text for the button to keep existing items',
            'cart_confirm_no_text' => 'Text for the button to remove existing items'
        );
        return isset($help_texts[$id]) ? $help_texts[$id] : '';
    }

    public function render_textarea_field($args) {
        $options = get_option($this->option_name);
        $value = isset($options[$args['id']]) ? $options[$args['id']] : $args['default'];
        ?>
        <div class="quick-order-field">
            <textarea 
                name="<?php echo $this->option_name . '[' . $args['id'] . ']'; ?>"
                class="large-text"
                rows="3"
            ><?php echo esc_textarea($value); ?></textarea>
            <?php if (!empty($args['help'])) : ?>
                <p class="description"><?php echo esc_html($args['help']); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap quick-order-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="quick-order-settings-container">
                <form action="options.php" method="post">
                    <?php
                    settings_fields($this->option_name);
                    do_settings_sections('quick-order-settings');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function get_setting($key, $default = '') {
        $options = get_option($this->option_name);
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public function get_all_settings() {
        return get_option($this->option_name, array());
    }

    public function update_settings($settings) {
        return update_option($this->option_name, $settings);
    }

    public function render_product_selector() {
        $options = get_option($this->option_name);
        $selected_products = isset($options['recommended_products']) ? $options['recommended_products'] : array();
        ?>
        <select name="<?php echo $this->option_name; ?>[recommended_products][]" 
                multiple 
                class="wc-product-search" 
                data-placeholder="<?php esc_attr_e('Search for a product...', $this->text_domain); ?>"
                style="width: 400px;">
            <?php
            foreach ($selected_products as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    echo '<option value="' . esc_attr($product_id) . '" selected>' . 
                         esc_html($product->get_name()) . '</option>';
                }
            }
            ?>
        </select>
        <p class="description">
            <?php _e('Select products to show when user has no orders', $this->text_domain); ?>
        </p>
        <?php
    }
} 