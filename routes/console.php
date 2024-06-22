<?php

use App\Support\EventLoop\WalletEventLoop;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use function Laravel\Prompts\progress;
use App\Support\EventLoop\Notifications\Notifications;


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

Artisan::command('backup:restore', function(){
    $this->info('🔍 Looking for backup files in the remote storage...');

    $backupFiles = Storage::disk('google')->files('Maranatha');
    $backupFiles = array_filter($backupFiles, function($file){
        return preg_match('/backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}.zip/', $file);
    });


    $backupFiles = array_map(function($file){
        $fileName = explode('-', $file);
        return [
            'date' => $fileName[1] . '-' . $fileName[2] . '-' . $fileName[3],
            'time' => $fileName[4] . ':' . $fileName[5] . ':' . str_replace('.zip', '', $fileName[6]),
            'name' => $file
        ];
    }, $backupFiles);

    $backupFiles = array_reverse($backupFiles);

    if(empty($backupFiles)){
        $this->info('🚨 No backup files found');
        return;
    }

    $this->info('📋 List of backup files found:');
    $this->table(['Date', 'Time', 'Name'], array_map(function($file){
        return [$file['date'], $file['time'], $file['name']];
    }, $backupFiles));

    $backupFile = $this->choice('✅ Choose a backup file to recover', array_map(function($file){
        return $file['date'] . ' ' . $file['time'];
    }, $backupFiles));

    $backupFile = array_values(array_filter($backupFiles, function($file) use ($backupFile){
        return $file['date'] . ' ' . $file['time'] == $backupFile;
    }))[0];


    //Ask if the user wants to restore the backup file to the local storage and this will delete the current database and filesystem:
    if(!$this->confirm('🚨 Are you sure you want to restore the backup file to the local storage? This will delete the current database and filesystem.')){
        $this->info('🚫 Operation cancelled');
        return;
    }

    //Ask if is in production environment:
    if (env('APP_ENV') == 'production') {
        if (!$this->confirm('🚨🚨🚨 You are in PRODUCTION environment. Are you sure you want to continue? 🚨🚨🚨')) {
            $this->info('🚫 Operation cancelled');
            return;
        }
    }

    $fileSize = Storage::disk('google')->size($backupFile['name']);
    $this->info('📥 Downloading backup file ' . $backupFile['name'] . '. 📏 File size: ' . round($fileSize / 1024 / 1024, 2) . ' MB...');
    $this->writeLine('');

    $temporaryDirectory = (new TemporaryDirectory())->create();
    $tempPath = $temporaryDirectory->path('maranatha-backup-recover.zip');

    /** @var \League\Flysystem\Filesystem $fs */
    $fs = Storage::disk('google')->getDriver();
    $stream = $fs->readStream($backupFile['name']);


    $chunkSize = 1024;
    $steps = ceil($fileSize / $chunkSize);

    $handle = fopen($tempPath, 'w');
    $progress = progress(label: '📦 Downloading backup file', steps: $steps);
    $progress->start();
    $sizeRead = 0;
    while (!feof($stream)) {
        fwrite($handle, fread($stream, $chunkSize));
        $progress->advance();
        $sizeRead += $chunkSize;
    }
    fclose($handle);
    fclose($stream);
    $progress->finish();


    //Restore the backup file to the local storage:
    $this->info('✨ Restoring backup file to the local storage');
    $this->call('backup:restore-from-file', [
        'file' => $tempPath
    ]);
});


Artisan::command('remove:model {model}', function ($model) {
    $files = [
        'app/Models/' . $model . '.php',
        'app/Http/Requests/Store' . $model . 'Request.php',
        'app/Http/Requests/Update' . $model . 'Request.php',
        'app/Http/Controllers/' . $model . 'Controller.php',
        'app/Policies/' . $model . 'Policy.php',
        'database/factories/' . $model . 'Factory.php',
        'database/seeders/' . $model . 'Seeder.php',
    ];

    collect($files)->each(function($file){
        if (file_exists($file)) {
            unlink($file);
            $this->info('File ' . $file . ' removed ✅');
        }else{
            $this->error('File ' . $file . ' not removed beacuse it does not exist ❌');
        }
    });
})->purpose('Remove Model, Controller, Policy, Factory, Migration, Seeder');
