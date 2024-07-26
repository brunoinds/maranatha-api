<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

class BackupRestoreFromFile extends Command
{
    protected $signature = 'backup:restore-from-file {file}';

    protected $description = 'Restore the backup image';

    public function handle()
    {

        $file = $this->argument('file');

        if(!file_exists($file)){
            $this->error('File ' . $file . ' does not exist.');
            return;
        }

        //Check if the file is a zip file:
        if(pathinfo($file, PATHINFO_EXTENSION) != 'zip'){
            $this->error('File ' . $file . ' is not a zip file.');
            return;
        }


        //Extract the backup zip file:
        $this->info('📤 Extracting backup image...');

        $zip = new ZipArchive();
        $zip->open($file);
        $temporaryDirectory = (new TemporaryDirectory())->create();
        $tempPath = $temporaryDirectory->path('maranatha-backup-recover');
        $zip->extractTo($tempPath);
        $zip->close();


        //Restore the filesystem:
        $this->info('🗂️ Restoring the filesystem...');

        $this->info('   🗑️ Deleting current filesytem...');
        $items = Storage::disk('public')->allFiles();
        foreach ($items as $item){
            $this->info('       - Deleting file ' . $item);
            Storage::disk('public')->delete($item);
        }

        $items = Storage::disk('public')->allDirectories();
        foreach ($items as $item){
            $this->info('       - Deleting directory ' . $item);
            Storage::disk('public')->deleteDirectory($item);
        }


        $this->info('   ✨ Restoring new filesytem...');

        $adapter = new LocalFilesystemAdapter($tempPath);
        $filesystem = new Filesystem($adapter);

        $items = $filesystem->listContents('/storage/app/public', true);
        foreach ($items as $item){
            $itemPathCorrected = str_replace('storage/app/public', '', $item['path']);
            if($item['type'] === 'file'){
                $this->info('       - Restoring file ' . $item['path']);
                Storage::disk('public')->put($itemPathCorrected, $filesystem->read($item['path']));
            }else{
                $this->info('       - Creating directory ' . $item['path']);
                Storage::disk('public')->makeDirectory($itemPathCorrected);
            }
        }

        //Restore the SQLite database:
        $this->call('db:restore', [
            'file' => $tempPath . '/db-dumps/sqlite-sqlite-database.sql'
        ]);

        $this->info('✅ Backup file has been restored successfully.');
    }
}
