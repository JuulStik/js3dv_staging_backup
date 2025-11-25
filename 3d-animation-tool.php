<?php
/*
    Plugin Name: 3D animation tool
    Description: A 3D animation of covers with adjustable price parameters, connected with woocommerce
    Version: 1.1
    Author: Juul Stikkelbroeck
*/

namespace JS\JS3DV;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class JS3DV_Plugin {
    function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_3d_visualization_scripts'), 999);
        add_action('rest_api_init', array($this, 'register_js3dv_routes'), 999);
        add_filter('woocommerce_cart_item_name', array($this, 'change_product_title_in_cart'), 20, 3);
        add_filter('woocommerce_cart_item_price', array($this, 'adjust_cart_item_price'), 20, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'adjust_cart_item_price_in_cart'), 20, 1);
        add_filter('woocommerce_get_item_data', array($this, 'display_custom_dimensions_in_cart'), 20, 2);
        add_filter('woocommerce_cart_item_thumbnail', array($this, 'change_cart_product_thumbnail'), 20, 3);

        // email
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_custom_cart_item_data_to_order'), 20, 4);
        add_action('woocommerce_order_item_meta_start', array($this, 'display_custom_dimensions_in_email'), 20, 3);
        add_filter('woocommerce_order_item_get_formatted_meta_data', array($this, 'remove_default_meta_data'), 10, 2);
        add_filter('woocommerce_email_attachments', array($this, 'attach_custom_image_to_email'), 10, 3);        

        // admin page
        add_action('admin_menu', array($this, 'js3dv_settings_page'));
    }

    function enqueue_3d_visualization_scripts() {
        if (is_page(array('transporthoes-samenstellen'))) {
            // wp_enqueue_script('three-js', 'https://cdnjs.cloudflare.com/ajax/libs/three.js/0.164.1/three.min.js', [], null, true);
            wp_enqueue_script('3d-visualization-script', plugins_url('js/3d-visualization_hardcode.js', __FILE__), array('jquery'), null, true);
            wp_enqueue_style('3d-visualization-style', plugins_url('css/3d-visualization.css', __FILE__));
            wp_localize_script('3d-visualization-script', 'pluginSettings', array(
                'url' => plugin_dir_url(__FILE__),
                'api_url' => rest_url('js3dv-plugin/v1/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'basePrice' => (is_numeric(get_option('js3dv-admin-baseprice')) ? esc_attr(get_option('js3dv-admin-baseprice')) : 100),
                'fabricPrice' => (is_numeric(get_option('js3dv-admin-fabricprice')) ? esc_attr(get_option('js3dv-admin-fabricprice')) : 21.00),
                'specialPrice' => (is_numeric(get_option('js3dv-admin-specialprice')) ? esc_attr(get_option('js3dv-admin-specialprice')) : 25.00),
                'handlePrice' => (is_numeric(get_option('js3dv-admin-handlePrice')) ? esc_attr(get_option('js3dv-admin-handlePrice')) : 10.00),
                'reinforcementPrice' => (is_numeric(get_option('js3dv-admin-reinforcementPrice')) ? esc_attr(get_option('js3dv-admin-reinforcementPrice')) : 30.00),
                'panelPrice' => (is_numeric(get_option('js3dv-admin-panelPrice')) ? esc_attr(get_option('js3dv-admin-panelPrice')) : 0.00),
                'waterResistancePrice' => (is_numeric(get_option('js3dv-admin-waterResistancePrice')) ? esc_attr(get_option('js3dv-admin-waterResistancePrice')) : 15.00)
            ));
        }
    }

    function js3dv_permission_callback($request) {
        $nonce = sanitize_text_field($request->get_header('X-WP-Nonce'));
        return isset($nonce) && wp_verify_nonce($nonce, 'wp_rest');
    }

    function register_js3dv_routes() {
        register_rest_route('js3dv-plugin/v1', '/measurement-data', array(
            'methods' => 'POST',
            'callback' => array($this, "js3dv_measurement_data"),
            'permission_callback' => array($this, "js3dv_permission_callback"),
        ));
    }

    function js3dv_measurement_data(\WP_REST_Request $request) {
        try {

            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                return new \WP_REST_Response(array('error' => 'WooCommerce is not active'), 500);
            }
    
            // Initialize WooCommerce session if needed
            if (null === WC()->session) {
                WC()->session = new \WC_Session_Handler();
                WC()->session->init();
            }
    
            // Initialize WooCommerce cart if not already
            if (null === WC()->cart) {
                WC()->cart = new \WC_Cart();
            }
    
            // Ensure customer is set for the cart context
            if (null === WC()->customer) {
                WC()->customer = new \WC_Customer(get_current_user_id(), true);
            }

            $product_id = absint( 10658 );

            $price_option = get_option('js3dv-admin-baseprice');

            $price = (isset($price_option) && is_numeric($price_option)) ? floatval($price_option) : 100;

            $quantity = 1;
            $handleCount = 0;

            $data = json_decode(file_get_contents('php://input'), true);
            $cart_item_data = [];

            if (!empty($data) && is_array($data)) {                
                foreach ($data as $item) {
                    $label = sanitize_text_field($item['label']);
                    if ($label === 'object') {
                        $cart_item_data['custom_title'] = 'Transporthoes voor ' . sanitize_text_field($item['value']);
                        $cart_item_data[$label] = sanitize_text_field($item['value']);
                    } elseif($label === 'Naam') {
                        $cart_item_data[$label] = sanitize_text_field($item['value']);
                    } elseif($label === 'Versteviging') {
                        $cart_item_data[$label] = sanitize_text_field($item['value']);
                    } elseif($label === 'venster') {
                        $cart_item_data[$label] = sanitize_text_field($item['value']);
                    } elseif($label === 'materiaal') {
                        $cart_item_data[$label] = sanitize_text_field($item['value']);
                    } elseif($label === 'radius') {
                        $cart_item_data[$label] = floatval($item['value']);
                    } elseif ($label === 'image_data_base64') {
                        $cart_item_data['custom_image'] = sanitize_text_field($item['value']);
                    } elseif ($label === 'image_data_front') {
                        $cart_item_data['custom_image_front'] = sanitize_text_field($item['value']);
                    } elseif ($label === 'top_pdf_image') {
                        $cart_item_data['top_pdf_image'] = sanitize_text_field($item['value']);
                    } elseif ($label === 'front_pdf_image') {
                        $cart_item_data['front_pdf_image'] = sanitize_text_field($item['value']);
                    } elseif ($label !== 'quantity' && $label !== 'handle' && $label !== 'image_data_base64') {
                        $value = floatval($item['value']);
                        $cart_item_data[$label] = $value;
                    } elseif ($label === 'quantity') {
                        $quantity = intval($item['value']);
                    } elseif ($label === 'handle') {
                        $handleCount++;
                        if (!isset($cart_item_data[$label])) {
                            $cart_item_data[$label] = [];
                        }
                    
                        $cart_item_data[$label][] = array(
                            'place' => sanitize_text_field($item['value']['placement']), 
                            'x' => floatval($item['value']['x']),
                            'y' => floatval($item['value']['y']),
                        );
                    } 
                }
            }

            $price = $this->calculate_total_price($quantity, $handleCount, $cart_item_data);

            $cart_item_data['price'] = $price;

            // return new \WP_REST_Response(array('message' => 'Product added to cart', 'cart_items' => $cart_item_data), 200);

            // error_log('Cart before adding: ' . print_r(WC()->cart->get_cart(), true));
            WC()->cart->get_cart();
            $added = WC()->cart->add_to_cart($product_id, $quantity, 0, array(), $cart_item_data);
    
            // Check if the product was added successfully
            if ($added) {
                // WC()->cart->calculate_totals();
                $cart_items = WC()->cart->get_cart();
                return new \WP_REST_Response(array('message' => 'Product added to cart', 'cart_item_key' => $added, 'cart_items' => $cart_items, 'cart_item_data' => $cart_item_data), 200);
            } else {
                $errors = wc_get_notices('error');
                return new \WP_REST_Response(array('error' => 'Failed to add product to cart', 'message' => $errors), 500);
            }
        } catch (\Exception $e) {
            return new \WP_REST_Response(array('error' => $e->getMessage()), 500);
        }
    }

    function calculate_total_price($quantity, $handleCount, $data) {
        $price_option = get_option('js3dv-admin-baseprice');

        $base_price = (isset($price_option) && is_numeric($price_option)) ? floatval($price_option) : 100;

        $handle_option = get_option('js3dv-admin-handlePrice');

        $handle_price = (isset($handle_option) && is_numeric($handle_option)) ? floatval($handle_option) : 10;
        $fabric_price = 0;

        switch ($data['object']) {
            case 'Rechthoek':
                $fabric_price = ($this->calculate_DJBooth_costs($data)) + ($handle_price * $handleCount);
                break;
            case 'Rechthoek met ronding':
                $fabric_price = ($this->calculate_DJBooth_costs($data)) + ($handle_price * $handleCount);
                break;
            case 'Zeshoek':
                $fabric_price = ($this->calculate_Hexagon_costs($data)) + ($handle_price * $handleCount);
                break;
            case 'Waaier':
                $fabric_price = ($this->calculate_Waaier_costs($data)) + ($handle_price * $handleCount);
                break;
            default:
                $fabric_price = $base_price + (999999);
        }
        return $fabric_price;
    }

    function calculate_fabric_size($height, $width) {        
        return floatval(($height / 100) * ($width / 100));
    }

    function calculate_DJBooth_costs($data) {
        $price_option = get_option('js3dv-admin-baseprice');
        $fabric_option = get_option('js3dv-admin-fabricprice');
        $special_option = get_option('js3dv-admin-specialprice');
        $reinforcement_option = get_option('js3dv-admin-reinforcementPrice');
        $panel_option = get_option('js3dv-admin-panelPrice');
        $water_resistance_option = get_option('js3dv-admin-waterResistancePrice');

        $base_price = (isset($price_option) && is_numeric($price_option)) ? floatval($price_option) : 100;
        $single_piece_cost = (isset($fabric_option) && is_numeric($fabric_option)) ? floatval($fabric_option) : 21.00;
        $special_costs = (isset($special_option) && is_numeric($special_option)) ? floatval($special_option) : 25.00;
        $reinforcement_costs = (isset($reinforcement_option) && is_numeric($reinforcement_option)) ? floatval($reinforcement_option) : 30.00;
        $panel_costs = (isset($panel_option) && is_numeric($panel_option)) ? floatval($panel_option) : 0.00;
        $water_resistance_costs = (isset($water_resistance_option) && is_numeric($water_resistance_option)) ? floatval($water_resistance_option) : 15.00;

        $fabric_size = 0.0;
        if (isset($data['Hoogte'], $data['Breedte'], $data['Diepte'])) {
            $width = floatval($data['Breedte']);
            $height = floatval($data['Hoogte']);
            $depth = floatval($data['Diepte']);
            //Top
            $fabric_size += $this->calculate_fabric_size($depth, $width);
            //side A
            $fabric_size += $this->calculate_fabric_size($height, $width);
            //side B
            $fabric_size += $this->calculate_fabric_size($height, $depth);
            //side C
            $fabric_size += $this->calculate_fabric_size($height, $width);
            //side D
            $fabric_size += $this->calculate_fabric_size($height, $depth);

            $price = $fabric_size * $single_piece_cost;
            $price = $base_price + $price;
            if (isset($data['materiaal']) && $data['materiaal'] !== "Waterafstotend") {
                $price = $price * (1 + ($water_resistance_costs / 100));
            }
            if (isset($data['Versteviging']) && $data['Versteviging'] !== "Nee") {
                $price = $price + $reinforcement_costs;
            }
            if (isset($data['venster']) && $data['venster'] !== "Nee") {
                $price = $price + $panel_costs;
            }
            if(isset($data['radius'])) {
                return $price + $special_costs;
            } else {
                return $price;
            }
        } else {
            return 9999999;
        }

    }

    function calculate_Hexagon_costs($data) {
        $price_option = get_option('js3dv-admin-baseprice');
        $fabric_option = get_option('js3dv-admin-fabricprice');
        $special_option = get_option('js3dv-admin-specialprice');
        $reinforcement_option = get_option('js3dv-admin-reinforcementPrice');
        $panel_option = get_option('js3dv-admin-panelPrice');
        $water_resistance_option = get_option('js3dv-admin-waterResistancePrice');

        $base_price = (isset($price_option) && is_numeric($price_option)) ? floatval($price_option) : 100;
        $single_piece_cost = (isset($fabric_option) && is_numeric($fabric_option)) ? floatval($fabric_option) : 21.00;
        $special_costs = (isset($special_option) && is_numeric($special_option)) ? floatval($special_option) : 25.00;
        $reinforcement_costs = (isset($reinforcement_option) && is_numeric($reinforcement_option)) ? floatval($reinforcement_option) : 30.00;
        $panel_costs = (isset($panel_option) && is_numeric($panel_option)) ? floatval($panel_option) : 0.00;
        $water_resistance_costs = (isset($water_resistance_option) && is_numeric($water_resistance_option)) ? floatval($water_resistance_option) : 15.00;

        $fabric_size = 0.0; 
        if (isset($data['Hoogte (G)'], $data['Korte zijde (D)'], $data['Lange zijde (A)'],  $data['Diepte'], $data['Zijde B en F'])) {
            $width = floatval($data['Lange zijde (A)']);
            $innerWidth = floatval($data['Korte zijde (D)']);
            $height = floatval($data['Hoogte (G)']);
            $depth = floatval($data['Diepte']);
            $innerDepth = floatval($data['Zijde B en F']);

            $ceWidth = sqrt(pow($depth - $innerDepth, 2) + pow(($width - $innerWidth) / 2, 2));
            //Top
            $fabric_size += $this->calculate_fabric_size($depth, $width);
            //side A
            $fabric_size += $this->calculate_fabric_size($height, $width);
            //side B
            $fabric_size += $this->calculate_fabric_size($height, $innerDepth);
            //side C
            $fabric_size += $this->calculate_fabric_size($height, $ceWidth);
            //side D
            $fabric_size += $this->calculate_fabric_size($height, $innerWidth);
            //side E
            $fabric_size += $this->calculate_fabric_size($height, $ceWidth);
            //side F
            $fabric_size += $this->calculate_fabric_size($height, $innerDepth);

            $price = ($fabric_size * $single_piece_cost);
            $price = $base_price + $price;
            if (isset($data['materiaal']) && $data['materiaal'] !== "Waterafstotend") {
                $price = $price * (1 + ($water_resistance_costs / 100));
            }
            if (isset($data['Versteviging']) && $data['Versteviging'] !== "Nee") {
                $price = $price + $reinforcement_costs;
            }
            if (isset($data['venster']) && $data['venster'] !== "Nee") {
                $price = $price + $panel_costs;
            }
            return $price + $special_costs;
        } else {
            return 9999999;
        }

    }

    function calculate_Waaier_costs($data) {
        $price_option = get_option('js3dv-admin-baseprice');
        $fabric_option = get_option('js3dv-admin-fabricprice');
        $special_option = get_option('js3dv-admin-specialprice');
        $reinforcement_option = get_option('js3dv-admin-reinforcementPrice');
        $panel_option = get_option('js3dv-admin-panelPrice');
        $water_resistance_option = get_option('js3dv-admin-waterResistancePrice');

        $base_price = (isset($price_option) && is_numeric($price_option)) ? floatval($price_option) : 100;
        $single_piece_cost = (isset($fabric_option) && is_numeric($fabric_option)) ? floatval($fabric_option) : 21.00;
        $special_costs = (isset($special_option) && is_numeric($special_option)) ? floatval($special_option) : 25.00;
        $reinforcement_costs = (isset($reinforcement_option) && is_numeric($reinforcement_option)) ? floatval($reinforcement_option) : 30.00;
        $panel_costs = (isset($panel_option) && is_numeric($panel_option)) ? floatval($panel_option) : 0.00;
        $water_resistance_costs = (isset($water_resistance_option) && is_numeric($water_resistance_option)) ? floatval($water_resistance_option) : 15.00;

        $fabric_size = 0.0; 
        if (isset($data['Hoogte (E)'], $data['Korte zijde (C)'], $data['Lange zijde (A)'],  $data['Diepte'])) {
            $tWidth = floatval($data['Lange zijde (A)']);
            $bWidth = floatval($data['Korte zijde (C)']);
            $height = floatval($data['Hoogte (E)']);
            $depth = floatval($data['Diepte']);

            $bdWidth = sqrt(pow(($tWidth - $bWidth) / 2, 2) + pow($depth, 2));
            //Top
            $fabric_size += $this->calculate_fabric_size($depth, $tWidth);
            //side A
            $fabric_size += $this->calculate_fabric_size($height, $tWidth);
            //side B
            $fabric_size += $this->calculate_fabric_size($height, $bdWidth);
            //side C
            $fabric_size += $this->calculate_fabric_size($height, $bWidth);
            //side D
            $fabric_size += $this->calculate_fabric_size($height, $bdWidth);

            $price = ($fabric_size * $single_piece_cost);
            $price = $base_price + $price;
            if (isset($data['materiaal']) && $data['materiaal'] !== "Waterafstotend") {
                $price = $price * (1 + ($water_resistance_costs / 100));
            }
            if (isset($data['Versteviging']) && $data['Versteviging'] !== "Nee") {
                $price = $price + $reinforcement_costs;
            }
            if (isset($data['venster']) && $data['venster'] !== "Nee") {
                $price = $price + $panel_costs;
            }
            return $price + $special_costs;
        } else {
            return 9999999;
        }

    }

    function display_custom_dimensions_in_cart($item_data, $cart_item) {
        foreach ($cart_item as $key => $value) {
            if (in_array($key, ['Breedte', 'Hoogte', 'Diepte', 'Hoogte (G)', 'Korte zijde (D)', 'Lange zijde (A)', 'Diepte', 'Zijde B en F', 'Hoogte (E)', 'Korte zijde (C)', 'Lange zijde (A)', 'radius'])) {
                $item_data[] = array(
                    'key'   => ucfirst($key), 
                    'value' => $value . ' cm'
                );
            } elseif (in_array($key, ['Naam', 'Versteviging', 'object', 'venster', 'materiaal'])) {
                $item_data[] = array(
                    'key'   => ucfirst($key),
                    'value' => $value
                );  
            } elseif( $key === 'handle') {
                foreach($value as $value) {

                    if (isset($value['place'], $value['x'], $value['y'])) { 
                        if ($value['place'] === 'top') {
                            $item_data[] = array(
                                'key'   => ucfirst('Handvat ' . $value['place']),
                                    'value' => 'Afstand van rechter zijde: ' . $value['x'] . ' cm, Afstand van zijde A: ' .  $value['y'] . ' cm',
                                );
                        } else {
                            $item_data[] = array(
                            'key'   => ucfirst('Handvat zijde ' . $value['place']),
                                'value' => 'Afstand van rechter zijde: ' . $value['x'] . ' cm, Afstand van top: ' .  $value['y'] . ' cm',
                            );
                        }  
                    }
                }
            }
        }
        return $item_data;
    }

    function adjust_cart_item_price_in_cart($cart) {

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['price'])) {
                $cart_item['data']->set_price($cart_item['price']);
            }
        }
    }

    function adjust_cart_item_price($price, $cart_item) {
        error_log('hello from price');

        if (isset($cart_item['price'])) {
            return wc_price($cart_item['price']);
        }
        return $price;
    }

    function change_product_title_in_cart($product_name, $cart_item, $cart_item_key) {
        if (isset($cart_item['custom_title'])) {
            $product_name = 'Transporthoes';
        }
        return $product_name;
    }

    function change_cart_product_thumbnail($thumbnail, $cart_item, $cart_item_key) {
        if (isset($cart_item['custom_image'], $cart_item['custom_title'])) {
            $custom_image_url = $cart_item['custom_image'];
    
            $thumbnail = '<img src="' . $cart_item['custom_image'] . '" alt="' . esc_attr($cart_item['custom_title']) . '" class="woocommerce-placeholder wp-post-image" style="width:300px"/>';
        }
        
        return $thumbnail;
    }

    function js3dv_settings_page() {
        $mainPageHook = add_menu_page('js3dv-plugin', 'JS3DV Settings', 'manage_options', 'js3dvset', array($this, 'render_js3dv_settings_page'), 'dashicons-money-alt', 100);
        add_action("load-{$mainPageHook}", array($this, 'reg_main_page_assets'));
    }

    function reg_main_page_assets() {
        wp_enqueue_style('admin-styles', plugin_dir_url(__FILE__) . 'css/admin-style.css');
    }

    function render_js3dv_settings_page() { 
        ?>
        <div class='js3dv-admin-settings-wrapper'>
            <h1 class="js3dv-admin-settings-header">JS3DV opties</h1>
            <form class="js3dv-admin-prices-form" method="POST" >
                <?php wp_nonce_field('save_js3dv_price_form', 'js3dv_price_nonce') ?>
                <div class="js3dv-admin-settings-form-container">
                    <h3>Prijzen</h3>
                    <?php if (isset($_POST['js3dv-admin-price-submit']) == 'true') $this->handle_js3dv_price_form() ?>
                    <div class="js3dv-admin-settings-container">
                        <input type="hidden" name="js3dv-admin-price-submit" value="true">
                        <!-- base price -->
                         <p>Dit is het basisbedrag dat aan elke hoes wordt toegevoegd.</p>
                        <div class="js3dv-admin-setting-container">
                            <label for="js3dv-admin-set-baseprice-value">Basis prijs:</label>
                            <input type="number" class="js3dv-admin-set-baseprice-value" name="js3dv-admin-set-baseprice-value" value="<?php echo (get_option('js3dv-admin-baseprice')) ? esc_attr(get_option('js3dv-admin-baseprice')) : ''; ?>">
                        </div>
                        <!-- fabric price -->
                         <p>Dit is de prijs van de stof 1.40 X 1 meter</p>
                        <div class="js3dv-admin-setting-container">
                            <label for="js3dv-admin-set-fabric-value">Stof prijs:</label>
                            <input type="number" class="js3dv-admin-set-fabric-value" name="js3dv-admin-set-fabric-value" value="<?php echo (get_option('js3dv-admin-fabricprice')) ? esc_attr(get_option('js3dv-admin-fabricprice')) : ''; ?>">
                        </div>
                        <!-- special price -->
                         <p>Dit is de prijs voor speciale vormen</p>
                        <div class="js3dv-admin-setting-container">
                            <label for="js3dv-admin-set-special-value">Speciaal prijs:</label>
                            <input type="number" class="js3dv-admin-set-special-value" name="js3dv-admin-set-special-value" value="<?php echo (get_option('js3dv-admin-specialprice')) ? esc_attr(get_option('js3dv-admin-specialprice')) : ''; ?>">
                        </div>
                         <!-- handle price -->
                         <p>Dit is de prijs per handvat</p>
                        <div class="js3dv-admin-setting-container">
                            <label for="js3dv-admin-set-handle-value">Handvat prijs:</label>
                            <input type="number" class="js3dv-admin-set-handle-value" name="js3dv-admin-set-handle-value" value="<?php echo (get_option('js3dv-admin-handlePrice')) ? esc_attr(get_option('js3dv-admin-handlePrice')) : ''; ?>">
                        </div>
                         <!-- Reinforcement price -->
                         <p>Dit is de prijs voor een versteviging</p>
                        <div class="js3dv-admin-setting-container">
                            <label for="js3dv-admin-set-reinforcement-value">Versteviging prijs:</label>
                            <input type="number" class="js3dv-admin-set-reinforcement-value" name="js3dv-admin-set-reinforcement-value" value="<?php echo (get_option('js3dv-admin-reinforcementPrice')) ? esc_attr(get_option('js3dv-admin-reinforcementPrice')) : ''; ?>">
                        </div>
                        <!-- Panel price -->
                        <p>Dit is de prijs voor een venster (A5 formaat)</p>
                        <div class="js3dv-admin-setting-container">
                            <label for="js3dv-admin-set-panel-value">Venster prijs:</label>
                            <input type="number" class="js3dv-admin-set-panel-value" name="js3dv-admin-set-panel-value" value="<?php echo (get_option('js3dv-admin-panelPrice')) ? esc_attr(get_option('js3dv-admin-panelPrice')) : ''; ?>">
                        </div>
                        <!-- Water resistance price -->
                        <p>Dit is de prijs voor een waterdichte stof (procent)</p>
                        <div class="js3dv-admin-setting-container">
                            <label for="js3dv-admin-set-waterResistance-value">Waterdichte stof prijs (%):</label>
                            <input type="number" class="js3dv-admin-set-waterResistance-value" name="js3dv-admin-set-waterResistance-value" value="<?php echo (get_option('js3dv-admin-waterResistancePrice')) ? esc_attr(get_option('js3dv-admin-waterResistancePrice')) : ''; ?>">
                        </div>
                        <input type="submit" name="js3dv-admin-set-baseprice" value="Opslaan" class="reg-reset-api-button button-primary">
                    </div>
                </div>
                    
            </form>
        </div>
    <?php }
    
    function handle_js3dv_price_form() { 
        if (!current_user_can('manage_options')) { ?>
            <div class="error">
                <p>Something went wrong</p>
            </div>
            <?php
            return;
        }
        if(wp_verify_nonce($_POST['js3dv_price_nonce'], 'save_js3dv_price_form')) { 
            $base_price = isset($_POST['js3dv-admin-set-baseprice-value']) ? $_POST['js3dv-admin-set-baseprice-value'] : '';
            update_option('js3dv-admin-baseprice', sanitize_text_field($base_price));
            $fabric_price = isset($_POST['js3dv-admin-set-fabric-value']) ? $_POST['js3dv-admin-set-fabric-value'] : '';
            update_option('js3dv-admin-fabricprice', sanitize_text_field($fabric_price));
            $special_price = isset($_POST['js3dv-admin-set-special-value']) ? $_POST['js3dv-admin-set-special-value'] : '';
            update_option('js3dv-admin-specialprice', sanitize_text_field($special_price));
            $handle_price = isset($_POST['js3dv-admin-set-handle-value']) ? $_POST['js3dv-admin-set-handle-value'] : '';
            update_option('js3dv-admin-handlePrice', sanitize_text_field($handle_price));
            $reinforcement_price = isset($_POST['js3dv-admin-set-reinforcement-value']) ? $_POST['js3dv-admin-set-reinforcement-value'] : '';
            update_option('js3dv-admin-reinforcementPrice', sanitize_text_field($reinforcement_price));
            $panel_price = isset($_POST['js3dv-admin-set-panel-value']) ? $_POST['js3dv-admin-set-panel-value'] : '';
            update_option('js3dv-admin-panelPrice', sanitize_text_field($panel_price));
            $water_resistance_price = isset($_POST['js3dv-admin-set-waterResistance-value']) ? $_POST['js3dv-admin-set-waterResistance-value'] : '';
            update_option('js3dv-admin-waterResistancePrice', sanitize_text_field($water_resistance_price));
            ?> <div class="updated"><p>Prijs is aangepast</p></div> <?php
        } else { ?>
            <div class="error">
                <p>Something went wrong</p>
            </div>
        <?php }
    }

    function save_custom_cart_item_data_to_order($item, $cart_item_key, $values, $order) {
        foreach ($values as $key => $value) {
            if (in_array($key, ['Breedte', 'Hoogte', 'Diepte', 'Hoogte (G)', 'Korte zijde (D)', 'Lange zijde (A)', 'Zijde B en F', 'Hoogte (E)', 'Korte zijde (C)', 'radius', 'Versteviging', 'venster', 'materiaal', 'object', 'Naam'])) {
                $item->update_meta_data($key, $value);
            } elseif ($key === 'handle' && is_array($value)) {
                // Loop through each handle item and store it in order meta
                $handles_data = [];
                foreach ($value as $handle) {
                    if (isset($handle['place'], $handle['x'], $handle['y'])) {
                        $handles_data[] = $handle; // Store the handle as-is to maintain structure
                    }
                }
                if (!empty($handles_data)) {
                    $item->update_meta_data('handle', $handles_data);
                }
            } elseif ($key === 'custom_image') {
                $item->update_meta_data('custom_image', $value);
            } elseif ($key === 'custom_image_front') {
                $item->update_meta_data('custom_image_front', $value);
            } elseif ($key === 'top_pdf_image') {
                $item->update_meta_data('top_pdf_image', $value);
            } elseif ($key === 'front_pdf_image') {
                $item->update_meta_data('front_pdf_image', $value);
            }
        } 
    }

    function display_custom_dimensions_in_email($item_id, $item, $order) {
        $keys_to_include = ['Naam', 'Breedte', 'Hoogte', 'Diepte', 'Hoogte (G)', 'Korte zijde (D)', 'Lange zijde (A)', 'Zijde B en F', 'Hoogte (E)', 'Korte zijde (C)', 'radius', 'Versteviging', 'venster', 'materiaal', 'object', 'handle', 'custom_image', 'custom_image_front'];
    
        foreach ($keys_to_include as $key) {
            $value = wc_get_order_item_meta($item_id, $key, true);
    
            if ($value) {
                if ($key === 'handle' && is_array($value)) {
                    foreach ($value as $handle) {
                        if (isset($handle['place'], $handle['x'], $handle['y'])) {
                            if ($handle['place'] === 'top') {
                                echo '<p><strong>' . esc_html__('Handvat ' . ucfirst($handle['place'])) . ':</strong> ' . esc_html('Afstand van rechter zijde: ' . $handle['x'] . ' cm, Afstand van zijde A: ' . $handle['y'] . ' cm') . '</p>';
                            } else {
                                echo '<p><strong>' . esc_html__('Handvat zijde ' . ucfirst($handle['place'])) . ':</strong> ' . esc_html('Afstand van rechter zijde: ' . $handle['x'] . ' cm, Afstand van top: ' . $handle['y'] . ' cm') . '</p>';
                            }
                        }
                    }
                } elseif ($key === 'custom_image') {
                    echo '<p><strong>' . esc_html__('Boven aanzicht') . ':</strong></p>';
                    echo '<img src="' . $value . '" alt="' . esc_attr__('Boven-aanzicht') . '" style="max-width: 300px; border: 1px solid #ccc; margin-top: 10px; background-color: black;">';
                } elseif ($key === 'custom_image_front') {
                    echo '<p><strong>' . esc_html__('Voor aanzicht') . ':</strong></p>';
                    echo '<img src="' . $value . '" alt="' . esc_attr__('Voor-aanzicht') . '" style="max-width: 300px; border: 1px solid #ccc; margin-top: 10px; background-color: black;">';
                } elseif (in_array($key, ['Breedte', 'Hoogte', 'Diepte', 'Hoogte (G)', 'Korte zijde (D)', 'Lange zijde (A)', 'Diepte', 'Zijde B en F', 'Hoogte (E)', 'Korte zijde (C)', 'Lange zijde (A)'])) {
                    echo '<p><strong>' . esc_html__(ucfirst($key)) . ':</strong> ' . esc_html($value) . ' cm</p>';
                } elseif (in_array($key, ['Naam', 'Versteviging', 'venster', 'materiaal', 'object'])) {
                     echo '<p><strong>' . esc_html__(ucfirst($key)) . ':</strong> ' . esc_html($value) . '</p>';
                }  else {
                    echo '<p><strong>' . esc_html__(ucfirst($key)) . ':</strong> ' . esc_html($value) . ' cm</p>';
                }
            }
        }
    }

    function save_base64_image($base64, $file_name) {
        if (strpos($base64, 'data:image/png;base64,') === 0) {
            $base64 = str_replace('data:image/png;base64,', '', $base64);
        }
        $image_data = base64_decode($base64);
        if ($image_data !== false) {
            $file_path = wp_tempnam($file_name) . '.png';
            file_put_contents($file_path, $image_data);
            return $file_path;
        }
        return false;
    }
    

    function generate_product_pdf($name, $top_view_base64, $front_view_base64) {
        require_once plugin_dir_path(__FILE__) . 'includes/fpdf/fpdf.php';

        $top_view_image = $this->save_base64_image($top_view_base64, 'top_view');
        $front_view_image = $this->save_base64_image($front_view_base64, 'front_view');

        if (!$top_view_image || !$front_view_image) {
            return false;
        }

        // Initialize FPDF
        $pdf = new \FPDF();
        $pdf->AddPage();

        $pdf->SetTextColor(0, 0, 0);
        
        // Title
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $name, 0, 1, 'C');

        // Top View
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Bovenaanzicht', 0, 1, 'L');
        $pdf->Image($top_view_image, 10, $pdf->GetY(), 190);
        
        // Front View
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, 'Vooraanzicht', 0, 1, 'L');
        $pdf->Image($front_view_image, 10, $pdf->GetY(), 190); 

        $pdf_file = wp_tempnam($name . '_views') . '.pdf';
        $pdf->Output('F', $pdf_file);
        
        // Clean up temporary images
        unlink($top_view_image);
        unlink($front_view_image);
        
        return $pdf_file; // Return the PDF path
    }
    

    function generate_invoice_pdf($order) {
        require_once plugin_dir_path(__FILE__) . 'includes/fpdf/fpdf.php';
    
        $pdf = new \FPDF();
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        
        // === Layout constants ===
        $logo_path = plugin_dir_path(__FILE__) . 'dev-data/logo_symbol.png';
        $logo_width = 40;
        $logo_y = 0; // Top padding
        $page_width = $pdf->GetPageWidth();
        
        // === Place logo ===
        $logo_x = ($page_width - $logo_width) / 2;
        $pdf->Image($logo_path, $logo_x, $logo_y, $logo_width);
        
        // Assume logo height is same as width (square)
        $logo_height = $logo_width;
        
        // === Contact & Business Info Blocks ===
        $block_width = ($page_width - $logo_width) / 2;
        $block_line_height = 5;
        $contact_lines = 4;
        $business_lines = 3;
        
        $contact_height = $contact_lines * $block_line_height;
        $business_height = $business_lines * $block_line_height;
        
        // Center vertically next to logo
        $contact_y = 5;
        $business_y = 5;
        
        $contact_x = 0;
        $business_x = $logo_x + $logo_width;
        
        // === Contact (Left of logo) ===
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY($contact_x, $contact_y);
        $pdf->MultiCell($block_width, $block_line_height, utf8_decode(
            "Adres: Molendijk-Zuid 21-02\n" .
            "5482 WZ Schijndel\n" .
            "Telefoon: 073-234 03 03\n" .
            "info@transporthoes.shop"
        ), 0, 'C');
        
        // === Business Info (Right of logo) ===
        $pdf->SetXY($business_x, $business_y);
        $pdf->MultiCell($block_width, $block_line_height, utf8_decode(
            "KVK: 93975740\n" .
            "BTW: NL8665.91.576.B.01\n\n" .
            "IBAN: NL95 ABNA 0895 2053 51"
        ), 0, 'C');
        
        // === Move Y below the entire banner area ===
        $banner_bottom_y = max($contact_y + $contact_height, $business_y + $business_height, $logo_y + $logo_height);
        $spacing_after_banner = 5;
        $pdf->SetY($banner_bottom_y + $spacing_after_banner);
        
        // === Full-width company title image ===
        $header_image_path = plugin_dir_path(__FILE__) . 'dev-data/company_title.png';
        $header_image_height = 20;
        $pdf->Image($header_image_path, 0, $pdf->GetY(), $page_width, $header_image_height);
        
        // === Move below title image for invoice body ===
        $pdf->SetY($pdf->GetY() + $header_image_height + 5);
        
        // === Invoice heading ===
        $pdf->Ln(25);
        // $pdf->SetY(25);
        $pdf->SetX(25);   
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(0, 0, 0);

        // Get order data
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $customer_address = $order->get_billing_address_1();
        $customer_postcode_city = $order->get_billing_postcode() . ' ' . $order->get_billing_city();
        $invoice_number = $order->get_order_number();
        $invoice_date = $order->get_date_created()->format('d-m-Y');

        // Output block
        $pdf->MultiCell($pdf->GetPageWidth() - 50, 6, utf8_decode(
            "Naam/Firma : $customer_name\n" .
            "Adres : $customer_address\n" .
            "Postcode : $customer_postcode_city\n\n" .
            "Factuurnummer : 2025-$invoice_number\n" .
            "Datum : $invoice_date"
        ), 0, 'L');

        $pdf->Ln(10);
    
        // // Order Items Table Header
        $usableWidth = $pdf->GetPageWidth() - 50;
        $totalWidth = 200;
        $scalingFactor = $usableWidth / $totalWidth;

        $w_product = 80 * $scalingFactor;
        $w_qty     = 30 * $scalingFactor;
        $w_price   = 30 * $scalingFactor;
        $w_total   = 30 * $scalingFactor;

        $pdf->SetX(25);
        $pdf->SetFont('Arial', 'B', 10);
        // Table headers with top and bottom borders only
        $pdf->Cell($w_product, 10, 'Producten/Diensten', 'T');
        $pdf->Cell($w_qty, 10, 'Aantal', 'T');
        $pdf->Cell($w_price, 10, 'Prijs Exc.', 'T');
        $pdf->Cell($w_price, 10, 'BTW.', 'T');
        $pdf->Cell($w_total, 10, 'Prijs Inc.', 'T');
        $pdf->Ln();

        // Space between header and content
        $pdf->SetFont('Arial', '', 10);

        // Table rows with bottom border only
        foreach ($order->get_items() as $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $total = number_format($item->get_total() * 1.21, 2);
            $price = number_format($item->get_subtotal(), 2);
            $pdf->SetX(25);
            $pdf->Cell($w_product, 10, $product_name, 'T');
            $pdf->Cell($w_qty, 10, $quantity, 'T');
            $pdf->Cell($w_price, 10, iconv('UTF-8', 'Windows-1252//TRANSLIT', "€" . $price), 'T');
            $pdf->Cell($w_price, 10, "21%", 'T');
            $pdf->Cell($w_total, 10, iconv('UTF-8', 'Windows-1252//TRANSLIT',"€" . $total), 'T');
            $pdf->Ln();
        }

        $payment_method = $order->get_payment_method_title();
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetX(25);
        $pdf->Cell(0, 10, "Het volledige bedrag is betaald via " . $payment_method);
    
        // Totals
        $pdf->Ln(20);
        $pdf->SetFont('Arial', '', 10);
        $labelWidth = 50;   // Width for the left column (label)
        $priceWidth = 20; 
        $cellWidth = $labelWidth + $priceWidth; // Width of the block

        // Set X so it starts on the right side
        $pdf->SetX($pdf->GetPageWidth() - 25 - $cellWidth);
          // Width for the right column (amount)

        $pdf->Cell($labelWidth, 6, 'Exclusief BTW in Euro:', 0, 0, 'L');
        $pdf->Cell($priceWidth, 6, iconv('UTF-8', 'Windows-1252//TRANSLIT', '€' . number_format($order->get_subtotal(), 2)), 0, 1, 'R');
        $pdf->SetX($pdf->GetPageWidth() - 25 - $cellWidth);
        $pdf->Cell($labelWidth, 6, 'BTW Tarief in Euro 21%:', 0, 0, 'L');
        $pdf->Cell($priceWidth, 6, iconv('UTF-8', 'Windows-1252//TRANSLIT', '€' . number_format($order->get_total_tax(), 2)), 0, 1, 'R');
        $pdf->SetX($pdf->GetPageWidth() - 25 - $cellWidth);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($labelWidth, 6, 'Inclusief BTW in Euro:', 0, 0, 'L');
        $pdf->Cell($priceWidth, 6, iconv('UTF-8', 'Windows-1252//TRANSLIT', '€' . number_format($order->get_total(), 2)), 0, 1, 'R');

        $pdf->Ln(2); // Small space
        $pdf->SetX($pdf->GetPageWidth() - 25 - $cellWidth);
        $pdf->Cell($labelWidth, 6, 'Totaal:', 0, 0, 'L');
        $pdf->Cell($priceWidth, 6, iconv('UTF-8', 'Windows-1252//TRANSLIT', '€' . number_format($order->get_total(), 2)), 0, 1, 'R');
        // Save PDF
        $pdf_file = wp_tempnam("factuur_2025-" . $order->get_order_number()) . '.pdf';
        $pdf->Output('F', $pdf_file);
    
        return $pdf_file;
    }
    
    

    function attach_custom_image_to_email($attachments, $email_id, $order) {
        if (!is_a($order, 'WC_Order')) {
            return $attachments;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $top_view = wc_get_order_item_meta($item_id, 'top_pdf_image', true);
            $front_view = wc_get_order_item_meta($item_id, 'front_pdf_image', true);
            $name = wc_get_order_item_meta($item_id, 'Naam', true);            
    
            if ($top_view && $front_view) {
                $pdf_file = $this->generate_product_pdf($name, $top_view, $front_view);
    
                if ($pdf_file) {
                    $attachments[] = $pdf_file;
                }
            }
        }
        $invoice_pdf = $this->generate_invoice_pdf($order);
        if ($invoice_pdf) {
            $attachments[] = $invoice_pdf;
        }

        return $attachments;
    }
    

    // remove default meta data
    function remove_default_meta_data($formatted_meta, $item) {
        $keys_to_exclude = ['Breedte', 'Hoogte', 'Diepte', 'Hoogte (G)', 'Korte zijde (D)', 'Lange zijde (A)', 'Zijde B en F', 'Hoogte (E)', 'Korte zijde (C)', 'radius', 'Versterking', 'venster', 'materiaal', 'object', 'custom_image', 'handle', 'custom_image_front', 'Naam', 'top_pdf_image', 'front_pdf_image'];
        foreach ($formatted_meta as $key => $meta) {
            if (in_array($meta->key, $keys_to_exclude)) {
                unset($formatted_meta[$key]);
            }
        }
        return $formatted_meta;
    }
}

new JS3DV_Plugin();