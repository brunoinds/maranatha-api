<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ReflectionProperty;

class BackupClearRemoteTrashBin extends Command
{
    protected $signature = 'backup:clear-remote-trash-bin';

    protected $description = 'Clear the backup trash bin of the remote storage';

    public function handle()
    {
        $service = $this->getGoogleDriveService();

        $trashFileList = $service->files->listFiles([
            'q' => 'trashed=true',
            'fields' => 'files(id, name)'
        ]);

        $filesToBeDeleted = $this->getBackupFilesFromTrash($trashFileList);

        if (empty($filesToBeDeleted)) {
            $this->info('No backup files in the trash bin to be deleted');
            return;
        }

        $this->displayFilesToBeDeleted($filesToBeDeleted);
        $this->deleteFiles($service, $filesToBeDeleted);
    }

    private function getGoogleDriveService()
    {
        $reflection = new ReflectionProperty(Storage::disk('google')->getDriver(), 'adapter');
        $reflection->setAccessible(true);
        $adapter = $reflection->getValue(Storage::disk('google')->getDriver());
        $reflectionService = new ReflectionProperty($adapter, 'service');
        $reflectionService->setAccessible(true);
        return $reflectionService->getValue($adapter);
    }

    private function getBackupFilesFromTrash($trashFileList)
    {
        return array_filter(iterator_to_array($trashFileList), function ($file) {
            return preg_match('/backup-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}.zip/', $file->name);
        });
    }

    private function displayFilesToBeDeleted($filesToBeDeleted)
    {
        $this->info('Found ' . count($filesToBeDeleted) . ' backup files in the trash bin to be deleted');
        $this->table(['Id', 'File Name'], array_map(function ($file) {
            return [$file->id, $file->name];
        }, $filesToBeDeleted));
    }

    private function deleteFiles($service, $filesToBeDeleted)
    {
        foreach ($filesToBeDeleted as $file) {
            $this->info('Deleting file ' . $file->name);
            $service->files->delete($file->id);
            $this->info('File ' . $file->name . ' has been deleted');
        }
    }
}
