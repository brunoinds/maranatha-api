<?php 

namespace App\Support\Exchange\Adapters;

use SoftinkLab\LaravelKeyvalueStorage\Facades\KVOption;


class PENAdapter{
    private static $db = [
        'name' => 'Brunoinds\SunatDolarLaravel',
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

    public static function getStore(): \Brunoinds\SunatDolarLaravel\Store\Store
    {
        $adapter = new self();
        $store = \Brunoinds\SunatDolarLaravel\Store\Store::newFromAdapter($adapter);
        return $store;
    }
}