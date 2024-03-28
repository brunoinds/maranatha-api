<?php

namespace App\Helpers;

class Toolbox{
    public static function moneyPrefix(string $moneyType): string{
        switch($moneyType){
            case 'PEN':
                return "S/.";
            case 'USD':
                return "$";
            case 'BRL':
                return "R$";
            case 'PYG':
                return "Gs.";
            default:
                return "S/.";
        }
    }
    public static function toObject(array $array): object{
        return json_decode(json_encode($array));
    }
    public static function validateImageBase64(string|null $base64Image, int|null $maxSizeInBytes = null)
    { 
        $maxSizeInBytes = $maxSizeInBytes ?? env('APP_MAXIMUM_UPLOAD_SIZE') ?? 2048 * 1024;
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

    public static function countryName(string $countryCode)
    {
        if ($countryCode === 'BR'){
            return 'Brazil';
        }elseif ($countryCode === 'PE'){
            return 'Peru';
        }elseif ($countryCode === 'PY'){
            return 'Paraguay';
        }elseif ($countryCode === 'US'){
            return 'United States';
        }else{
            return 'Unknown';
        }
    }

    public static function getOneSignalUserId(int $userId): string{
        if (env('APP_ENV') === 'production'){
            return (string) 'user-id-'.$userId;
        }else{
            return (string) 'dev-user-id-'.$userId;
        }
    }
}