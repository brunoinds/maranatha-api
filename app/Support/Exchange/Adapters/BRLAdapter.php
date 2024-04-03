<?php 

namespace App\Support\Exchange\Adapters;

use SoftinkLab\LaravelKeyvalueStorage\Facades\KVOption;


class BRLAdapter{
    private static $db = [
        'name' => 'Brunoinds\FrankfurterLaravel',
        'value' => null,
    ];
    public function get()
    {
        if (self::$db['value'] !== null) {
            return self::$db['value'];
        }

        self::$db['value'] = KVOption::get(self::$db['name']);
        return self::$db['value'];
    }

    public function set(string $value)
    {
        KVOption::set(self::$db['name'], $value);
        self::$db['value'] = $value;
    }

    public static function getStore(): \Brunoinds\FrankfurterLaravel\Store\Store
    {
        $adapter = new self();
        $store = \Brunoinds\FrankfurterLaravel\Store\Store::newFromAdapter($adapter);
        return $store;
    }
}