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

        $matrix = [
            [
                'product_id' => 62,
                'replace_to' => 66
            ],
            [
                'product_id' => 525,
                'replace_to' => 45
            ],
            [
                'product_id' => 150,
                'replace_to' => 343
            ],
            [
                'product_id' => 149,
                'replace_to' => 344
            ],
            [
                'product_id' => 154,
                'replace_to' => 605
            ],
            [
                'product_id' => 794,
                'replace_to' => 226
            ],
            [
                'product_id' => 126,
                'replace_to' => 363
            ]
        ];

        foreach ($matrix as $item) {
            //Check if product_id and replace_to exists:
            $product = InventoryProduct::where('id', $item['product_id'])->first();
            $replacer = InventoryProduct::where('id', $item['replace_to'])->first();

            if (!$product) {
                $this->error('Product with id ' . $item['product_id'] . ' does not exist');
                continue;
            }

            if (!$replacer) {
                $this->error('Product with id ' . $item['replace_to'] . ' does not exist');
                continue;
            }

            InventoryProductItem::where('inventory_product_id', $item['product_id'])
                ->update([
                    'inventory_product_id' => $item['replace_to']
                ]);
        }
    }
}
