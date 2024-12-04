<?php

namespace App\Console\Commands;

use App\Models\InventoryProduct;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcome;
use App\Models\InventoryWarehouseOutcomeRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryWarehouseProductItemLoan;

class RestartInventory extends Command
{
    protected $signature = 'inventory:restart';

    protected $description = 'Description';

    public function handle()
    {
        InventoryWarehouseIncome::query()->delete();
        InventoryWarehouseOutcome::query()->delete();
        InventoryWarehouseOutcomeRequest::query()->delete();
        InventoryWarehouseProductItemLoan::query()->delete();
        InventoryProductItem::query()->delete();
        InventoryProductItemUncountable::query()->delete();
    }

}
