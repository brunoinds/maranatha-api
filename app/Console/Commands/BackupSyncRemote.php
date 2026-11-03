<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupSyncRemote extends Command
{
    protected $signature = 'backup:sync-remote';

    protected $description = 'Synchronize local and remote backup storage';

    private $localFiles;
    private $remoteFiles;
    private $filesOnlyInLocal;
    private $filesOnlyInRemote;

    public function handle()
    {
        $this->loadFiles();
        $this->getLocalAndRemoteFilesDifference();
        $this->displayLocalAndRemoteFilesState();

        if (!empty($this->filesOnlyInLocal)) {
            $this->uploadFilesToRemoteStorage();
        }

        if (!empty($this->filesOnlyInRemote)) {
            $this->deleteFilesFromRemoteStorage();
        }
    }

    private function loadFiles()
    {
        // Pasta vazia listaria a raiz do Drive, e o sync apagaria tudo o que não é backup
        $folder = config('backup.backup.name');
        if (empty($folder)) {
            throw new \RuntimeException('backup.backup.name is empty: refusing to sync the Drive root');
        }

        $this->localFiles = Storage::disk('backup')->files($folder);
        $this->remoteFiles = Storage::disk('google')->files($folder);
    }

    private function getLocalAndRemoteFilesDifference()
    {
        $this->filesOnlyInLocal = array_diff($this->localFiles, $this->remoteFiles);
        $this->filesOnlyInRemote = array_diff($this->remoteFiles, $this->localFiles);
    }

    private function displayLocalAndRemoteFilesState()
    {
        if (empty($this->filesOnlyInLocal) && empty($this->filesOnlyInRemote)) {
            $this->info('The local and remote backup storage are synchronized!');
            return;
        }

        if (count($this->filesOnlyInLocal) > 0) {
            $count = count($this->filesOnlyInLocal);
            $this->info("There are $count files to be uploaded to remote storage:");
            $this->table(['File name'], array_map(fn($file) => [$file], $this->filesOnlyInLocal));
        }

        if (count($this->filesOnlyInRemote) > 0) {
            $count = count($this->filesOnlyInRemote);
            $this->info("There are $count files to be deleted from remote storage:");
            $this->table(['File name'], array_map(fn($file) => [$file], $this->filesOnlyInRemote));
        }
    }

    private function uploadFilesToRemoteStorage()
    {
        foreach ($this->filesOnlyInLocal as $file) {
            $this->info("Uploading '$file' to remote storage...");

            $readStream = Storage::disk('backup')->readStream($file);
            if ($readStream) {
                Storage::disk('google')->writeStream($file, $readStream);
                if (is_resource($readStream)) {
                    fclose($readStream);
                }
            }

            $this->info("$file uploaded successfully!");
        }
    }

    private function deleteFilesFromRemoteStorage()
    {
        foreach ($this->filesOnlyInRemote as $file) {
            $this->info("Deleting '$file' from remote storage...");
            Storage::disk('google')->delete($file);
            $this->info("$file removed successfully from remote storage!");
        }
    }
}
