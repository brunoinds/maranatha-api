<?php

namespace App\Helpers\Enums;


enum MoneyType: string
{
    case PEN = 'PEN';
    case BRL = 'BRL';
    case USD = 'USD';
    case PYG = 'PYG';


    public static function toArray():array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = $case->value;
        }

        return $items;
    }
    public static function toAssociativeArray(mixed $value = []): array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[$case->value] = $value;
        }

        return $items;
    }
}
