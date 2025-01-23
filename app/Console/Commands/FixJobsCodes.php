<?php

namespace App\Console\Commands;

use App\Models\InventoryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryWarehouseProductItemLoan;
use App\Models\Job;
use App\Models\Invoice;
use App\Models\Attendance;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Models\InventoryWarehouseOutcome;

class FixJobsCodes extends Command
{
    protected $signature = 'jobs:fix-codes';

    protected $description = 'Description';

    public function handle()
    {

        Job::each(function ($job) {
            $previousCode = $job->code;

            if (is_null($job->country) || strlen($job->country) === 0) {
                $job->country = 'PE';
                $job->save();
            }

            $newCode = Job::sanitizeCode($previousCode) . '-' . $job->country . '[' . $job->zone . ']';

            if ($previousCode !== $newCode) {
                $job->update(['code' => $newCode]);
            }


            Invoice::where('job_code', $previousCode)->update(['job_code' => $newCode]);
            Attendance::where('job_code', $previousCode)->update(['job_code' => $newCode]);
            InventoryWarehouseIncome::where('job_code', $previousCode)->update(['job_code' => $newCode]);
            InventoryWarehouseOutcomeRequest::where('job_code', $previousCode)->update(['job_code' => $newCode]);
            InventoryWarehouseOutcome::where('job_code', $previousCode)->update(['job_code' => $newCode]);
            InventoryWarehouseProductItemLoan::where('job_code', $previousCode)->update(['job_code' => $newCode]);
        });
    }

}
