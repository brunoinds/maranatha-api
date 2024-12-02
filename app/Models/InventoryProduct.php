<?php

namespace App\Models;

use App\Helpers\Enums\InventoryProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Helpers\Enums\InventoryProductUnit;
use App\Models\InventoryProductsPack;
use App\Support\Cache\DataCache;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Models\InventoryWarehouse;
use App\Models\InventoryProductItemUncountable;
use App\Support\Toolbox\TString;





class InventoryProduct extends Model
{
    use HasFactory;
    use \Staudenmeir\EloquentJsonRelations\HasJsonRelationships;


    protected $fillable = [
        'name',
        'description',
        'category',
        'sub_category',
        'brand',
        'presentation',
        'unit',
        'code',
        'status',
        'image',
        'is_loanable',
        'inventory_warehouses_ids'
    ];

    protected $casts = [
        'unit' => InventoryProductUnit::class,
        'status' => InventoryProductStatus::class,
        'inventory_warehouses_ids' => 'array'
    ];

    public function warehouses()
    {
        return $this->hasMany(InventoryWarehouse::class, 'id', 'inventory_warehouses_ids');
    }

    public function items()
    {
        return $this->hasMany(InventoryProductItem::class);
    }

    public function uncountableItems()
    {
        return $this->hasMany(InventoryProductItemUncountable::class);
    }

    public function stockCount()
    {
        return $this->items()->where('status', InventoryProductItemStatus::InStock)->count();
    }

    public function averageAmount()
    {
        return $this->items()->avg('amount');
    }

    public function clearStockCaches()
    {
        InventoryWarehouse::all()->each(function($warehouse){
            DataCache::clearRecord('warehouseStockList', [$warehouse->id]);
        });
    }

