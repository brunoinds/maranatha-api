<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseRequest;
use App\Http\Requests\UpdateInventoryWarehouseRequest;
use App\Models\InventoryWarehouse;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;
use App\Models\InventoryProduct;
use Illuminate\Support\Facades\DB;
use App\Support\Cache\DataCache;
use App\Helpers\Enums\MoneyType;
use App\Models\User;
use Carbon\Carbon;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Models\Job;
use App\Models\Expense;


class InventoryWarehouseController extends Controller
{
    public function index()
    {
        return InventoryWarehouse::all();
    }

    public function listProductItems(InventoryWarehouse $warehouse, InventoryProduct $product)
    {
        $getCountableProductItems = function () use ($warehouse, $product) {
            $incomesIds = $warehouse->items()
                ->where('inventory_product_id', $product->id)
                ->groupBy('inventory_warehouse_income_id')
                ->select('inventory_warehouse_income_id')
                ->pluck('inventory_warehouse_income_id')
                ->unique();

            $movements = $incomesIds->map(function ($incomeId) use ($warehouse, $product) {
                $items = $warehouse->items()->where('inventory_product_id', $product->id)->where('inventory_warehouse_income_id', $incomeId);

                return [
                    'income_id' => $incomeId,
                    'count' => (clone $items)->count(),
                    'price' => (clone $items)->first()->buy_amount,
                    'currency' => (clone $items)->first()->buy_currency,
                    'total_price' => (clone $items)->sum('buy_amount'),
                    'outcomes' => (function () use ($items) {
                        $outcomeItems = (clone $items)->whereNotNull('inventory_warehouse_outcome_id')
                            ->groupBy('inventory_warehouse_outcome_id')
                            ->select('inventory_warehouse_outcome_id')
                            ->pluck('inventory_warehouse_outcome_id')
                            ->unique();

                        return $outcomeItems->map(function ($outcomeId) use ($items) {
                            $outcomeItems = $items->where('inventory_warehouse_outcome_id', $outcomeId);
                            return [
                                'outcome_id' => $outcomeId,
                                'count' => $outcomeItems->count(),
                            ];
                        });
                    })(),
                    'remaining_count' => $warehouse->items()->where('inventory_product_id', $product->id)->where('inventory_warehouse_income_id', $incomeId)->where(['inventory_warehouse_outcome_id' => NULL])->count(),
                    'remaining_price' => $warehouse->items()->where('inventory_product_id', $product->id)->where('inventory_warehouse_income_id', $incomeId)->where(['inventory_warehouse_outcome_id' => NULL])->sum('buy_amount'),
                ];
            });


            $validated = request()->validate([
                'page' => 'required|numeric|min:1',
            ]);

            $productItems = $warehouse->items()->where('inventory_product_id', $product->id)->paginate(100, ['*'], 'page', $validated['page']);

            return [
                'pages' => $productItems->lastPage(),
                'items' => $productItems->items(),
                'movements_history' => $movements->toArray(),
            ];
        };

        $getUncountableProductItems = function () use ($warehouse, $product) {
            $incomesIds = $warehouse->uncountableItems()
                ->where('inventory_product_id', $product->id)
                ->groupBy('inventory_warehouse_income_id')
                ->select('inventory_warehouse_income_id')
                ->pluck('inventory_warehouse_income_id')
                ->unique();

            $movements = $incomesIds->map(function ($incomeId) use ($warehouse, $product) {
                $items = $warehouse->uncountableItems()->where('inventory_product_id', $product->id)->where('inventory_warehouse_income_id', $incomeId);


                $outcomes = (function () use ($items) {
                    $outcomesDetails = $items->first()->outcomes_details;
                    $results = [];
                    foreach ($outcomesDetails as $outcomeId => $outcomeDetails) {
                        $results[] = [
                            'outcome_id' => $outcomeId,
                            'count' => $outcomeDetails['quantity'],
                        ];
                    }
                    return $results;
                })();

                $count = (clone $items)->first()->quantity_inserted;
                $price = (clone $items)->first()->calculateSellPriceFromBuyPrice(1);
                $remainingCount = (clone $items)->first()->quantity_remaining;
                $totalPrice = (clone $items)->sum('buy_amount');

                return [
                    'income_id' => $incomeId,
                    'count' => $count,
                    'price' => $price,
                    'currency' => (clone $items)->first()->buy_currency,
                    'total_price' => $totalPrice,
                    'outcomes' => $outcomes,
                    'remaining_count' => $remainingCount,
                    'remaining_price' => ($remainingCount * $price)
                ];
            });


            $validated = request()->validate([
                'page' => 'required|numeric|min:1',
            ]);

            $productItems = $warehouse->uncountableItems()->where('inventory_product_id', $product->id)->paginate(100, ['*'], 'page', $validated['page']);

            return [
                'pages' => $productItems->lastPage(),
                'items' => $productItems->items(),
                'movements_history' => $movements->toArray(),
            ];
        };


        if ($product->unitNature() === 'Integer') {
            $productItems = $getCountableProductItems();
        } elseif ($product->unitNature() === 'Float') {
            $productItems = $getUncountableProductItems();
        }

        return response()->json($productItems);
    }
    public function listIncomes(InventoryWarehouse $warehouse)
    {
        $incomes = $warehouse->incomes;

        //Do not include ->items from eager loading:
        $incomes->makeHidden('items');

        //Include the value of method amount() as property:
        $incomes->map(function ($income) {
            $income->amount = $income->amount();
            $income->items_count = $income->items()->count() + $income->uncountableItems()->count();
            return $income;
        });

        return response()->json($incomes);
    }

