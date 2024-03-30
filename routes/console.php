<?php

use App\Models\Invoice;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use mikehaertl\shellcommand\Command;
use Illuminate\Support\Facades\Storage;
use App\Support\Exchange\Exchanger;
use App\Support\Exchange\MoneyType;
use Illuminate\Support\Facades\DB;


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


Artisan::command('sqlite:check', function(){
    $response = DB::statement('select (select "key" from "pulse_entries" as "keys" where "keys"."key_hash" = "aggregated"."key_hash" limit 1) as "key", "cache_hit", "cache_miss" from (select "key_hash", sum("cache_hit") as "cache_hit", sum("cache_miss") as "cache_miss" from (select * from (select "key_hash", count(case when ("type" = cache_hit) then 1 else null end) as "cache_hit", count(case when ("type" = cache_miss) then 1 else null end) as "cache_miss" from "pulse_entries" where "type" in (cache_hit, cache_miss) and "timestamp" >= 1711483641 and "timestamp" <= 1711483679 group by "key_hash") union all select * from (select "key_hash", sum(case when ("type" = cache_hit) then "value" else null end) as "cache_hit", sum(case when ("type" = cache_miss) then "value" else null end) as "cache_miss" from "pulse_aggregates" where "period" = 60 and "type" in (cache_hit, cache_miss) and "aggregate" = count and "bucket" >= 1711483680 group by "key_hash")) as "results" group by "key_hash" order by "cache_hit" desc limit 101) as "aggregated"')->get();
    $this->info('Response: ' . $response);
});

Artisan::command('sqlite:version', function(){
    $sqliteVersion = json_encode(SQLite3::version());
    $this->info('Version: ' . $sqliteVersion);
});

Artisan::command('check:invoices', function(){
    Invoice::all()->each(function($invoice){
        $invoice->imageSize();
    });
});