<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Support\Generators\Records\Attendances\RecordAttendancesByJobsExpenses;


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


Artisan::command('run:lowercase', function () {
    $users = App\Models\User::all();
    $users->each(function($user){
        $user->username = strtolower($user->username);
        $user->save();
    });
    $this->info('All users usernames set to lowercase');
})->purpose('Set all users usernames to lowercase');