    public function listOutcomes(InventoryWarehouse $warehouse)
    {
        $outcomes = $warehouse->outcomes;
        //Do not include ->items from eager loading:
        $outcomes->makeHidden('items');

        //Include the value of method amount() as property:
        $outcomes->map(function ($outcome) {
            $outcome->amount = $outcome->amount();
            $outcome->items_count = $outcome->items()->count() + $outcome->uncountableItems()->count();
            return $outcome;
        });

        return response()->json($outcomes);
    }



    public function listLoans(InventoryWarehouse $warehouse)
    {
        $loans = $warehouse->loans()->with([
            'productItem:id,inventory_product_id,status,batch,amount,currency',
            'productItem.product:id,name,description,category,brand,unit',
            'loanedBy:id,name,username,email',
            'loanedTo:id,name,username,email'
        ])->get();

        return response()->json($loans->toArray());
    }

    public function listLoansByUsers(InventoryWarehouse $warehouse)
    {
        $loans = $warehouse->loans()->with([
            'productItem:id,inventory_product_id,status,batch,amount,currency',
            'productItem.product:id,name,description,category,brand,unit',
            'loanedBy:id,name,username,email',
            'loanedTo:id,name,username,email'
        ])->get();

        $groupedLoans = $loans->groupBy('loaned_to_user_id')->map(function ($userLoans) {
            return [
                'user' => $userLoans->first()->loanedTo->toArray(),
                'loans' => $userLoans->toArray()
            ];
        });

        return response()->json($groupedLoans->values()->toArray());
    }

    public function listOutcomeRequests(InventoryWarehouse $warehouse)
    {

        //Do not include ->chat property from eager loading:
        $outcomes = $warehouse->outcomeRequests()->get()
            ->makeHidden('messages');

        return response()->json($outcomes->toArray());
    }

    public function listProducts(InventoryWarehouse $warehouse)
    {
        $products = $warehouse->products;
        return response()->json($products->toArray());
    }

    public function listStock(InventoryWarehouse $warehouse)
    {
        /* if (DataCache::getRecord('warehouseStockList', [$warehouse->id])) {
            return response()->json([
                'items' => DataCache::getRecord('warehouseStockList', [$warehouse->id]),
                'is_cached' => true
            ]);
        } */

        $stock = $warehouse->stock();

        DataCache::storeRecord('warehouseStockList', [$warehouse->id], $stock);

        return response()->json([
            'items' => $stock,
            'is_cached' => false
        ]);
    }

