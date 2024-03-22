<?php 

namespace App\Support\Exchange\Currencies;

use DateTime;


class PYG{
    public static function convertFromDollar(DateTime $date, float $amount){
        return \Brunoinds\ParaguayDolarLaravel\Exchange::on($date)->convert(\Brunoinds\ParaguayDolarLaravel\Enums\Currency::USD, $amount)->to(\Brunoinds\ParaguayDolarLaravel\Enums\Currency::PYG);
    }
    public static function convertToDollar(DateTime $date, float $amount){
        return \Brunoinds\ParaguayDolarLaravel\Exchange::on($date)->convert(\Brunoinds\ParaguayDolarLaravel\Enums\Currency::PYG, $amount)->to(\Brunoinds\ParaguayDolarLaravel\Enums\Currency::USD);
    }
}