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

    public static function natures(): array{
        return [
            'Integer' => [InventoryProductUnit::Bags, InventoryProductUnit::Units, InventoryProductUnit::Boxes, InventoryProductUnit::Buckets, InventoryProductUnit::Bags, InventoryProductUnit::Packs],
            'Float' => [InventoryProductUnit::Liters, InventoryProductUnit::Kilograms, InventoryProductUnit::Meters, InventoryProductUnit::Gallons]
        ];
    }

    public static function naturesValues(): array{
        $natures = self::natures();
        return [
            'Integer' => array_map(function($item){
                return $item->value;
            }, $natures['Integer']),
            'Float' => array_map(function($item){
                return $item->value;
            }, $natures['Float']),
        ];
    }

    public static function getNature(InventoryProductUnit $enum): string|null{
        foreach (self::natures() as $nature => $units){
            if(in_array($enum, $units)){
                return $nature;
            }
        }
        return null;
    }
}
