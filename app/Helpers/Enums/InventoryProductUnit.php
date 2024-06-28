<?php

namespace App\Helpers\Enums;


enum InventoryProductUnit: string
{
    case Units = 'Units';
    case Liters = 'Liters';
    case Kilograms = 'Kilograms';
    case Meters = 'Meters';
    case Boxes = 'Boxes';
    case Buckets = 'Buckets';
    case Bags = 'Bags';
    case Gallons = 'Gallons';
    case Packs = 'Packs';


    public static function toArray():array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = $case->value;
        }

        return $items;
    }
}
