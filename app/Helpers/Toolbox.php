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
    public static function validateImageBase64(string|null $base64Image, int|null $maxSizeInBytes = 2048 * 1024) // 2MB
    { 
        if (!is_null($base64Image) && mb_strlen($base64Image) > 40){
            $imageSize = (fn() => strlen(base64_decode($base64Image)))();
            if ($imageSize > $maxSizeInBytes) {
                
                return Toolbox::toObject([
                    'isImage' => true,
                    'isValid' => false,
                    'message' => "Image exceeds max size (maximum $maxSizeInBytes bytes)"
                ]);
            }else{
                return Toolbox::toObject([
                    'isImage' => true,
                    'isValid' => true,
                    'message' => 'Image is valid'
                ]);
            }
        }else{
            return Toolbox::toObject([
                'isImage' => false,
                'isValid' => false,
                'message' => 'No image provided'
            ]);
        }
    }
}