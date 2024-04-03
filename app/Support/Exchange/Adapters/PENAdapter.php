<?php 

namespace App\Support\Exchange\Adapters;

use SoftinkLab\LaravelKeyvalueStorage\Facades\KVOption;


class PENAdapter{
    public function get()
    {
        return KVOption::get('Brunoinds\SunatDolarLaravel');
    }

    public function set(string $value)
    {
        return KVOption::set('Brunoinds\SunatDolarLaravel', $value);
    }

    public static function getStore(): \Brunoinds\SunatDolarLaravel\Store\Store
    {
        $adapter = new self();
        $store = \Brunoinds\SunatDolarLaravel\Store\Store::newFromAdapter($adapter);
        return $store;
    }
}