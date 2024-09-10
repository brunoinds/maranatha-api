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

    public static function getDescription(string $status):string
    {
        switch ($status){
            case self::SendingToLoan->value:
                return 'En camino';
            case self::OnLoan->value:
                return 'Prestado';
            case self::ReturningToWarehouse->value:
                return 'Devolviendo a almacén';
            case self::Returned->value:
                return 'Devuelto';
            default:
                return '';
        }
    }
}
