<?php
// includes/traits/trait-image-handler.php
namespace JS\JS3DV\Traits;

trait Image_Handler {
    public function save_base64($base64, $prefix = 'img') {
        $base64 = preg_replace('#^data:image/\w+;base64,#i', '', $base64);
        $data = base64_decode($base64);
        if (!$data) return false;
        $file = wp_tempnam($prefix) . '.png';
        file_put_contents($file, $data);
        return $file;
    }
}