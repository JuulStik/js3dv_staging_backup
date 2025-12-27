<?php
namespace JS\JS3DV;

class Translator
{
    private array $translations = [
        'waaier' => [
            'height' => 'Hoogte',
            'depth'  => 'Diepte',
        ],
    ];

    public function translate(string $object, string $key): string
    {
        $object = strtolower($object);
        return $this->translations[$object][$key] ?? $key;
    }
}
