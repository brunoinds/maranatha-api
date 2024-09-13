<?php

namespace App\Helpers\Enums;


enum ProjectStructureBuildingType: string
{
    case Church = 'Church';
    case School = 'School';
    case Well = 'Well';
    case Other = 'Other';

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
            case self::Church:
                return 'Iglesia';
            case self::School:
                return 'Escuela';
            case self::Well:
                return 'Pozo';
            case self::Other:
                return 'Otro';
            default:
                return 'Unknown';
        }
    }
}
