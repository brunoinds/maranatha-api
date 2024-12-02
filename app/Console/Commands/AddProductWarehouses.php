<?php

namespace App\Console\Commands;

use App\Models\InventoryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryProductItem;

class AddProductWarehouses extends Command
{
    protected $signature = 'inventory:add-product-warehouses';

    protected $description = 'Description';

    public function handle()
    {
        InventoryProduct::each(function ($product) {
            $product->update([
                'inventory_warehouses_ids' => [1, 2, 3, 5, 6, 7, 8, 9, 10]
            ]);
        });
    }

}
