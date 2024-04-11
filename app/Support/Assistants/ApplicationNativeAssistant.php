<?php

namespace App\Support\Assistants;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BundleFile{
    public string $version;
    public string $minimalVersion;
    public int $size;
    public string $lastModified;
    public string $url;
    public string $path;


    public function __construct($version, $minimalVersion, $size, $lastModified, $url, $path){
        $this->version = $version;
        $this->minimalVersion = $minimalVersion;
        $this->size = $size;
        $this->lastModified = $lastModified;
        $this->url = $url;
        $this->path = $path;
    }
}
class ApplicationNativeAssistant{
    public static function bundleFile() :BundleFile|null{
        $bundleFolder = Storage::disk('public')->path('bundles');

        if (!file_exists($bundleFolder)) {
            return null;
        }

        $bundleFile = null;
        $files = scandir($bundleFolder);
        foreach ($files as $file) {
            if ($file != '.'&& $file != '..') {
                if (!str_contains($file, '.zip')){
                    continue;
                }

                $filePath = $bundleFolder . '/' . $file;
                $fileInfo = pathinfo($filePath);
                $fileInfo['size'] = (int) filesize($filePath);
                $fileInfo['lastModified'] = filemtime($filePath);
                $fileInfo['lastModifiedHuman'] = date('Y-m-d H:i:s', filemtime($filePath));


                $versionArea = explode('_', $fileInfo['filename'])[1];

                preg_match('/\(([^)]+)\)/', $versionArea, $matches);
                $minimalVersion = $matches[1];
                $version = str_replace('(' . $minimalVersion . ')', '', $versionArea);


                $fileInfo['version'] = $version;
                $fileInfo['minimalVersion'] = str_replace('m', '', $minimalVersion);;
                $fileInfo['url'] = url('/api/app/native/bundles/' . $fileInfo['version']);

                $bundleFile = new BundleFile(
                    version: $fileInfo['version'],
                    minimalVersion: $fileInfo['minimalVersion'],
                    size: $fileInfo['size'],
                    lastModified: $fileInfo['lastModified'],
                    url: $fileInfo['url'],
                    path: $filePath
                );
            }
        }
        return $bundleFile;
    }

    public static function setBundleFile(string $filePath, string $fileName)
    {
        if (Storage::disk('public')->exists('bundles')){
            File::deleteDirectory(Storage::disk('public')->path('bundles'), true);
        }

        $path = 'bundles/' . $fileName;
        $wasSuccessfull = Storage::disk('public')->put($path, file_get_contents($filePath));

        if (!$wasSuccessfull) {
            throw new Exception('Bundle upload failed');
        }

        return self::bundleFile();
    }
}
