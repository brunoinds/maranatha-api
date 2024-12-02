<?php

namespace App\Helpers\Enums;


enum InventoryWarehouseOutcomeRequestType: string
{
    case Loans = 'Loans';
    case Outcomes = 'Outcomes';

    public static function toArray():array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = $case->value;
        }

        return $items;
    }

}
