<?php

namespace App\Support\Exchange\Currencies;

use DateTime;
use Illuminate\Support\Facades\Log;


class COP{
    public static function convertFromDollar(DateTime $date, float $amount){
        //Check if float is zero:
        if($amount == 0){
            return 0;
        }

        try {
            \Brunoinds\CurrencyGetGetApiLaravel\Exchange::useStore(\App\Support\Exchange\Adapters\COPAdapter::getStore());
            return \Brunoinds\CurrencyGetGetApiLaravel\Exchange::on($date)->convert(\Brunoinds\CurrencyGetGetApiLaravel\Enums\Currency::USD, $amount)->to(\Brunoinds\CurrencyGetGetApiLaravel\Enums\Currency::COP);
        } catch (\Throwable $th) {
            Log::warning('Failed to convert USD to COP', ['date' => $date, 'amount' => $amount, 'error' => $th->getMessage()]);
            return 0;
        }
    }
    public static function convertToDollar(DateTime $date, float $amount){
        //Check if float is zero:
        if($amount == 0){
            return 0;
        }

        try {
            \Brunoinds\CurrencyGetGetApiLaravel\Exchange::useStore(\App\Support\Exchange\Adapters\COPAdapter::getStore());
            return \Brunoinds\CurrencyGetGetApiLaravel\Exchange::on($date)->convert(\Brunoinds\CurrencyGetGetApiLaravel\Enums\Currency::COP, $amount)->to(\Brunoinds\CurrencyGetGetApiLaravel\Enums\Currency::USD);
        } catch (\Throwable $th) {
            Log::warning('Failed to convert COP to USD', ['date' => $date, 'amount' => $amount, 'error' => $th->getMessage()]);
            return 0;
        }
    }
}
