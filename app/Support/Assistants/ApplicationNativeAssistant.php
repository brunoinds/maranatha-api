<?php

namespace App\Support\Assistants;

class BundleFile{
    public string $version;
    public string $size;
    public string $lastModified;
    public string $url;
    public string $path;


    public function __construct($version, $size, $lastModified, $url, $path){
        $this->version = $version;
        $this->size = $size;
        $this->lastModified = $lastModified;
        $this->url = $url;
        $this->path = $path;
    }
}
class ApplicationNativeAssistant{
    public static function bundleFile() :BundleFile|null{
        $bundleFolder = resource_path('ionic/.bundle');

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
                $fileInfo['size'] = filesize($filePath);
                $fileInfo['lastModified'] = filemtime($filePath);
                $fileInfo['lastModifiedHuman'] = date('Y-m-d H:i:s', filemtime($filePath));

                $fileInfo['version'] = explode('_', $fileInfo['filename'])[1];
                $fileInfo['url'] = url('/api/app/native/bundles/' . $fileInfo['version']);

                $bundleFile = new BundleFile(
                    version: $fileInfo['version'],
                    size: $fileInfo['size'],
                    lastModified: $fileInfo['lastModified'],
                    url: $fileInfo['url'],
                    path: $filePath
                );
            }
        }
        return $bundleFile;
    }
}
