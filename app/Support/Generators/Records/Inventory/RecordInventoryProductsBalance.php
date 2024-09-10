<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Models\InventoryProductItem;
use App\Helpers\Toolbox;


class RecordInventoryProductsBalance
{

    private string|null $moneyType = null;
    private string|null $warehouseId = null;
    private string|null $productId = null;

    /**
        * RecordInventoryProductsBalance constructor.

        * @param array $options
        * @param string|null $options['moneyType']
        * @param string|null $options['warehouseId']
        * @param string|null $options['productId']
     */

    public function __construct(array $options){
        $this->moneyType = $options['moneyType'] ?? null;
        $this->warehouseId = $options['warehouseId'] ?? null;
        $this->productId = $options['productId'] ?? null;
    }

    private function getProductsItems():Collection
    {
        $options = [
            'moneyType' => $this->moneyType,
            'warehouseId' => $this->warehouseId,
            'productId' => $this->productId
        ];

        $query = InventoryProductItem::query();

        if ($options['moneyType'] !== null){
            $query = $query->where('buy_currency', $options['moneyType']);
        }
        if ($options['warehouseId'] !== null){
            $query = $query->where('inventory_warehouse_id', $options['warehouseId']);
        }
        if ($options['productId'] !== null){
            $query = $query->where('inventory_product_id', $options['productId']);
        }



        $items = $query->get()->groupBy(function($productItem){
            return $productItem->buy_currency . $productItem->buy_amount;
        })->map(function($productItems){
            $item = [
                'id' => $productItems->first()->product->id,
                'name' => $productItems->first()->product->name,
                'category' => $productItems->first()->product->category,
                'currency' => $productItems->first()->buy_currency,
                'warehouse' => $productItems->first()->warehouse->name,
                'income_quantity' => $productItems->count(),
                'outcome_quantity' => $productItems->where('status', InventoryProductItemStatus::Sold)->count(),
                'outcome_amount' => $productItems->where('status', InventoryProductItemStatus::Sold)->first()?->buy_amount ?? 0,
                'balance_quantity' => $productItems->count() - $productItems->where('status', InventoryProductItemStatus::Sold)->count(),
                'balance_total_amount' => ($productItems->count() - $productItems->where('status', InventoryProductItemStatus::Sold)->count()) * $productItems->first()->buy_amount
            ];

            return $item;
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
                'warehouseId' => $this->warehouseId,
                'productId' => $this->productId
            ],
        ];
    }
}
