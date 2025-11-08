<?php

namespace App\Console\Commands;

use App\Support\Assistants\ApplicationWebAssistant;
use App\Support\Assistants\ApplicationNativeAssistant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Exception;

class LoadBundleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:load-bundle
                            {--type= : Bundle type (Web, Native)}
                            {--filepath= : Path to the bundle file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Load a bundle file for Web or Native applications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $type = $this->option('type');
        $filePath = $this->option('filepath');

        // Validate required options
        if (!$type) {
            $this->error('Type option is required. Use --type=Web|Native');
            return 1;
        }

        if (!$filePath) {
            $this->error('Filepath option is required. Use --filepath=/path/to/file.zip');
            return 1;
        }

        // Validate type
        $validTypes = ['Web', 'Native'];
        if (!in_array($type, $validTypes)) {
            $this->error('Invalid type. Must be one of: ' . implode(', ', $validTypes));
            return 1;
        }

        // Validate file exists
        if (!File::exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        // Validate file extension
        if (!str_ends_with($filePath, '.zip')) {
            $this->error('File must be a .zip file');
            return 1;
        }

        // Validate file name format (same validation as in controllers)
        $fileName = basename($filePath);
        if (!$this->validateFileName($fileName)) {
            $this->error('Invalid file name format. Expected format: name_(version(mX)).zip');
            return 1;
        }

        try {
            $this->info("Loading {$type} bundle from: {$filePath}");

            switch ($type) {
                case 'Web':
                    $this->loadWebBundle($filePath, $fileName);
                    break;
                case 'Native':
                    $this->loadNativeBundle($filePath, $fileName);
                    break;
            }

            $this->info("Bundle loaded successfully!");
            return 0;

        } catch (Exception $e) {
            $this->error("Error loading bundle: " . $e->getMessage());
            if ($this->option('verbose')) {
                $this->error("Stack trace: " . $e->getTraceAsString());
            }
            return 1;
        }
    }

    /**
     * Validate file name format
     */
    private function validateFileName(string $fileName): bool
    {
        return str_contains($fileName, '(m') &&
               str_contains($fileName, '_') &&
               str_contains($fileName, ').zip');
    }

    /**
     * Load Web bundle
     */
    private function loadWebBundle(string $filePath, string $fileName): void
    {
        $this->info('Creating Web bundle file...');
        $bundleFile = ApplicationWebAssistant::createBundleFile($filePath, $fileName);

        $this->info('Extracting and applying Web bundle...');
        ApplicationWebAssistant::extractAndApplyBundle($bundleFile);

        $this->displayBundleInfo($bundleFile);
    }

    /**
     * Load Native bundle
     */
    private function loadNativeBundle(string $filePath, string $fileName): void
    {
        $this->info('Setting Native bundle file...');
        $bundleFile = ApplicationNativeAssistant::setBundleFile($filePath, $fileName);

        $this->displayBundleInfo($bundleFile);
    }

    /**
     * Display bundle information
     */
    private function displayBundleInfo($bundleFile): void
    {
        $this->info('Bundle Information:');
        $this->line("  Version: {$bundleFile->version}");
        $this->line("  Minimal Version: {$bundleFile->minimalVersion}");
        $this->line("  Size: " . $this->formatBytes($bundleFile->size));
        $this->line("  URL: {$bundleFile->url}");
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
