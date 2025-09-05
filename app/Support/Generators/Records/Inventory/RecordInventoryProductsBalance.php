<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;
use App\Helpers\Toolbox;


class RecordInventoryProductsBalance
{

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
                    ->select()->each(function($item) use (&$options, &$items){
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

                        $items[] = [
                            'id' => (clone $productItems)->first()->product->id,
                            'name' => (clone $productItems)->first()->product->name,
                            'category' => (clone $productItems)->first()->product->category,
                            'sub_category' => (clone $productItems)->first()->product->sub_category,
                            'currency' => (clone $productItems)->first()->buy_currency,
                            'warehouse' => (clone $productItems)->first()->warehouse->name,


                            /* 'q' => (clone $productItems)->toRawSql(), */
                            'income_quantity' => (clone $productItems)->count(),
                            'outcome_quantity' => (clone $productItems)->where('status', InventoryProductItemStatus::Sold)->count(),

                            'stock_quantity' => (clone $productItems)->where('status', InventoryProductItemStatus::InStock)->count(),
                            'stock_amount' => (clone $productItems)->where('status', InventoryProductItemStatus::InStock)->sum('buy_amount'),
                            'unit_price' => (clone $productItems)->first()->buy_amount,
                            /*
                            'outcome_amount' => (clone $productItems)->first()?->buy_amount ?? 0,
                            'balance_quantity' => (clone $productItems)->count() - (clone $productItems)->where('status', InventoryProductItemStatus::Sold)->count(),
                            'balance_total_amount' => ((clone $productItems)->count() - (clone $productItems)->where('status', InventoryProductItemStatus::Sold)->count()) * ((clone $productItems)->first()?->buy_amount ?? 0)
                            */
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
                    ->select()->each(function($item) use (&$options, &$items){
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

                        $items[] = [
                            'id' => (clone $productItems)->first()->product->id,
                            'name' => (clone $productItems)->first()->product->name,
                            'category' => (clone $productItems)->first()->product->category,
                            'sub_category' => (clone $productItems)->first()->product->sub_category,
                            'currency' => (clone $productItems)->first()->buy_currency->value,
                            'warehouse' => (clone $productItems)->first()->warehouse->name,

                            /* 'q' => (clone $productItems)->toRawSql(), */
                            'income_quantity' => (clone $productItems)->sum('quantity_inserted'),
                            'outcome_quantity' => (clone $productItems)->sum('quantity_used'),
                            'stock_quantity' => (clone $productItems)->sum('quantity_remaining'),
                            'stock_amount' => (clone $productItems)->sum('quantity_remaining') * (clone $productItems)->first()->calculateSellPriceFromBuyPrice(1),
                            'unit_price' => (clone $productItems)->first()->calculateSellPriceFromBuyPrice(1),
                            /*
                            'outcome_amount' => (clone $productItems)->first()->calculateSellPriceFromBuyPrice(1),
                            'balance_quantity' => (clone $productItems)->sum('quantity_used'),
                            'balance_total_amount' => (((clone $productItems)->first()->calculateSellPriceFromBuyPrice(1) * (clone $productItems)->sum('quantity_inserted')) - ((clone $productItems)->sum('quantity_used') * (clone $productItems)->first()->calculateSellPriceFromBuyPrice(1)))
                            */
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
                'income_quantity' => $item['income_quantity'],
                'outcome_quantity' => $item['outcome_quantity'],
                'stock_quantity' => $item['stock_quantity'],
                'stock_amount' => $item['stock_amount'],
                'unit_price' => $item['unit_price'],
                /* 'q' => $item['q'], */
                /* 'outcome_amount' => Toolbox::moneyFormat($item['outcome_amount'], $item['currency']),
                'balance_quantity' => $item['balance_quantity'],
                'balance_total_amount' => Toolbox::moneyFormat($item['balance_total_amount'], $item['currency']) */
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
                    'title' => 'Ingreso',
                    'key' => 'income_quantity'
                ],
                [
                    'title' => 'Salida',
                    'key' => 'outcome_quantity'
                ],
                [
                    'title' => 'Actual',
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
