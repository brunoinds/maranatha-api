<?php 

namespace App\Support\Exchange\Adapters;

use SoftinkLab\LaravelKeyvalueStorage\Facades\KVOption;


class BRLAdapter{
    public function get()
    {
        return KVOption::get('Brunoinds\FrankfurterLaravel');
    }

    public function set(string $value)
    {
        return KVOption::set('Brunoinds\FrankfurterLaravel', $value);
    }

    public static function getStore(): \Brunoinds\FrankfurterLaravel\Store\Store
    {
        $adapter = new self();
        $store = \Brunoinds\FrankfurterLaravel\Store\Store::newFromAdapter($adapter);
        return $store;
    }
}