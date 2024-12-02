<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryProductItem;

class FixOrfanateProductItems extends Command
{
    protected $signature = 'inventory:fix-orfanate-items';

    protected $description = 'Fix orfanate product items in the database';

    public function handle()
    {
        $orfanateItems = $this->getOrfanateItems();

        if ($orfanateItems->isEmpty()) {
            $this->info('❤️ No orfanate items found.');
            return;
        }
        $this->info('Found ' . $orfanateItems->count() . ' orfanate items.');

        /* $orfanateItems->each(function ($item) {
            $this->warn('Deleting orfanate item with id: "' . $item->id . '"');
            InventoryProductItem::find($item->id)->delete();
        }); */
    }

    private function getOrfanateItems()
    {
        //Grab all inventory_product_item that the column inventory_product_id is not null but the product do not exists on inventory_products, using DB facade:
        $items = DB::table('inventory_product_items')
            ->leftJoin('inventory_products', 'inventory_product_items.inventory_product_id', '=', 'inventory_products.id')
            ->whereNull('inventory_products.id')
            ->select('inventory_product_items.id')
            ->get();
        return $items;
    }

}
