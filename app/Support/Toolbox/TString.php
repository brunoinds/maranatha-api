<?php

namespace App\Support\Toolbox;


class TString{
    public static function replaceAll($string, $search, $replace){
        return str_replace($search, $replace, $string);
    }

    public static function new($string){
        return new TString($string);
    }
}