<?php

use App\Support\Assistants\WorkersAssistant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Support\Generators\Records\Attendances\RecordAttendancesByJobsExpenses;
use Illuminate\Support\Facades\Storage;
use App\Models\Worker;
use App\Models\WorkerPayment;

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


Artisan::command('run:loadworkers', function () {
    Worker::all()->each(function ($worker) {
        $worker->delete();
    });


    foreach (WorkersAssistant::getListWorkersWithPayments() as $worker)
    {
        $workerClass = Worker::create([
            'dni' => $worker['dni'],
            'name' => $worker['name'],
            'team' => $worker['team'],
            'country' => 'PE',
            'supervisor' => $worker['supervisor'],
            'role' => $worker['function'],
            'is_active' => $worker['is_active'],
        ]);

        foreach ($worker['payments'] as $payment) {
            if ($payment['amount_data']['original']['amount'] <= 0) continue;
            WorkerPayment::create([
                'worker_id' => $workerClass->id,
                'month' => $payment['month'],
                'year' => $payment['year'],
                'amount' => $payment['amount_data']['original']['amount'],
                'currency' => $payment['amount_data']['original']['money_type'],
            ]);
        }
    }
})->purpose('Convert all the workers from the old system to the new one');
