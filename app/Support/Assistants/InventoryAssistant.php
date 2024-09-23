<?php


namespace App\Support\Assistants;

use App\Models\InventoryWarehouse;
use App\Helpers\Enums\MoneyType;
use App\Models\InventoryProduct;
use App\Helpers\Toolbox;



class InventoryAssistant{

    public static function getWarehouseStock(InventoryWarehouse $warehouse): \Illuminate\Support\Collection
    {
        $productsInWarehouse = collect($warehouse->products()->get());
        $grouped = $productsInWarehouse->groupBy('inventory_product_id');

        $stock = $grouped->map(function($productItems){
            $productItems = collect($productItems->toArray());
            $averageBuyPrices = MoneyType::toAssociativeArray(null);
            $averageSellPrices = MoneyType::toAssociativeArray(null);

            foreach ($averageBuyPrices as $moneyType => $value){
                $totalBuyAmount = $productItems->where('buy_currency', $moneyType)->sum('buy_amount');
                $totalSellAmount = $productItems->where('status', 'Sold')->where('sell_currency', $moneyType)->sum('sell_amount');
                $totalBuyCount = $productItems->where('buy_currency', $moneyType)->count();
                $totalSellCount = $productItems->where('status', 'Sold')->where('sell_currency', $moneyType)->count();


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

            return [
                'in_stock_count' => $productItems->where('status', 'InStock')->count(),
                'sold_count' => $productItems->where('status', 'Sold')->count(),
                'average_buy_price' => $averageBuyPrices,
                'average_sell_price' => $averageSellPrices,
                'items' => $productItems->toArray()
            ];
        })->toArray();

        $products = [];
        foreach ($stock as $index => $productInfo){
            $product = InventoryProduct::find($index);
            $product->stock = $productInfo;

            $products[] = $product;
        }

        //Complete list of products with products that are not in the warehouse:
        $productsInWarehouseIds = $productsInWarehouse->pluck('inventory_product_id')->toArray();

        //Remove duplicates:
        $productsInWarehouseIds = array_unique($productsInWarehouseIds);

        $productsNotInWarehouse = InventoryProduct::whereNotIn('id', $productsInWarehouseIds)->get();

        foreach ($productsNotInWarehouse as $product){
            $product->stock = [
                'in_stock_count' => 0,
                'sold_count' => 0,
                'average_buy_price' => [],
                'average_sell_price' => [],
                'items' => []
            ];
            $products[] = $product;
        }

        return collect($products);
    }

}
