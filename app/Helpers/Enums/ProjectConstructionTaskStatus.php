<?php

namespace App\Helpers\Enums;


enum ProjectConstructionTaskStatus: string
{
    case Ongoing = 'Ongoing';
    case WaitingToStart = 'WaitingToStart';
    case Finished = 'Finished';

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
            case self::Ongoing:
                return 'En Curso';
            case self::WaitingToStart:
                return 'Esperando Inicio';
            case self::Finished:
                return 'Concluído';
            default:
                return 'Unknown';
        }
    }
}
