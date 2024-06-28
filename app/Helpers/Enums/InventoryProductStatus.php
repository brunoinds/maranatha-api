<?php

namespace App\Helpers\Enums;


enum InventoryProductStatus: string
{
    case Active = 'Active';
    case Inactive = 'Inactive';

    public static function toArray():array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = $case->value;
        }

        return $items;
    }
}
