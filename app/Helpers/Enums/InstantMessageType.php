<?php

namespace App\Helpers\Enums;


enum InstantMessageType: string
{
    case Text = 'Text';
    case Image = 'Image';
    case Video = 'Video';
    case Audio = 'Audio';
    case Voice = 'Voice';
    case Document = 'Document';
    case Model = 'Model';


    public static function toArray(): array
    {
        $items = [];
        foreach (self::cases() as $case) {
            $items[] = $case->value;
        }

        return $items;
    }
}
