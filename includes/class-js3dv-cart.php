<?php
namespace JS\JS3DV;

class Cart_Handler {
    use \JS\JS3DV\Traits\Image_Handler;

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_filter('woocommerce_cart_item_name', [$this, 'item_name'], 10, 3);
        add_filter('woocommerce_cart_item_price', [$this, 'item_price'], 10, 2);
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'item_thumbnail'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'item_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'set_price']);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_to_order'], 10, 4);
        add_action('woocommerce_order_item_meta_start', [$this, 'email_display'], 10, 3);
        add_filter('woocommerce_email_attachments', [$this, 'attach_pdfs'], 10, 3);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'hide_meta'], 10, 2);
    }

    public function enqueue() {
        if (!is_page('transporthoes-samenstellen')) return;

        wp_enqueue_script('js3dv-main', JS3DV_URL . 'assets/js/3d-visualization_hardcode.js', ['jquery'], null, true);
        wp_enqueue_style('js3dv-style', JS3DV_URL . 'assets/css/3d-visualization.css');

        wp_localize_script('js3dv-main', 'js3dvSettings', [
            'url' => JS3DV_URL,
            'api' => rest_url('js3dv/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'prices' => (new Price_Calculator())->get_options(), // optional
        ]);
    }

    public function item_name($name, $item) {
        return $item['custom_title'] ?? 'Transporthoes';
    }

    public function item_price($price, $item) {
        return isset($item['price']) ? wc_price($item['price']) : $price;
    }

    public function set_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        foreach ($cart->get_cart() as $item) {
            if (isset($item['price'])) {
                $item['data']->set_price($item['price']);
            }
        }
    }

    public function item_thumbnail($thumb, $item) {
        if (!empty($item['custom_image'])) {
            return '<img src="' . esc_url($item['custom_image']) . '" style="width:100px;">';
        }
        return $thumb;
    }

    public function item_data($data, $item) {
        $keys = ['Breedte','Hoogte','Diepte','Hoogte (G)','Korte zijde (D)','Lange zijde (A)','Zijde B en F','Hoogte (E)','Korte zijde (C)','radius','Naam','Versteviging','venster','materiaal','object'];
        foreach ($keys as $k) {
            if (isset($item[$k])) {
                $data[] = ['key' => $k, 'value' => $item[$k] . (is_numeric($item[$k]) ? ' cm' : '')];
            }
        }
        if (!empty($item['handle'])) {
            foreach ($item['handle'] as $i => $h) {
                $place = $h['place'] === 'top' ? 'top' : 'zijde ' . $h['place'];
                $data[] = ['key' => "Handvat $place", 'value' => "X: {$h['x']}cm, Y: {$h['y']}cm"];
            }
        }
        return $data;
    }

    public function save_to_order($item, $key, $values, $order) {
        foreach ($values as $k => $v) {
            if (in_array($k, ['custom_image','custom_image_front','top_pdf_image','front_pdf_image','handle']) || strpos($k, 'image') !== false) {
                $item->update_meta_data($k, $v);
            } elseif (!in_array($k, ['price','custom_title'])) {
                $item->update_meta_data($k, $v);
            }
        }
    }

    public function email_display($item_id, $item) {
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

    public function attach_pdfs($attachments, $id, $order) {
        if (!is_a($order, 'WC_Order')) return $attachments;

        $pdf = new PDF_Generator();
        foreach ($order->get_items() as $item) {
            $top = $item->get_meta('top_pdf_image');
            $front = $item->get_meta('front_pdf_image');
            $name = $item->get_meta('Naam') ?: 'Transporthoes';

            if ($top && $front) {
                $file = $pdf->product_overview($name, $top, $front);
                if ($file) $attachments[] = $file;
            }
        }

        $invoice = $pdf->invoice($order);
        if ($invoice) $attachments[] = $invoice;

        return $attachments;
    }

    public function hide_meta($meta) {
        $hide = ['custom_image','handle','top_pdf_image','front_pdf_image','price','custom_title'];
        foreach ($meta as $k => $m) {
            if (in_array($m->key, $hide)) unset($meta[$k]);
        }
        return $meta;
    }
}