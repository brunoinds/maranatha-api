<?php 

namespace App\Support\Exchange\Currencies;

use DateTime;


class BRL{
    public static function convertFromDollar(DateTime $date, float $amount){
        return \Brunoinds\FrankfurterLaravel\Exchange::on($date)->convert(\Brunoinds\FrankfurterLaravel\Enums\Currency::USD, $amount)->to(\Brunoinds\FrankfurterLaravel\Enums\Currency::BRL);
    }
    public static function convertToDollar(DateTime $date, float $amount){
        return \Brunoinds\FrankfurterLaravel\Exchange::on($date)->convert(\Brunoinds\FrankfurterLaravel\Enums\Currency::BRL, $amount)->to(\Brunoinds\FrankfurterLaravel\Enums\Currency::USD);
    }
}