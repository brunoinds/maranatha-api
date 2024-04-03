<?php 

namespace App\Support\Exchange\Adapters;

use SoftinkLab\LaravelKeyvalueStorage\Facades\KVOption;


class PYGAdapter{
    private static $db = [
        'name' => 'Brunoinds\ParaguayDolarLaravel',
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

    public static function getStore(): \Brunoinds\ParaguayDolarLaravel\Store\Store
    {
        $adapter = new self();
        $store = \Brunoinds\ParaguayDolarLaravel\Store\Store::newFromAdapter($adapter);
        return $store;
    }
}