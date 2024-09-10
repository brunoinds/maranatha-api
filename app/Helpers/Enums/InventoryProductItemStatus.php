<?php

namespace App\Helpers\Enums;


enum InventoryProductItemStatus: string
{
    case InStock = 'InStock';
    case Sold = 'Sold';
    case Loaned = 'Loaned';
    case InRepair = 'InRepair';
    case WriteOff = 'WriteOff';

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
            case self::Loaned->value:
                return 'Prestado';
            case self::InRepair->value:
                return 'En reparación';
            case self::WriteOff->value:
                return 'Dado de baja';
            default:
                return '';
        }
    }
}
