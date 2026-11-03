<?php

namespace App\Support\Images;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PngImage{
    // Recebe a imagem como veio do app (base64 ou data URI) e devolve os bytes em PNG
    public static function encode(string $input): string{
        $manager = new ImageManager(new Driver());
        return $manager->decode($input)->encodeUsingFileExtension('png')->toString();
    }
}
