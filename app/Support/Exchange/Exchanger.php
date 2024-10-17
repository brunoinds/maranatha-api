<?php

namespace App\Support\Exchange;

use DateTime;
use App\Support\Exchange\Currencies\BRL;
use App\Support\Exchange\Currencies\PYG;
use App\Support\Exchange\Currencies\PEN;
use App\Helpers\Enums\MoneyType;


class Exchanger{
    public static function on(DateTime $date): ExchangeDate
    {
        return new ExchangeDate($date);
    }
    public static function now():ExchangeDate
    {
        return new ExchangeDate(new DateTime());
    }
}


class ExchangeDate{
    public DateTime $date;

    public function __construct(DateTime $date){
        $this->date = $date;
    }

    public function convert(float $amount, MoneyType $from, MoneyType $to): float{
        if ($from === MoneyType::USD){
            return $this->convertFromDollar($amount, $to);
        }elseif ($to === MoneyType::USD){
            return $this->convertToDollar($amount, $from);
        }else{
            $amountInDollar = $this->convertToDollar($amount, $from);
            return $this->convertFromDollar($amountInDollar, $to);
        }
    }

    private function convertToDollar(float $amount, MoneyType $from): float{
        if ($from === MoneyType::PEN){
            return PEN::convertToDollar($this->date, $amount);
        }elseif ($from === MoneyType::BRL){
            return BRL::convertToDollar($this->date, $amount);
        }elseif ($from === MoneyType::PYG){
            return PYG::convertToDollar($this->date, $amount);
        }
    }
    private function convertFromDollar(float $amount, MoneyType $to): float{
        if ($to === MoneyType::PEN){
            return PEN::convertFromDollar($this->date, $amount);
        }elseif ($to === MoneyType::BRL){
            return BRL::convertFromDollar($this->date, $amount);
        }elseif ($to === MoneyType::PYG){
            return PYG::convertFromDollar($this->date, $amount);
        }elseif ($to === MoneyType::USD){
            return $amount;
        }
    }
}
