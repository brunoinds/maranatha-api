<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryProductItem;
use App\Models\InventoryWarehouseOutcomeRequest;

class FixOutcomeRequestProductsInteger extends Command
{
    protected $signature = 'inventory:fix-outcome-request-products-integer';

    protected $description = 'Fix outcome request products integer';

    public function handle()
    {
        InventoryWarehouseOutcomeRequest::each(function ($outcomeRequest) {
            $outcomeRequest->requested_products = array_map(function($item){
                return [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ];
            }, $outcomeRequest->requested_products);

            $outcomeRequest->received_products = array_map(function($item){
                return [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ];
            }, $outcomeRequest->received_products);

            $outcomeRequest->save();
        });
    }
}
