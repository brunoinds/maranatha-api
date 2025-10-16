<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use DateTime;
use App\Support\Exchange\Currencies\PYG;
use Carbon\Carbon;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcome;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $startDate = Carbon::parse('2025-09-01', 'America/Lima')->startOfDay()->setTimezone('America/Lima')->toIso8601String();

        $this->info('Searching for outcomes from >=' . $startDate);

        InventoryWarehouseOutcome::where('date', '>=', $startDate)->select('date')->get()->each(function($outcome){
            $this->info($outcome->date);
        });
    }
}
