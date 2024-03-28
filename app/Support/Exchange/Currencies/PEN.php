<?php 

namespace App\Support\Exchange\Currencies;

use DateTime;
use Illuminate\Support\Facades\Log;


class PEN{
    public static function convertFromDollar(DateTime $date, float $amount){
        try {
            return \Brunoinds\SunatDolarLaravel\Exchange::on($date)->convert(\Brunoinds\SunatDolarLaravel\Enums\Currency::USD, $amount)->to(\Brunoinds\SunatDolarLaravel\Enums\Currency::PEN);
        } catch (\Throwable $th) {
            Log::warning('Failed to convert USD to PEN', ['date' => $date, 'amount' => $amount, 'error' => $th->getMessage()]);
            return 0;
        }
    }
    public static function convertToDollar(DateTime $date, float $amount){
        try {
            return \Brunoinds\SunatDolarLaravel\Exchange::on($date)->convert(\Brunoinds\SunatDolarLaravel\Enums\Currency::PEN, $amount)->to(\Brunoinds\SunatDolarLaravel\Enums\Currency::USD);
        } catch (\Throwable $th) {
            Log::warning('Failed to convert PEN to USD', ['date' => $date, 'amount' => $amount, 'error' => $th->getMessage()]);
            return 0;
        }
    }
}