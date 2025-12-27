<?php
namespace JS\JS3DV;

class Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function menu() {
        add_menu_page(
            'JS3DV Settings',
            'JS3DV Prices',
            'manage_options',
            'js3dv-settings',
            [$this, 'page'],
            'dashicons-money-alt',
            100
        );
    }

    public function register_settings() {
        $fields = [
            'baseprice' => 'Basisprijs',
            'fabricprice' => 'Stofprijs per m²',
            'specialprice' => 'Speciale vorm toeslag',
            'handleprice' => 'Prijs per handvat',
            'reinforcementprice' => 'Versteviging',
            'panelprice' => 'Venster (A5)',
            'waterresistanceprice' => 'Waterdicht (%)',
        ];

        foreach ($fields as $id => $label) {
            register_setting('js3dv_options', "js3dv_$id");
            add_settings_field(
                "js3dv_$id",
                $label,
                [$this, 'field_callback'],
                'js3dv-settings',
                'js3dv_options',
                ['id' => "js3dv_$id"]
            );
        }
    }

    public function field_callback($args) {
        $id = $args['id'];
        $value = get_option($id);
        printf('<input type="number" step="0.01" name="%s" value="%s" class="regular-text">', $id, esc_attr($value));
    }

    public function page() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1>JS3DV Prijzen Instellen</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('js3dv_options');
                do_settings_sections('js3dv-settings');
                submit_button('Opslaan');
                ?>
            </form>
        </div>
        <?php
    }
}