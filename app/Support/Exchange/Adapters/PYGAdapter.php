<?php 

namespace App\Support\Exchange\Adapters;

use SoftinkLab\LaravelKeyvalueStorage\Facades\KVOption;


class PYGAdapter{
    public function get()
    {
        return KVOption::get('Brunoinds\ParaguayDolarLaravel');
    }

    public function set(string $value)
    {
        return KVOption::set('Brunoinds\ParaguayDolarLaravel', $value);
    }

    public static function getStore(): \Brunoinds\ParaguayDolarLaravel\Store\Store
    {
        $adapter = new self();
        $store = \Brunoinds\ParaguayDolarLaravel\Store\Store::newFromAdapter($adapter);
        return $store;
    }
}