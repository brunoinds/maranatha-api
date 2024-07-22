<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\TemporaryDirectory\TemporaryDirectory;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore';

    protected $description = 'Restore a backup file from remote storage';

    public function handle()
    {
        $this->info('🔍 Looking for backup files in the remote storage...');

        $backupFiles = $this->getBackupFiles();

        if (empty($backupFiles)) {
            $this->info('🚨 No backup files found');
            return;
        }

        $this->displayBackupFiles($backupFiles);

        $chosenBackup = $this->chooseBackupFile($backupFiles);

        if (!$this->confirmRestoration()) {
            return;
        }

        $this->downloadAndRestoreBackup($chosenBackup);
    }

    private function getBackupFiles()
    {
        $files = Storage::disk('google')->files('Maranatha');
        $backupFiles = array_filter($files, function ($file) {
            return preg_match('/backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}.zip/', $file);
        });

        return array_reverse(array_map(function ($file) {
            $fileName = explode('-', $file);
            return [
                'date' => $fileName[1] . '-' . $fileName[2] . '-' . $fileName[3],
                'time' => $fileName[4] . ':' . $fileName[5] . ':' . str_replace('.zip', '', $fileName[6]),
                'name' => $file
            ];
        }, $backupFiles));
    }

    private function displayBackupFiles($backupFiles)
    {
        $this->info('📋 List of backup files found:');
        $this->table(['Date', 'Time', 'Name'], array_map(function ($file) {
            return [$file['date'], $file['time'], $file['name']];
        }, $backupFiles));
    }

    private function chooseBackupFile($backupFiles)
    {
        $choice = $this->choice('✅ Choose a backup file to recover', array_map(function ($file) {
            return $file['date'] . ' ' . $file['time'];
        }, $backupFiles));

        return array_values(array_filter($backupFiles, function ($file) use ($choice) {
            return $file['date'] . ' ' . $file['time'] == $choice;
        }))[0];
    }

    private function confirmRestoration()
    {
        if (!$this->confirm('🚨 Are you sure you want to restore the backup file? This will delete the current database and filesystem.')) {
            $this->info('🚫 Operation cancelled');
            return false;
        }

        if (env('APP_ENV') == 'production') {
            if (!$this->confirm('🚨🚨🚨 You are in PRODUCTION environment. Are you sure you want to continue? 🚨🚨🚨')) {
                $this->info('🚫 Operation cancelled');
                return false;
            }
        }

        return true;
    }

    private function downloadAndRestoreBackup($backupFile)
    {
        $fileSize = Storage::disk('google')->size($backupFile['name']);
        $this->info('📥 Downloading backup file ' . $backupFile['name'] . '. 📏 File size: ' . round($fileSize / 1024 / 1024, 2) . ' MB...');
        $this->line('');

        $temporaryDirectory = (new TemporaryDirectory())->create();
        $tempPath = $temporaryDirectory->path('maranatha-backup-recover.zip');

        /** @var \League\Flysystem\Filesystem $fs */
        $fs = Storage::disk('google')->getDriver();
        $stream = $fs->readStream($backupFile['name']);

        $chunkSize = 1024;
        $steps = ceil($fileSize / $chunkSize);

        $handle = fopen($tempPath, 'w');
        $progress = $this->output->createProgressBar($steps);
        $progress->setFormat('📦 Downloading backup file [%bar%] %percent:3s%%');
        $progress->start();

        while (!feof($stream)) {
            fwrite($handle, fread($stream, $chunkSize));
            $progress->advance();
        }

        fclose($handle);
        fclose($stream);
        $progress->finish();
        $this->line('');

        $this->info('✨ Restoring backup file to the local storage');
        $this->call('backup:restore-from-file', [
            'file' => $tempPath
        ]);

        $temporaryDirectory->delete();
    }
}
