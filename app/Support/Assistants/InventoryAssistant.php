<?php


namespace App\Support\Assistants;

use App\Models\InventoryWarehouse;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;

use App\Helpers\Enums\MoneyType;
use App\Helpers\Enums\InventoryProductUnit;

use App\Models\InventoryProduct;
use App\Helpers\Toolbox;
use Illuminate\Support\Benchmark;




class InventoryAssistant{
    private static function getWarehouseCountableStock(InventoryWarehouse $warehouse): \Illuminate\Support\Collection
    {
        $productsIds = InventoryProduct::whereIn('unit', InventoryProductUnit::naturesValues()['Integer'])->pluck('id')->toArray();

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

    private static function getWarehouseUncountableStock(InventoryWarehouse $warehouse):  \Illuminate\Support\Collection{
        $productsIds = InventoryProduct::whereIn('unit', InventoryProductUnit::naturesValues()['Float'])->pluck('id')->toArray();


        $stock = [];
        foreach ($productsIds as $productId){
            $averageBuyPrices = MoneyType::toAssociativeArray(null);
            $averageSellPrices = MoneyType::toAssociativeArray(null);


            $productItemsCountQuery = InventoryProductItemUncountable::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);

            if ($productItemsCountQuery->sum('quantity_inserted') == 0){
                $stock[] = [
                    'in_stock_count' => 0,
                    'sold_count' => 0,
                    'average_buy_price' => [],
                    'average_sell_price' => [],
                ];
                continue;
            }





            $productItemsBuyAmountQuery = InventoryProductItemUncountable::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsSellAmountQuery = InventoryProductItemUncountable::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsBuyCountQuery = InventoryProductItemUncountable::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);

            $productItemsInStockCountQuery = InventoryProductItemUncountable::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsSoldCountQuery = InventoryProductItemUncountable::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);
            $productItemsAllCountQuery = InventoryProductItemUncountable::where('inventory_product_id', $productId)->where('inventory_warehouse_id', $warehouse->id);


            foreach ($averageBuyPrices as $moneyType => $value){
                $totalBuyAmount = $productItemsBuyAmountQuery->where('buy_currency', $moneyType)->sum('buy_amount');
                $totalBuyCount = $productItemsBuyCountQuery->where('buy_currency', $moneyType)->sum('quantity_inserted');
                $averageBuyPrice = Toolbox::toFixed($totalBuyAmount / ($totalBuyCount == 0 ? 1 : $totalBuyCount));


                $totalSellCount = $productItemsSellAmountQuery->where('buy_currency', $moneyType)->sum('quantity_used');
                $totalSellAmount = $totalSellCount * $averageBuyPrice;
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
                'in_stock_count' => $productItemsInStockCountQuery->sum('quantity_remaining'),
                'sold_count' => $productItemsSoldCountQuery->sum('quantity_used'),
                'all_count' => $productItemsAllCountQuery->sum('quantity_inserted'),
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


    public static function getWarehouseStock(InventoryWarehouse $warehouse): \Illuminate\Support\Collection
    {
        //Merge countable and uncountable stocks:

        $countableStock = self::getWarehouseCountableStock($warehouse);
        $uncountableStock = self::getWarehouseUncountableStock($warehouse);

        return $countableStock->merge($uncountableStock);
    }
}
