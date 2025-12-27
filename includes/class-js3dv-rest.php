<?php
namespace JS\JS3DV;

class REST_API {
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('js3dv/v1', '/add-to-cart', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_add_to_cart'],
            'permission_callback' => [$this, 'check_nonce'],
        ]);
    }

    public function check_nonce($request) {
        return wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest');
    }

    public function handle_add_to_cart($request) {
        if (!class_exists('WooCommerce')) return new \WP_Error('wc_missing', 'WooCommerce not active', ['status' => 500]);

        WC()->frontend_includes();
        if (null === WC()->session) wc()->session = new \WC_Session_Handler();
        if (null === WC()->customer) wc()->customer = new \WC_Customer(get_current_user_id(), true);
        if (null === WC()->cart) wc()->cart = new \WC_Cart();

        $data = $request->get_json_params();
        $quantity = intval($data['quantity'] ?? 1);
        $handle_count = is_array($data['handle'] ?? []) ? count($data['handle']) : 0;

        $calculator = new Price_Calculator();
        $price = $calculator->calculate($data, $quantity, $handle_count);

        $cart_item_data = $this->prepare_cart_data($data);
        $cart_item_data['price'] = $price;

        $key = WC()->cart->add_to_cart(JS3DV_PRODUCT_ID, $quantity, 0, [], $cart_item_data);

        return rest_ensure_response([
            'success' => (bool)$key,
            'cart_item_key' => $key,
            'price' => $price,
        ]);
    }

    private function prepare_cart_data($data) {
        $allowed = ['object','Naam','Versteviging','venster','materiaal','radius'];
        $cart_data = ['custom_title' => 'Transporthoes voor ' . ($data['object'] ?? 'object')];

        foreach ($data as $k => $v) {
            if (in_array($k, $allowed) || strpos($k, 'image') !== false || $k === 'handle') {
                $cart_data[$k] = is_array($v) ? array_map('sanitize_text_field', $v) : sanitize_text_field($v);
            } elseif (!in_array($k, ['quantity','handle'])) {
                $cart_data[$k] = floatval($v);
            }
        }
        return $cart_data;
    }
}