    public function listOutcomeResumeAnalisys(InventoryWarehouse $warehouse)
    {
        $validated = request()->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:inventory_products,id',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.do_loan' => 'required|boolean',
        ]);

        $productsResume = collect([]);
        $batchSize = 500; //Limit for SQLite parameter restriction

        foreach ($validated['products'] as $product) {
            $inventoryProduct = InventoryProduct::find($product['product_id']);

            $getCountableProductResume = function () use ($product, $warehouse, $validated, $batchSize) {
                // Split the product quantity into manageable batches
                $quantity = $product['quantity'];
                $productItemsIdsChosen = [];

                // Fetch in batches, respecting FIFO logic
                $offset = 0;
                while ($quantity > 0) {
                    $limit = min($quantity, $batchSize);
                    $batchIds = InventoryProductItem::where('inventory_product_id', $product['product_id'])
                        ->where('inventory_warehouse_id', $warehouse->id)
                        ->where('status', 'InStock')
                        ->orderBy('order', 'asc')
                        ->offset($offset)
                        ->limit($limit)
                        ->select('id')
                        ->pluck('id')
                        ->toArray();

                    $productItemsIdsChosen = array_merge($productItemsIdsChosen, $batchIds);

                    // Decrease the remaining quantity and increase the offset
                    $quantity -= $limit;
                    $offset += $limit;
                }

                // Handle batch queries for buy_currency and other info
                $itemsChosenToSellBuyCurrenciesFounds = $this->batchQuery(function ($batch) {
                    return InventoryProductItem::whereIn('id', $batch)
                        ->groupBy('buy_currency')
                        ->pluck('buy_currency')
                        ->flatten()
                        ->toArray();
                }, $productItemsIdsChosen);


                $itemsChosenToSellBuyCurrenciesFounds = array_unique($itemsChosenToSellBuyCurrenciesFounds);

                $prices = [];
                foreach ($itemsChosenToSellBuyCurrenciesFounds as $buyCurrency) {
                    $itemsChosenToSellBuyAmount = $this->batchQuery(function ($batch) use ($buyCurrency) {
                        return InventoryProductItem::whereIn('id', $batch)
                            ->where('buy_currency', $buyCurrency)
                            ->sum('buy_amount');
                    }, $productItemsIdsChosen);

                    $itemsChosenToSellBuyCount = $this->batchQuery(function ($batch) use ($buyCurrency) {
                        return InventoryProductItem::whereIn('id', $batch)
                            ->where('buy_currency', $buyCurrency)
                            ->count();
                    }, $productItemsIdsChosen);

                    $prices[] = [
                        'currency' => $buyCurrency,
                        'amount' => $itemsChosenToSellBuyAmount,
                        'count' => $itemsChosenToSellBuyCount,
                    ];
                }

                $itemsChosenToSellAggregation = $this->batchQuery(function ($batch) {
                    return InventoryProductItem::whereIn('id', $batch)
                        ->groupBy(['buy_currency', 'buy_amount'])
                        ->select('buy_currency', 'buy_amount', DB::raw('COUNT(*) as count'), DB::raw('SUM(buy_amount) as total_buy_amount'))
                        ->get()
                        ->toArray();
                }, $productItemsIdsChosen);

                return [
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'do_loan' => $product['do_loan'],
                    'is_uncountable' => false,
                    'items_ids' => $productItemsIdsChosen,
                    'items_uncountable' => [],
                    'items_aggregated' => collect($itemsChosenToSellAggregation)->map(function ($item) {
                        return [
                            'currency' => $item['buy_currency'],
                            'unit_amount' => $item['buy_amount'],
                            'count' => $item['count'],
                            'total_amount' => $item['total_buy_amount'],
                        ];
                    })->toArray(),
                    'prices' => $prices,
                ];
            };
            $getUncountableProductResume = function () use ($product, $warehouse, $validated) {
                // Split the product quantity into manageable batches:
                $quantityRequired = $product['quantity'];

                $quantityReached = 0;
                $productsItemsSelected = [];

                InventoryProductItemUncountable::where('inventory_product_id', $product['product_id'])
                    ->where('inventory_warehouse_id', $warehouse->id)
                    ->where('status', 'InStock')
                    ->orderBy('order', 'asc')
                    ->select(['id', 'quantity_remaining'])
                    ->each(function ($item) use (&$productsItemsSelected, $quantityRequired, &$quantityReached) {
                        $quantityToReach = $quantityRequired - $quantityReached;
                        if ($item->quantity_remaining >= $quantityToReach) {
                            $quantityReached += $quantityToReach;
                            $productsItemsSelected[] = [
                                'item_id' => $item->id,
                                'quantity' => $quantityToReach,
                            ];
                            return false;
                        } elseif ($item->quantity_remaining < $quantityToReach) {
                            $quantityReached += $item->quantity_remaining;
                            $productsItemsSelected[] = [
                                'item_id' => $item->id,
                                'quantity' => $item->quantity_remaining,
                            ];
                        }
                    });

                $productItemsIdsChosen = array_map(function ($item) {
                    return $item['item_id'];
                }, $productsItemsSelected);


                // Handle batch queries for buy_currency and other info
                $itemsChosenToSellBuyCurrenciesFounds = $this->batchQuery(function ($batch) {
                    return InventoryProductItemUncountable::whereIn('id', $batch)
                        ->groupBy('buy_currency')
                        ->pluck('buy_currency')
                        ->flatten()
                        ->toArray();
                }, $productItemsIdsChosen);




                $itemsChosenToSellBuyCurrenciesFounds = array_unique($itemsChosenToSellBuyCurrenciesFounds);

                $prices = [];
                foreach ($itemsChosenToSellBuyCurrenciesFounds as $buyCurrency) {
                    $amount = $this->batchQuery(function ($items) use ($buyCurrency) {
                        $amountSum = 0;
                        InventoryProductItemUncountable::whereIn('id', array_map(function ($item) {
                            return $item['item_id'];
                        }, $items))
                            ->where('buy_currency', $buyCurrency)
                            ->each(function (InventoryProductItemUncountable $inventoryProductItemUncountable) use ($items, &$amountSum) {
                                $item = collect($items)->firstWhere('item_id', $inventoryProductItemUncountable->id);
                                $amountSum += $inventoryProductItemUncountable->calculateSellPriceFromBuyPrice($item['quantity']);
                            });
                        return $amountSum;
                    }, $productsItemsSelected);


                    $prices[] = [
                        'currency' => $buyCurrency->value,
                        'amount' => $amount,
                        'count' => array_reduce($productsItemsSelected, function ($carry, $item) use ($buyCurrency) {
                            return $carry + $item['quantity'];
                        }, 0),
                    ];
                }


                return [
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'do_loan' => false,
                    'is_uncountable' => true,
                    'items_ids' => [],
                    'items_uncountable' => array_map(function ($item) {
                        return [
                            'id' => $item['item_id'],
                            'quantity' => $item['quantity'],
                        ];
                    }, $productsItemsSelected),
                    'items_aggregated' => collect($productsItemsSelected)->map(function ($item) {
                        $inventoryProductItemUncountable = InventoryProductItemUncountable::find($item['item_id']);

                        $amount = $inventoryProductItemUncountable->calculateSellPriceFromBuyPrice($item['quantity']);
                        return [
                            'currency' => $inventoryProductItemUncountable->buy_currency,
                            'unit_amount' => $inventoryProductItemUncountable->calculateSellPriceFromBuyPrice(1),
                            'count' => $item['quantity'],
                            'total_amount' => $amount,
                        ];
                    })->toArray(),
                    'prices' => $prices,
                ];
            };

            if ($inventoryProduct->unitNature() === 'Integer') {
                $productsResume->push($getCountableProductResume());
            } elseif ($inventoryProduct->unitNature() === 'Float') {
                $productsResume->push($getUncountableProductResume());
            }
        }

        $productsResume = $productsResume->toArray();

        $outcomeResume = [
            'products' => $productsResume,
            'summary' => [
                'prices' => (function () use ($productsResume) {
                    $prices = [];
                    foreach ($productsResume as $productResume) {
                        if ($productResume['do_loan']) {
                            continue;
                        }

                        foreach ($productResume['prices'] as $price) {
                            $found = false;
                            foreach ($prices as $index => $priceFound) {
                                if ($priceFound['currency'] == $price['currency']) {
                                    $prices[$index]['amount'] += $price['amount'];
                                    $prices[$index]['count'] += $price['count'];
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found) {
                                $prices[] = $price;
                            }
                        }
                    }

                    return $prices;
                })(),
                'items_to_loan' => (function () use ($productsResume) {
                    $items = [];
                    foreach ($productsResume as $productResume) {
                        if ($productResume['do_loan']) {
                            $items = array_merge($items, $productResume['items_ids']);
                        }
                    }
                    return $items;
                })(),
                'items_to_sell' => (function () use ($productsResume) {
                    $items = [];
                    foreach ($productsResume as $productResume) {
                        if (!$productResume['do_loan']) {
                            $items = array_merge($items, $productResume['items_ids']);
                        }
                    }
                    return $items;
                })(),

                'items_uncountable_to_sell' => (function () use ($productsResume) {
                    $items = [];
                    foreach ($productsResume as $productResume) {
                        if (!$productResume['do_loan']) {
                            $items = array_merge($items, $productResume['items_uncountable']);
                        }
                    }
                    return $items;
                })(),
            ]
        ];

        return response()->json($outcomeResume);
    }


    protected function batchQuery($queryFunc, array $ids, $batchSize = 500)
    {
        $results = [];
        $isNumeric = false;
        foreach (array_chunk($ids, $batchSize) as $batch) {
            $response = $queryFunc($batch);

            if (is_array($response)) {
                $results = array_merge($results, $response);
            } elseif (is_numeric($response)) {
                $results[] = $response;
                $isNumeric = true;
            }
        }

        if ($isNumeric) {
            return array_sum($results);
        }

        return $results;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryWarehouseRequest $request)
    {
        $validated = $request->validated();
        $warehouse = InventoryWarehouse::create($validated);

        DataCache::clearRecord('warehouseStockList', [$warehouse->id]);
        return response()->json(['message' => 'Warehouse created', 'warehouse' => $warehouse->toArray()]);
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryWarehouse $warehouse)
    {
        return response()->json($warehouse->toArray());
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryWarehouseRequest $request, InventoryWarehouse $warehouse)
    {
        $validated = $request->validated();
        $warehouse->update($validated);

        return response()->json(['message' => 'Warehouse updated', 'warehouse' => $warehouse->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryWarehouse $warehouse)
    {
        DataCache::clearRecord('warehouseStockList', [$warehouse->id]);
        $warehouse->delete();
        return response()->json(['message' => 'Warehouse deleted successfully']);
    }
}
