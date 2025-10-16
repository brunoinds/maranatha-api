<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;
use App\Helpers\Toolbox;
use DateTime;
use Carbon\Carbon;

class RecordInventoryProductsBalance
{

    private DateTime|null $startDate = null;
    private DateTime|null $endDate = null;
    private string|null $moneyType = null;
    private array|null $warehouseIds = null;
    private string|null $productId = null;
    private array|null $categories = null;
    private array|null $subCategories = null;
    private bool|null $ignoreVoidPricing = null;

    /**
        * RecordInventoryProductsBalance constructor.

        * @param array $options
        * @param string|null $options['moneyType']
        * @param string|null $options['warehouseIds']
        * @param string|null $options['productId']
        * @param string|null $options['categories']
        * @param string|null $options['subCategories']
        * @param bool|null $options['ignoreVoidPricing']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'] ?? null;
        $this->endDate = $options['endDate'] ?? null;
        $this->moneyType = $options['moneyType'] ?? null;
        $this->warehouseIds = $options['warehouseIds'] ?? null;
        $this->productId = $options['productId'] ?? null;
        $this->categories = $options['categories'] ?? null;
        $this->subCategories = $options['subCategories'] ?? null;
        $this->ignoreVoidPricing = $options['ignoreVoidPricing'] ?? null;
    }

    private function getProductsItems():Collection
    {
        $instance = $this;
        $options = [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'moneyType' => $this->moneyType,
            'warehouseIds' => $this->warehouseIds,
            'productId' => $this->productId,
            'categories' => $this->categories,
            'subCategories' => $this->subCategories,
            'ignoreVoidPricing' => $this->ignoreVoidPricing
        ];

        if ($options['moneyType'] === null){
            $options['moneyType'] = 'PEN';
        }



        $countableItems = (function() use ($options, $instance){
            $query = InventoryProductItem::query();

            if ($options['moneyType'] !== null){
                $query = $query->where('buy_currency', $options['moneyType']);
            }
            if ($options['warehouseIds'] !== null){
                $query = $query->whereIn('inventory_warehouse_id', $options['warehouseIds']);
            }
            if ($options['productId'] !== null){
                $query = $query->where('inventory_product_id', $options['productId']);
            }
            if ($options['ignoreVoidPricing'] !== null && $options['ignoreVoidPricing'] === true){
                $query = $query->where('buy_amount', '>', 0);
            }



            $items = [];
            $query->groupBy(['inventory_product_id', 'inventory_warehouse_id'])
                    ->select()->each(function($item) use (&$options, &$items, $instance){
                        $query = InventoryProductItem::query();

                        $productItems = $query->where('inventory_product_id', $item->inventory_product_id)
                            ->where('inventory_warehouse_id', $item->inventory_warehouse_id)
                            ->where('buy_currency', $item->buy_currency);

                        if ($options['categories'] !== null){
                            if (!in_array((clone $productItems)->first()->product->category, $options['categories'])){
                                return;
                            }
                        }

                        if ($options['subCategories'] !== null){
                            if (!in_array((clone $productItems)->first()->product->sub_category, $options['subCategories'])){
                                return;
                            }
                        }

                        $productItemsLoaded = $productItems->with('income', 'outcome')->get();

                        $previousStockQuantity = (function() use ($productItemsLoaded, $instance){
                            $previousIncomesProductsItems = (clone $productItemsLoaded)->filter(function($item) use ($instance){
                                return Carbon::parse($item->income->date)->isBefore($instance->startDate);
                            });
                            $previousOutcomesProductsItems = (clone $productItemsLoaded)->filter(function($item) use ($instance){
                                if ($item->outcome === null){
                                    return false;
                                }
                                return Carbon::parse($item->outcome->date)->isBefore($instance->startDate);
                            });
                            return $previousIncomesProductsItems->count() - $previousOutcomesProductsItems->count();
                        })();
                        $incomeInPeriodQuantity = (clone $productItemsLoaded)->filter(function($item) use ($instance){
                            return Carbon::parse($item->income->date)->isBetween($instance->startDate, $instance->endDate);
                        })->count();
                        $outcomeInPeriodQuantity = (clone $productItemsLoaded)->filter(function($item) use ($instance){
                            if ($item->outcome === null){
                                return false;
                            }
                            return Carbon::parse($item->outcome->date)->isBetween($instance->startDate, $instance->endDate);
                        })->count();
                        $inPeriodStockQuantity = $previousStockQuantity + $incomeInPeriodQuantity - $outcomeInPeriodQuantity;



                        $items[] = [
                            'id' => (clone $productItems)->first()->product->id,
                            'name' => (clone $productItems)->first()->product->name,
                            'category' => (clone $productItems)->first()->product->category,
                            'sub_category' => (clone $productItems)->first()->product->sub_category,
                            'currency' => (clone $productItems)->first()->buy_currency,
                            'warehouse' => (clone $productItems)->first()->warehouse->name,

                            'previous_stock_quantity' => $previousStockQuantity,
                            'income_quantity' => $incomeInPeriodQuantity,
                            'outcome_quantity' => $outcomeInPeriodQuantity,

                            'stock_quantity' => $inPeriodStockQuantity,
                            'stock_amount' => (clone $productItems)->first()->buy_amount * $inPeriodStockQuantity,
                            'unit_price' => (clone $productItems)->first()->buy_amount,
                        ];
                    });



            return collect($items);
        })();

        $uncountableItems = (function() use ($options, $instance){
            $query = InventoryProductItemUncountable::query();

            if ($options['moneyType'] !== null){
                $query = $query->where('buy_currency', $options['moneyType']);
            }
            if ($options['warehouseIds'] !== null){
                $query = $query->whereIn('inventory_warehouse_id', $options['warehouseIds']);
            }
            if ($options['productId'] !== null){
                $query = $query->where('inventory_product_id', $options['productId']);
            }
            if ($options['ignoreVoidPricing'] !== null && $options['ignoreVoidPricing'] === true){
                $query = $query->where('buy_amount', '>', 0);
            }


            $items = [];
            $query->groupBy(['inventory_product_id', 'inventory_warehouse_id'])
                    ->select()->each(function($item) use (&$options, &$items, $instance){
                        $query = InventoryProductItemUncountable::query();

                        $productItems = $query->where('inventory_product_id', $item->inventory_product_id)
                            ->where('inventory_warehouse_id', $item->inventory_warehouse_id)
                            ->where('buy_currency', $item->buy_currency);


                        if ($options['categories'] !== null){
                            if (!in_array((clone $productItems)->first()->product->category, $options['categories'])){
                                return;
                            }
                        }

                        if ($options['subCategories'] !== null){
                            if (!in_array((clone $productItems)->first()->product->sub_category, $options['subCategories'])){
                                return;
                            }
                        }

                        $productItemsLoaded = $productItems->get();

                        $previousStock = (function() use ($productItemsLoaded, $instance){
                            $previousIncomesProductsItems = (clone $productItemsLoaded)->filter(function($item) use ($instance){
                                return Carbon::parse($item->income->date)->isBefore($instance->startDate);
                            });

                            $previousOutcomesBalances = $previousIncomesProductsItems->map(function($item) use ($instance){
                                $sumExits = 0;
                                $sumAmounts = 0;
                                $item->outcomes->filter(function($outcome) use ($instance){
                                    return Carbon::parse($outcome->date)->isBefore($instance->startDate);
                                })->each(function($outcome) use ($item, &$sumExits, &$sumAmounts){
                                    $sumExits += $item->outcomes_details[$outcome->id]['quantity'];
                                    $sumAmounts += $item->outcomes_details[$outcome->id]['sell_amount'];
                                });

                                return [
                                    'quantity' => $sumExits,
                                    'amount' => $sumAmounts,
                                ];
                            });


                            return [
                                'quantity' => $previousIncomesProductsItems->sum('quantity_inserted') - $previousOutcomesBalances->sum('quantity'),
                                'amount' => $previousIncomesProductsItems->sum('buy_amount') - $previousOutcomesBalances->sum('amount'),
                            ];
                        })();

                        $incomesInPeriod = (clone $productItemsLoaded)->filter(function($item) use ($instance){
                            if ($item->income === null){
                                return false;
                            }
                            return Carbon::parse($item->income->date)->isBetween($instance->startDate, $instance->endDate);
                        });



                        $incomeInPeriodQuantity = $incomesInPeriod->sum('quantity_inserted');
                        $incomeInPeriodAmount = $incomesInPeriod->sum('buy_amount');

                        $outcomeInPeriodQuantity = (function() use ($productItemsLoaded, $instance){
                            $sumExits = 0;
                            $sumAmounts = 0;
                            (clone $productItemsLoaded)->each(function($item) use ($instance, &$sumExits, &$sumAmounts){
                                $item->outcomes->filter(function($outcome) use ($instance){
                                    return Carbon::parse($outcome->date)->isBetween($instance->startDate, $instance->endDate);
                                })->each(function($outcome) use ($item, &$sumExits, &$sumAmounts){
                                    $sumExits += $item->outcomes_details[$outcome->id]['quantity'];
                                    $sumAmounts += $item->outcomes_details[$outcome->id]['sell_amount'];
                                });
                            });
                            return [
                                'quantity' => $sumExits,
                                'amount' => $sumAmounts,
                            ];
                        })();
                        $inPeriodStockQuantity = $previousStock['quantity'] + $incomeInPeriodQuantity - $outcomeInPeriodQuantity['quantity'];

                        $items[] = [
                            'id' => (clone $productItems)->first()->product->id,
                            'name' => (clone $productItems)->first()->product->name,
                            'category' => (clone $productItems)->first()->product->category,
                            'sub_category' => (clone $productItems)->first()->product->sub_category,
                            'currency' => (clone $productItems)->first()->buy_currency->value,
                            'warehouse' => (clone $productItems)->first()->warehouse->name,
                            'previous_stock_quantity' => number_format($previousStock['quantity'], 2),
                            'income_quantity' => $incomeInPeriodQuantity,
                            'outcome_quantity' => $outcomeInPeriodQuantity['quantity'],
                            'stock_quantity' => $inPeriodStockQuantity,
                            'stock_amount' => $previousStock['amount'] + $incomeInPeriodAmount - $outcomeInPeriodQuantity['amount'],
                            'unit_price' => ($inPeriodStockQuantity > 0) ? round(($previousStock['amount'] + $incomeInPeriodAmount - $outcomeInPeriodQuantity['amount']) / $inPeriodStockQuantity, 2) : 0,
                        ];
                    });



            return collect($items);
        })();

        return collect($countableItems->merge($uncountableItems));
    }

    private function createTable():array{
        $items = $this->getProductsItems();

        $body = collect($items)->map(function($item){
            return [
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'category' => $item['category'],
                //'sub_category' => $item['sub_category'],
                'currency' => $item['currency'],
                'warehouse' => $item['warehouse'],
                'previous_stock_quantity' => $item['previous_stock_quantity'],
                'income_quantity' => $item['income_quantity'],
                'outcome_quantity' => $item['outcome_quantity'],
                'stock_quantity' => $item['stock_quantity'],
                'stock_amount' => $item['stock_amount'],
                'unit_price' => $item['unit_price'],
            ];
        });

        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'ID',
                    'key' => 'product_id'
                ],
                [
                    'title' => 'Producto',
                    'key' => 'product_name'
                ],
                [
                    'title' => 'Categoria',
                    'key' => 'category'
                ],
                /* [
                    'title' => 'Sub Categoria',
                    'key' => 'sub_category'
                ], */
                [
                    'title' => 'Almacen',
                    'key' => 'warehouse'
                ],
                [
                    'title' => 'Saldo Inicial',
                    'key' => 'previous_stock_quantity'
                ],
                [
                    'title' => 'Ingresos',
                    'key' => 'income_quantity'
                ],
                [
                    'title' => 'Salidas',
                    'key' => 'outcome_quantity'
                ],
                [
                    'title' => 'Saldo Final',
                    'key' => 'stock_quantity'
                ],
                [
                    'title' => 'Precio Unitario',
                    'key' => 'unit_price'
                ],
                /* [
                    'title' => 'Moneda',
                    'key' => 'currency'
                ], */
                [
                    'title' => 'Total',
                    'key' => 'stock_amount'
                ]
            ],
            'body' => collect($body)->map(function($item){
                return [
                    ...$item,
                    'income_quantity' => '+' . $item['income_quantity'],
                    'outcome_quantity' => '-' . $item['outcome_quantity'],
                    'stock_amount' => Toolbox::moneyFormat($item['stock_amount'], $item['currency']),
                    'unit_price' => Toolbox::moneyFormat($item['unit_price'], $item['currency']),
                ];
            }),
            'footer' => [
                'totals' => [
                    'title' => 'Totales',
                    'items' => [
                        [
                            'key' => 'income_quantity',
                            'value' => round(array_sum(array_column($body, 'income_quantity')), 2),
                        ],
                        [
                            'key' => 'outcome_quantity',
                            'value' => round(array_sum(array_column($body, 'outcome_quantity')),2),
                        ],
                        [
                            'key' => 'stock_quantity',
                            'value' => round(array_sum(array_column($body, 'stock_quantity')),2),
                        ],
                        [
                            'key' => 'stock_amount',
                            'value' => Toolbox::moneyFormat(array_sum(array_column($body, 'stock_amount')), $this->moneyType),
                        ]
                    ]
                ]
            ],
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'moneyType' => $this->moneyType,
                'warehouseIds' => $this->warehouseIds,
                'productId' => $this->productId,
                'categories' => $this->categories,
                'subCategories' => $this->subCategories
            ],
        ];
    }
}
