<?php 

namespace App\Support\Exchange\Currencies;

use DateTime;
use Illuminate\Support\Facades\Log;


class BRL{
    public static function convertFromDollar(DateTime $date, float $amount){
        try {
            \Brunoinds\FrankfurterLaravel\Exchange::useStore(\App\Support\Exchange\Adapters\BRLAdapter::getStore());
            return \Brunoinds\FrankfurterLaravel\Exchange::on($date)->convert(\Brunoinds\FrankfurterLaravel\Enums\Currency::USD, $amount)->to(\Brunoinds\FrankfurterLaravel\Enums\Currency::BRL);
        } catch (\Throwable $th) {
            Log::warning('Failed to convert USD to BRL', ['date' => $date, 'amount' => $amount, 'error' => $th->getMessage()]);
            return 0;
        }
    }
    public static function convertToDollar(DateTime $date, float $amount){
        try {
            \Brunoinds\FrankfurterLaravel\Exchange::useStore(\App\Support\Exchange\Adapters\BRLAdapter::getStore());
            return \Brunoinds\FrankfurterLaravel\Exchange::on($date)->convert(\Brunoinds\FrankfurterLaravel\Enums\Currency::BRL, $amount)->to(\Brunoinds\FrankfurterLaravel\Enums\Currency::USD);
        } catch (\Throwable $th) {
            Log::warning('Failed to convert BRL to USD', ['date' => $date, 'amount' => $amount, 'error' => $th->getMessage()]);
            return 0;
        }
    }
}