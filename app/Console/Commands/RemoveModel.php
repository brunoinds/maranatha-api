<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class RemoveModel extends Command
{
    protected $signature = 'remove:model {model}';

    protected $description = 'Remove Model, Controller, Policy, Factory, Migration, Seeder';

    public function handle()
    {
        $model = $this->argument('model');

        $files = [
            'app/Models/' . $model . '.php',
            'app/Http/Requests/Store' . $model . 'Request.php',
            'app/Http/Requests/Update' . $model . 'Request.php',
            'app/Http/Controllers/' . $model . 'Controller.php',
            'app/Policies/' . $model . 'Policy.php',
            'database/factories/' . $model . 'Factory.php',
            'database/seeders/' . $model . 'Seeder.php',
        ];

        Collection::make($files)->each(function ($file) {
            if (file_exists($file)) {
                unlink($file);
                $this->info('File ' . $file . ' removed ✅');
            } else {
                $this->error('File ' . $file . ' not removed because it does not exist ❌');
            }
        });
    }
}
