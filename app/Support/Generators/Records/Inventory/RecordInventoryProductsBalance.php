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

    /**
        * RecordInventoryProductsBalance constructor.

        * @param array $options
        * @param string|null $options['moneyType']
        * @param string|null $options['warehouseIds']
        * @param string|null $options['productId']
        * @param string|null $options['categories']
        * @param string|null $options['subCategories']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'] ?? null;
        $this->endDate = $options['endDate'] ?? null;
        $this->moneyType = $options['moneyType'] ?? null;
        $this->warehouseIds = $options['warehouseIds'] ?? null;
        $this->productId = $options['productId'] ?? null;
        $this->categories = $options['categories'] ?? null;
        $this->subCategories = $options['subCategories'] ?? null;
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
            'subCategories' => $this->subCategories
        ];


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



            $items = [];
            $query->groupBy(['inventory_product_id','buy_currency', 'buy_amount', 'inventory_warehouse_id'])
                    ->select()->each(function($item) use (&$options, &$items, $instance){
                        $query = InventoryProductItem::query();
                        if ($options['moneyType'] !== null && $item->buy_currency !== $options['moneyType']){
                            return;
                        }
                        if ($options['warehouseIds'] !== null && !in_array($item->inventory_warehouse_id, $options['warehouseIds'])){
                            return;
                        }
                        if ($options['productId'] !== null && $item->inventory_product_id !== $options['productId']){
                            return;
                        }

                        $productItems = $query->where('buy_currency', $item->buy_currency)
                            ->where('buy_amount', $item->buy_amount)
                            ->where('inventory_product_id', $item->inventory_product_id);

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


            $items = [];
            $query->groupBy(['inventory_product_id','buy_currency', 'buy_amount'])
                    ->select()->each(function($item) use (&$options, &$items, $instance){
                        $query = InventoryProductItemUncountable::query();
                        if ($options['moneyType'] !== null && $item->buy_currency !== $options['moneyType']){
                            return;
                        }
                        if ($options['warehouseIds'] !== null && !in_array($item->inventory_warehouse_id, $options['warehouseIds'])){
                            return;
                        }
                        if ($options['productId'] !== null && $item->inventory_product_id !== $options['productId']){
                            return;
                        }

                        $productItems = $query->where('buy_currency', $item->buy_currency)
                            ->where('buy_amount', $item->buy_amount)
                            ->where('inventory_product_id', $item->inventory_product_id);

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

                        $previousStockQuantity = (function() use ($productItemsLoaded, $instance){
                            $previousIncomesProductsItems = (clone $productItemsLoaded)->filter(function($item) use ($instance){
                                return Carbon::parse($item->income->date)->isBefore($instance->startDate);
                            });

                            $previousOutomesBalances =$previousIncomesProductsItems->map(function($item) use ($instance){
                                $balance = $item->quantity_inserted;
                                $item->outcomes->filter(function($outcome) use ($instance){
                                    return Carbon::parse($outcome->date)->isBefore($instance->startDate);
                                })->each(function($outcome) use ($item, &$balance){
                                    $balance -= $item->outcomes_details[$outcome->id]['quantity'];
                                });

                                return $balance;
                            });

                            return $previousIncomesProductsItems->sum('quantity_inserted') - $previousOutomesBalances->sum();
                        })();

                        $items[] = [
                            'id' => (clone $productItems)->first()->product->id,
                            'name' => (clone $productItems)->first()->product->name,
                            'category' => (clone $productItems)->first()->product->category,
                            'sub_category' => (clone $productItems)->first()->product->sub_category,
                            'currency' => (clone $productItems)->first()->buy_currency->value,
                            'warehouse' => (clone $productItems)->first()->warehouse->name,
                            'previous_stock_quantity' => $previousStockQuantity,
                            'income_quantity' => (clone $productItems)->sum('quantity_inserted'),
                            'outcome_quantity' => (clone $productItems)->sum('quantity_used'),
                            'stock_quantity' => (clone $productItems)->sum('quantity_remaining'),
                            'stock_amount' => (clone $productItems)->sum('quantity_remaining') * (clone $productItems)->first()->calculateSellPriceFromBuyPrice(1),
                            'unit_price' => (clone $productItems)->first()->calculateSellPriceFromBuyPrice(1),
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
                'sub_category' => $item['sub_category'],
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
                [
                    'title' => 'Sub Categoria',
                    'key' => 'sub_category'
                ],
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
                [
                    'title' => 'Moneda',
                    'key' => 'currency'
                ],
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
