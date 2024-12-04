<?php

namespace App\Console\Commands;

use App\Models\InventoryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryWarehouseProductItemLoan;

class FixMovements extends Command
{
    protected $signature = 'inventory:fix-movements';

    protected $description = 'Description';

    public function handle()
    {
        InventoryWarehouseProductItemLoan::each(function ($productLoan) {
            $productLoan->movements = collect($productLoan->movements)->map(function ($movement) {
                $movement['to_user_id'] = $movement['user_id'];
                return $movement;
            })->toArray();
            $productLoan->save();
        });
    }

}
