<?php

namespace App\Support\Toolbox;

use Illuminate\Support\Str;


class TString{
    public static function replaceAll($string, $search, $replace){
        return str_replace($search, $replace, $string);
    }

    public static function new($string){
        return new TString($string);
    }

    public static function generateRandomBatch():string
    {
        $uuid = Str::uuid();
        $uuid = strtoupper(str_replace('-', '', $uuid));
        $batch = substr($uuid, -10);
        $batch = substr_replace($batch, '-', 3, 0);
        return $batch;
    }
}
