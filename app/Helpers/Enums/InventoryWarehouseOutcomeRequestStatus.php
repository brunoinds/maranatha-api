<?php

namespace App\Helpers\Enums;


enum InventoryWarehouseOutcomeRequestStatus: string
{
    case Draft = 'Draft';
    case Requested = 'Requested';
    case Rejected = 'Rejected';
    case Approved = 'Approved';
    case Dispatched = 'Dispatched';
    case OnTheWay = 'OnTheWay';
    case Delivered = 'Delivered';
    case Finished = 'Finished';

    public static function toArray():array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = $case->value;
        }

        return $items;
    }

    private static function getListOfHierarchy():array
    {
        return [
            self::Draft => 0,
            self::Requested => 1,
            self::Rejected => 2,
            self::Approved => 3,
            self::Dispatched => 4,
            self::OnTheWay => 5,
            self::Delivered => 6,
            self::Finished => 7
        ];
    }

    public static function getHierarchyOf(InventoryWarehouseOutcomeRequestStatus $status): int
    {
        return self::getListOfHierarchy()[$status->value];
    }
}
