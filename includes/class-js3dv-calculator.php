<?php
namespace JS\JS3DV;

class Price_Calculator {
    private $opts;

    public function __construct() {
        $this->opts = [
            'base'           => floatval(get_option('js3dv_baseprice', 100)),
            'fabric'         => floatval(get_option('js3dv_fabricprice', 21)),
            'special'        => floatval(get_option('js3dv_specialprice', 25)),
            'handle'         => floatval(get_option('js3dv_handleprice', 10)),
            'reinforcement'  => floatval(get_option('js3dv_reinforcementprice', 30)),
            'panel'          => floatval(get_option('js3dv_panelprice', 0)),
            'water_resist'   => floatval(get_option('js3dv_waterresistanceprice', 15)),
        ];
    }

    public function calculate($data, $quantity = 1, $handle_count = 0) {
        $type = $data['object'] ?? 'Rechthoek';
        $method = 'calc_' . strtolower(str_replace([' ', ' met '], ['_', ''], $type));

        $fabric_cost = method_exists($this, $method) ? $this->$method($data) : 999999;

        $total = $this->opts['base'] + $fabric_cost + ($this->opts['handle'] * $handle_count);

        if (($data['materiaal'] ?? '') !== 'Waterafstotend') {
            $total *= (1 + $this->opts['water_resist'] / 100);
        }
        if (($data['Versteviging'] ?? 'Nee') !== 'Nee') $total += $this->opts['reinforcement'];
        if (($data['venster'] ?? 'Nee') !== 'Nee') $total += $this->opts['panel'];

        return round($total * $quantity, 2);
    }

    private function fabric_size($h, $w) { return ($h/100) * ($w/100); }

    private function calc_rechthoek($d) { return $this->calc_rectangle($d); }
    private function calc_rechthoekmetronding($d) { return $this->calc_rectangle($d) + $this->opts['special']; }

    private function calc_rectangle($d) {
        $w = $d['Breedte']; $h = $d['Hoogte']; $dep = $d['Diepte'];
        return $this->fabric_size($dep,$w) + 2*$this->fabric_size($h,$w) + 2*$this->fabric_size($h,$dep);
    }

    private function calc_zeshoek($d) {
        $w = $d['Lange zijde (A)']; $iw = $d['Korte zijde (D)'];
        $h = $d['Hoogte (G)']; $dep = $d['Diepte']; $id = $d['Zijde B en F'];
        $ce = sqrt(pow($dep-$id,2) + pow(($w-$iw)/2,2));

        return $this->fabric_size($dep,$w) +
               $this->fabric_size($h,$w) +
               2*$this->fabric_size($h,$id) +
               2*$this->fabric_size($h,$ce) +
               $this->fabric_size($h,$iw);
    }

    private function calc_waaier($d) {
        $tw = $d['Lange zijde (A)']; $bw = $d['Korte zijde (C)'];
        $h = $d['Hoogte (E)']; $dep = $d['Diepte'];
        $bd = sqrt(pow(($tw-$bw)/2,2) + pow($dep,2));

        return $this->fabric_size($dep,$tw) +
               $this->fabric_size($h,$tw) +
               2*$this->fabric_size($h,$bd) +
               $this->fabric_size($h,$bw);
    }
}