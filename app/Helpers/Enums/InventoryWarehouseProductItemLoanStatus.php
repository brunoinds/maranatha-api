<?php

namespace App\Helpers\Enums;


enum InventoryWarehouseProductItemLoanStatus: string
{
    case SendingToLoan = 'SendingToLoan';
    case OnLoan = 'OnLoan';
    case ReturningToWarehouse = 'ReturningToWarehouse';
    case Returned = 'Returned';

    public static function toArray():array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = $case->value;
        }

        return $items;
    }
}
