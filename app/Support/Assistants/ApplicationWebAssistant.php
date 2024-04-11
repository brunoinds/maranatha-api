<?php

namespace App\Support\Assistants;

use Exception;
use Illuminate\Support\Facades\File;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use mikehaertl\shellcommand\Command;
use ZipArchive;

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


    public static function loadFromPath(string $filePath): self{
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

        return new BundleFile(
            version: $fileInfo['version'],
            minimalVersion: $fileInfo['minimalVersion'],
            size: $fileInfo['size'],
            lastModified: $fileInfo['lastModified'],
            url: $fileInfo['url'],
            path: $filePath
        );
    }
}

class ApplicationWebAssistant{
    private static function storePreviousBundleAsBackup(): string|null
    {
        if (count(scandir(resource_path('ionic'))) == 2) {
            return null;
        }
        //Make a .zip file of all file inside resource_path('ionic') and store it in TemporaryDirectory:
        $temporaryDirectory = (new TemporaryDirectory())->create();
        $backupPath = $temporaryDirectory->path('backup.zip');
        //Do not use ZipArchive, use shell command, using the Laravel Process library:
        $commandLine = 'cd ' . resource_path('ionic') . ' && zip -r ' . $backupPath . ' .';
        $command = new Command($commandLine);
        if (!$command->execute()) {
            throw new Exception('Failed to create backup');
        }

        return $backupPath;
    }
    private static function restorePreviousBundleAsBackup($backupPath): void
    {
        if (File::exists(resource_path('ionic'))){
            File::deleteDirectory(resource_path('ionic'), true);
        }else{
            File::makeDirectory(resource_path('ionic'));
        }


        $zip = new ZipArchive;
        $res = $zip->open($backupPath);
        if ($res === TRUE) {
            $zip->extractTo(resource_path('ionic'));
            $zip->close();
        } else {
            throw new Exception('Fatal error, failed to restore backup');
        }
    }
    public static function extractAndApplyBundle(BundleFile $bundleFile):void
    {
        $bundleFilePath = $bundleFile->path;
        if (!File::exists($bundleFilePath)) {
            throw new Exception('Bundle file not found');
        }

        $backupPath = null;
        if (File::exists(resource_path('ionic'))){
            $backupPath = self::storePreviousBundleAsBackup();
            File::deleteDirectory(resource_path('ionic'), true);
        }else{
            File::makeDirectory(resource_path('ionic'));
        }



        $zip = new ZipArchive;
        $res = $zip->open($bundleFilePath);
        if ($res === TRUE) {
            $zip->extractTo(resource_path('ionic'));
            $zip->close();
        } else {
            if ($backupPath !== null){
                self::restorePreviousBundleAsBackup($backupPath);
                throw new Exception('Failed to extract bundle, but we restored by backup successfully');
            }
        }
    }


    public static function createBundleFile(string $filePath, string $fileName): BundleFile
    {
        $content = file_get_contents($filePath);
        $temporaryDirectory = (new TemporaryDirectory())->create();
        $tempPath = $temporaryDirectory->path($fileName);
        file_put_contents($tempPath, $content);
        return BundleFile::loadFromPath($tempPath);
    }
}
