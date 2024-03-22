<?php 

namespace App\Support\Exchange\Currencies;

use DateTime;


class PEN{
    public static function convertFromDollar(DateTime $date, float $amount){
        return \Brunoinds\SunatDolarLaravel\Exchange::on($date)->convert(\Brunoinds\SunatDolarLaravel\Enums\Currency::USD, $amount)->to(\Brunoinds\SunatDolarLaravel\Enums\Currency::PEN);
    }
    public static function convertToDollar(DateTime $date, float $amount){
        return \Brunoinds\SunatDolarLaravel\Exchange::on($date)->convert(\Brunoinds\SunatDolarLaravel\Enums\Currency::PEN, $amount)->to(\Brunoinds\SunatDolarLaravel\Enums\Currency::USD);
    }
}