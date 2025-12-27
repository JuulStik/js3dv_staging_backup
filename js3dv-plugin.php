<?php
/**
 * Plugin Name: 3D Animation Tool
 * Description: 3D cover configurator with dynamic pricing for WooCommerce
 * Version:     2.0.0
 * Author:      Juul Stikkelbroeck
 * Text Domain: js3dv
 */

if (!defined('ABSPATH')) exit;

define('JS3DV_VERSION', '2.0.0');
define('JS3DV_PATH', plugin_dir_path(__FILE__));
define('JS3DV_URL', plugin_dir_url(__FILE__));
define('JS3DV_PRODUCT_ID', 10658);

require_once JS3DV_PATH . 'includes/class-js3dv-plugin.php';

new JS\JS3DV\JS3DV_Plugin();