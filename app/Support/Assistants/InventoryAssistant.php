<?php


namespace App\Support\Assistants;

use App\Models\InventoryWarehouse;
use App\Models\InventoryProductItem;
use App\Helpers\Enums\MoneyType;
use App\Models\InventoryProduct;
use App\Helpers\Toolbox;
use Illuminate\Support\Benchmark;




class InventoryAssistant{

    public static function getWarehouseStock(InventoryWarehouse $warehouse): \Illuminate\Support\Collection
    {
        $productsIds = InventoryProduct::all(['id'])->pluck('id')->toArray();

        $stock = [];
        foreach ($productsIds as $productId){
            $averageBuyPrices = MoneyType::toAssociativeArray(null);
            $averageSellPrices = MoneyType::toAssociativeArray(null);


            $productItemsCountQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);

            if ($productItemsCountQuery->count() === 0){
                $stock[] = [
                    'in_stock_count' => 0,
                    'sold_count' => 0,
                    'average_buy_price' => [],
                    'average_sell_price' => [],
                ];
                continue;
            }





            $productItemsQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsBuyAmountQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsSellAmountQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsBuyCountQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsSellCountQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);

            $productItemsInStockCountQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsSoldCountQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsAllCountQuery = InventoryProductItem::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);


            foreach ($averageBuyPrices as $moneyType => $value){
                $totalBuyAmount = $productItemsBuyAmountQuery->where('buy_currency', $moneyType)->sum('buy_amount');
                $totalSellAmount = $productItemsSellAmountQuery->where('status', 'Sold')->where('sell_currency', $moneyType)->sum('sell_amount');
                $totalBuyCount = $productItemsBuyCountQuery->where('buy_currency', $moneyType)->count();
                $totalSellCount = $productItemsSellCountQuery->where('status', 'Sold')->where('sell_currency', $moneyType)->count();


                $averageBuyPrice = Toolbox::toFixed($totalBuyAmount / ($totalBuyCount == 0 ? 1 : $totalBuyCount));
                $averageSellPrice = Toolbox::toFixed($totalSellAmount / ($totalSellCount == 0 ? 1 : $totalSellCount));

                if ($totalBuyCount !== 0){
                    $averageBuyPrices[$moneyType] = $averageBuyPrice;
                }
                if ($totalSellCount !== 0){
                    $averageSellPrices[$moneyType] = $averageSellPrice;
                }
            }

            //Remove the keys that are empty (null):
            $averageBuyPrices = array_filter($averageBuyPrices);
            $averageSellPrices = array_filter($averageSellPrices);

            $stock[] = [
                'in_stock_count' => $productItemsInStockCountQuery->where('status', 'InStock')->count(),
                'sold_count' => $productItemsSoldCountQuery->where('status', 'Sold')->count(),
                'all_count' => $productItemsAllCountQuery->count(),
                'average_buy_price' => $averageBuyPrices,
                'average_sell_price' => $averageSellPrices
            ];
        }


        $products = [];
        foreach ($stock as $index => $productInfo){
            $product = InventoryProduct::find($productsIds[$index]);
            $product->stock = $productInfo;
            $products[] = $product;
        }

        return collect($products);
    }

}
