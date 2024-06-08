<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('check:environment', function () {
    $appEnvirontment = env('APP_ENV');
    $this->info('App environment: ' . $appEnvirontment);
})->purpose('Check the current environment');


Artisan::command('backup:run-remote-trash-clear', function () {
    $reflection = new ReflectionProperty(Storage::disk('google')->getDriver(), 'adapter');
    $reflection->setAccessible(true);
    $adapter = $reflection->getValue(Storage::disk('google')->getDriver());
    $reflectionService = new ReflectionProperty($adapter, 'service');
    $reflectionService->setAccessible(true);
    $service = $reflectionService->getValue($adapter);

    $trashFileList = $service->files->listFiles([
        'q' => 'trashed=true',
        'fields' => 'files(id, name)'
    ]);

    $filesToBeDeleted = [];
    foreach ($trashFileList as $file) {
        if (preg_match('/backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}.zip/', $file->name)) {
            $filesToBeDeleted[] = $file;
        }
    }

    if (empty($filesToBeDeleted)) {
        $this->info('No backup files in the trash bin to be deleted');
        return;
    }else{
        $this->info('Found ' . count($filesToBeDeleted) . ' backup files in the trash bin to be deleted');
        $this->table(['Id', 'File Name'], array_map(function ($file) {
            return [$file->id, $file->name];
        }, $filesToBeDeleted));
    }

    foreach ($filesToBeDeleted as $file) {
        $this->info('Deleting file ' . $file->name);
        $service->files->delete($file->id);
        $this->info('File ' . $file->name . ' has been deleted');
    }
})->purpose('Clear the backup trash bin of the remote storage');