    public function convertItemsUnitNature(string $convertTo)
    {
        $inst = $this;

        $convertItemsToFloatItems = function() use ($inst){
            //To Float:
            $inst->items()->each(function($item){
                $item->loans()->each(function($loan){
                    $loan->delete();
                });
                $item->delete();
            });
        };

        $convertItemsToIntegerItems = function() use ($inst){
            //To Integer:
            $inst->uncountableItems()->each(function($uncountableItem){
                $uncountableItem->delete();
            });
        };


        /*
        $convertItemsToFloatItems = function() use ($inst){
            //To Float:
            $inst->items()->each(function($item){
                $item->loans()->each(function($loan){
                    $loan->delete();
                });
            });
            $inst->items()->groupBy('inventory_warehouse_income_id', 'inventory_warehouse_outcome_id')->chunk(300, function($group) {
                $lastOrder = InventoryProductItemUncountable::orderBy('order', 'desc')->first();
                $lastOrder = $lastOrder ? $lastOrder->order : -1;

                $wasSelled = $group->first()->inventory_warehouse_outcome_id ? true : false;



                InventoryProductItemUncountable::create([
                    'batch' => TString::generateRandomBatch(),
                    'order' => $lastOrder + 1,
                    'buy_amount' => (float) $group->sum('buy_amount'),
                    'buy_currency' => $group->first()->buy_currency,

                    'quantity_inserted' => $group->count(),
                    'quantity_used' => ($wasSelled) ? $group->count() : 0,
                    'quantity_remaining' => ($wasSelled) ? 0 : $group->count(),

                    'status' => $wasSelled ? InventoryProductItemStatus::Sold : InventoryProductItemStatus::InStock,

                    'inventory_product_id' => $group->first()->inventory_product_id,
                    'inventory_warehouse_id' => $group->first()->inventory_warehouse_id,
                    'inventory_warehouse_income_id' =>  $group->first()->inventory_warehouse_income_id,
                    'inventory_warehouse_outcome_ids' => ($wasSelled) ? [$group->first()->inventory_warehouse_outcome_id] : [],
                    'outcomes_details' => ($wasSelled) ? [
                        $group->first()->inventory_warehouse_outcome_id => [
                            'quantity' => $group->count(),
                            'sell_amount' => $group->sum('sell_amount'),
                            'sell_currency' => $group->first()->sell_currency
                        ]
                    ] : []
                ]);

                $group->each(function($item){
                    $item->delete();
                });
            });
        };

        $convertItemsToIntegerItems = function() use ($inst){
            //To Integer:
            $inst->uncountableItems()->each(function($uncountableItem){
                $countInserted = ceil($uncountableItem->quantity_inserted);

                if ($countInserted === 0){
                    return;
                }

                $buyPricePerItem = $uncountableItem->buy_amount / $uncountableItem->quantity_inserted;
                $buyCurrency = $uncountableItem->buy_currency;

                $lastOrder = InventoryProductItem::orderBy('order', 'desc')->first();
                $lastOrder = $lastOrder ? $lastOrder->order : -1;

                $countSold = 0;
                //Add items with outcome
                foreach ($uncountableItem->outcomes_details as $outcomeId => $outcomeDetail) {
                    $quantity = ceil($outcomeDetail['quantity']);

                    if ($quantity === 0){
                        continue;
                    }

                    $sellPricePerItem = $outcomeDetail['sell_amount'] / $quantity;
                    $sellCurrency = $outcomeDetail['sell_currency'];

                    $i = 0;
                    while ($i < $quantity) {
                        InventoryProductItem::create([
                            'batch' => TString::generateRandomBatch(),
                            'order' => $lastOrder + $i + 1,
                            'buy_amount' => (float) round($buyPricePerItem, 2),
                            'sell_amount' => (float)  round($sellPricePerItem, 2),
                            'buy_currency' => $buyCurrency,
                            'sell_currency' => $sellCurrency,
                            'status' => InventoryProductItemStatus::Sold,
                            'inventory_product_id' => $uncountableItem->inventory_product_id,
                            'inventory_warehouse_id' => $uncountableItem->inventory_warehouse_id,
                            'inventory_warehouse_income_id' => $uncountableItem->inventory_warehouse_income_id,
                            'inventory_warehouse_outcome_id' => $outcomeId
                        ]);
                        $i++;
                    }
                    $countSold += $quantity;
                }

                //Add remaining items without outcome:
                $i = 0;

                while ($i < $countInserted - $countSold) {
                    InventoryProductItem::create([
                        'batch' => TString::generateRandomBatch(),
                        'order' => $lastOrder + $i + 1,
                        'buy_amount' => (float) round($buyPricePerItem, 2),
                        'buy_currency' => $buyCurrency,
                        'status' => InventoryProductItemStatus::InStock,
                        'inventory_product_id' => $uncountableItem->inventory_product_id,
                        'inventory_warehouse_id' => $uncountableItem->inventory_warehouse_id,
                        'inventory_warehouse_income_id' => $uncountableItem->inventory_warehouse_income_id
                    ]);
                    $i++;
                }

                $uncountableItem->delete();
            });
        };
        */

        if ($convertTo == 'Integer'){
            $convertItemsToIntegerItems();
        } else if ($convertTo == 'Float'){
            $convertItemsToFloatItems();
        }
    }

    public function unitNature(): string|null
    {
        return InventoryProductUnit::getNature($this->unit);
    }

    public function delete()
    {

        $this->items()->each(function($item){
            $item->delete();
        });

        $this->uncountableItems()->each(function($item){
            $item->delete();
        });

        $this->clearStockCaches();

        //Delete product on InventoryProductsPack that contains this product, that has an attribute products that is an array of objects like: [{product_id: 1, quantity: 1}]'
        $productPacks = InventoryProductsPack::all();
        foreach ($productPacks as $productPack) {
            $productPack->products = array_filter($productPack->products, function($productPack){
                return $productPack['product_id'] !== $this->id;
            });
            $productPack->save();
        }


        //Delete product on OutcomeRequest that contains this product, that has an attribute requested_products that is an array of objects like: [{product_id: 1, amount: 2, unit: 'kg'}]'
        $outcomeRequests = InventoryWarehouseOutcomeRequest::all();
        foreach ($outcomeRequests as $outcomeRequest) {
            $outcomeRequest->requested_products = array_filter($outcomeRequest->requested_products, function($requestedProduct){
                return $requestedProduct['product_id'] !== $this->id;
            });
            $outcomeRequest->received_products = array_filter($outcomeRequest->received_products, function($receivedProduct){
                return $receivedProduct['product_id'] !== $this->id;
            });
            $outcomeRequest->save();
        }

        return parent::delete();
    }
}
