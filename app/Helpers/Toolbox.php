<?php

namespace App\Helpers;

class Toolbox{
    public static function moneyPrefix(string $moneyType): string{
        switch($moneyType){
            case 'PEN':
                return "S/.";
            case 'USD':
                return "$";
            default:
                return "S/.";
        }
    }
    public static function toObject(array $array): object{
        return json_decode(json_encode($array));
    }
}