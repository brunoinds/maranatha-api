<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use mikehaertl\shellcommand\Command;
use Illuminate\Support\Facades\Storage;
use App\Support\Exchange\Exchanger;
use App\Support\Exchange\MoneyType;


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


Artisan::command('clear:db', function(){
    $commandLine = 'rm -rf database/database.sqlite && touch database/database.sqlite && php artisan migrate --force';
    $command = new Command($commandLine);
    $response = $command->execute();
    if (!$response){
        $this->error('Failed to clear database');
        return;
    }
    $this->info('Database cleared successfully');
})->purpose('Clear the database and create a new one');

Artisan::command('check:environment', function () {
    $appEnvirontment = env('APP_ENV');
    $this->info('App environment: ' . $appEnvirontment);
})->purpose('Display an inspiring quote');
