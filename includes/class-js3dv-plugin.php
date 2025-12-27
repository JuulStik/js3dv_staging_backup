<?php
namespace JS\JS3DV;

if (!defined('ABSPATH')) exit;

class JS3DV_Plugin {
    public function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        if (!class_exists('WooCommerce')) return;

        require_once JS3DV_PATH . 'includes/class-js3dv-calculator.php';
        require_once JS3DV_PATH . 'includes/class-js3dv-admin.php';
        require_once JS3DV_PATH . 'includes/class-js3dv-rest.php';
        require_once JS3DV_PATH . 'includes/class-js3dv-cart.php';
        require_once JS3DV_PATH . 'includes/class-js3dv-pdf.php';
        require_once JS3DV_PATH . 'includes/traits/trait-image-handler.php';
        require_once JS3DV_PATH . 'includes/services/class-js3dv-translator.php';

        new Admin();
        new REST_API();
        new Cart_Handler();
    }
}
