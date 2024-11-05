<?php

namespace App\Helpers\Enums;


enum InventoryProductItemUncountableStatus: string
{
    case InStock = 'InStock';
    case Sold = 'Sold';


    public static function toArray():array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = $case->value;
        }

        return $items;
    }


    public static function getDescription(string $status):string
    {
        switch ($status){
            case self::InStock->value:
                return 'En stock';
            case self::Sold->value:
                return 'Vendido';
            default:
                return '';
        }
    }
}
