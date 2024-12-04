<?php

namespace App\Console\Commands;

use App\Models\InventoryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryWarehouseProductItemLoan;

class FixLoansJobs extends Command
{
    protected $signature = 'inventory:fix-loans-jobs';

    protected $description = 'Description';

    public function handle()
    {
        InventoryWarehouseProductItemLoan::each(function ($productLoan) {
            $productLoan->job_code = collect($productLoan->movements)->last()['job_code'] ?? null;
            $productLoan->expense_code = collect($productLoan->movements)->last()['expense_code'] ?? null;
            $productLoan->save();
        });
    }

}
