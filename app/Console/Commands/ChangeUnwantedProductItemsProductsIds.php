<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryProductItem;
use App\Models\InventoryProduct;
use App\Models\InventoryWarehouseOutcomeRequest;

class ChangeUnwantedProductItemsProductsIds extends Command
{
    protected $signature = 'inventory:change-unwanted-product-items';

    protected $description = 'Change unwanted product items products ids';

    public function handle()
    {

        InventoryWarehouseOutcomeRequest::where('inventory_warehouse_outcome_id', '=', 6)->update([
            'inventory_warehouse_outcome_id' => null
        ]);
    }
}
