<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Models\InventoryProductItem;
use App\Helpers\Toolbox;


class RecordInventoryProductsBalance
{

    private string|null $moneyType = null;
    private array|null $warehouseIds = null;
    private string|null $productId = null;
    private array|null $categories = null;

    /**
        * RecordInventoryProductsBalance constructor.

        * @param array $options
        * @param string|null $options['moneyType']
        * @param string|null $options['warehouseIds']
        * @param string|null $options['productId']
                * @param string|null $options['categories']
     */

    public function __construct(array $options){
        $this->moneyType = $options['moneyType'] ?? null;
        $this->warehouseIds = $options['warehouseIds'] ?? null;
        $this->productId = $options['productId'] ?? null;
        $this->categories = $options['categories'] ?? null;
    }

    private function getProductsItems():Collection
    {
        $options = [
            'moneyType' => $this->moneyType,
            'warehouseIds' => $this->warehouseIds,
            'productId' => $this->productId,
            'categories' => $this->categories
        ];

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
        $query->groupBy(['buy_currency', 'buy_amount'])
                ->select()->each(function($item) use (&$options, &$items){
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

                    $productItems = $query->where('buy_currency', $item->buy_currency)
                        ->where('buy_amount', $item->buy_amount);

                    if ($options['categories'] !== null){
                        if (!in_array((clone $productItems)->first()->product->category, $options['categories'])){
                            return;
                        }
                    }

                    $items[] = [
                        'id' => (clone $productItems)->first()->product->id,
                        'name' => (clone $productItems)->first()->product->name,
                        'category' => (clone $productItems)->first()->product->category,
                        'currency' => (clone $productItems)->first()->buy_currency,
                        'warehouse' => (clone $productItems)->first()->warehouse->name,
                        'income_quantity' => (clone $productItems)->count(),
                        'outcome_quantity' => (clone $productItems)->where('status', InventoryProductItemStatus::Sold)->count(),
                        'outcome_amount' => (clone $productItems)->where('status', InventoryProductItemStatus::Sold)->first()?->buy_amount ?? 0,
                        'balance_quantity' => (clone $productItems)->count() - (clone $productItems)->where('status', InventoryProductItemStatus::Sold)->count(),
                        'balance_total_amount' => ((clone $productItems)->count() - (clone $productItems)->where('status', InventoryProductItemStatus::Sold)->count()) * ((clone $productItems)->first()?->buy_amount ?? 0)
                    ];
                });

        return collect($items);
    }

    private function createTable():array{
        $items = $this->getProductsItems();

        $body = collect($items)->map(function($item){
            return [
                'product_id' => $item['id'],
                'product_name' => $item['name'],
                'category' => $item['category'],
                'currency' => $item['currency'],
                'warehouse' => $item['warehouse'],
                'income_quantity' => $item['income_quantity'],
                'outcome_quantity' => $item['outcome_quantity'],
                'outcome_amount' => Toolbox::moneyFormat($item['outcome_amount'], $item['currency']),
                'balance_quantity' => $item['balance_quantity'],
                'balance_total_amount' => Toolbox::moneyFormat($item['balance_total_amount'], $item['currency'])
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
                    'key' => 'balance_quantity'
                ],
                [
                    'title' => 'Precio',
                    'key' => 'outcome_amount'
                ],
                [
                    'title' => 'Moneda',
                    'key' => 'currency'
                ],
                [
                    'title' => 'Total',
                    'key' => 'balance_total_amount'
                ]
            ],
            'body' => $body,
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'moneyType' => $this->moneyType,
                'warehouseIds' => $this->warehouseIds,
                'productId' => $this->productId,
                'categories' => $this->categories
            ],
        ];
    }
}
