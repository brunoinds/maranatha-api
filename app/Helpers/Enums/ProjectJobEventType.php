<?php

namespace App\Helpers\Enums;

enum ProjectJobEventType: string
{
    case NewConstruction = 'NewConstruction';
    case Renovation = 'Renovation';
    case Repair = 'Repair';
    case Painting = 'Painting';
    case Addition = 'Addition';
    case Fundraising = 'Fundraising';
    case Medical = 'Medical';
    case VBS = 'VBS';
    case Evangelism = 'Evangelism';
    case Landscaping = 'Landscaping';
    case Other = 'Other';
    case Meetings = 'Meetings';
    case Maintenance = 'Maintenance';

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
            case self::NewConstruction:
                return 'Construcción Nueva';
            case self::Renovation:
                return 'Renovación';
            case self::Repair:
                return 'Reparación';
            case self::Painting:
                return 'Pintura';
            case self::Addition:
                return 'Adición';
            case self::Fundraising:
                return 'Recaudación de Fondos';
            case self::Medical:
                return 'Médico';
            case self::VBS:
                return 'Escuela Bíblica de Vacaciones';
            case self::Evangelism:
                return 'Evangelismo';
            case self::Landscaping:
                return 'Paisajismo';
            case self::Other:
                return 'Otro';
            case self::Meetings:
                return 'Reuniones';
            case self::Maintenance:
                return 'Mantenimiento';
            default:
                return 'Unknown';
        }
    }
}
