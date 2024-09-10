<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Models\InventoryProductItem;
use App\Models\InventoryProduct;
use App\Models\InventoryWarehouse;

use App\Helpers\Toolbox;


class RecordInventoryProductsStock
{

    private string|null $warehouseId = null;
    private string|null $productId = null;
    private  string|null $brand = null;
    private  string|null $category = null;
    private  string|null $status = null;

    /**
        * RecordInventoryProductsStock constructor.

        * @param array $options
        * @param string|null $options['category']
        * @param string|null $options['brand']
        * @param string|null $options['status']
        * @param string|null $options['warehouseId']
        * @param string|null $options['productId']
     */



    public function __construct(array $options){
        $this->warehouseId = $options['warehouseId'] ?? null;
        $this->productId = $options['productId'] ?? null;
        $this->brand = $options['brand'] ?? null;
        $this->category = $options['category'] ?? null;
        $this->status = $options['status'] ?? null;
    }

    private function getProductsItems():Collection
    {
        $options = [
            'warehouseId' => $this->warehouseId,
            'productId' => $this->productId,
            'brand' => $this->brand,
            'category' => $this->category,
            'status' => $this->status
        ];

        $query = InventoryProduct::query();

        if ($options['productId'] !== null){
            $query = $query->where('inventory_product_id', $options['productId']);
        }
        if ($options['brand'] !== null){
            $query = $query->where('brand', $options['brand']);
        }
        if ($options['category'] !== null){
            $query = $query->where('category', $options['category']);
        }
        if ($options['status'] !== null){
            $query = $query->where('status', $options['status']);
        }


        $products = $query->get()->map(function($product) use ($options){
            $productsItemsQuery = InventoryProductItem::query()
                ->where('inventory_product_id', $product->id);

            if ($options['warehouseId'] !== null){
                $productsItemsQuery = $productsItemsQuery->where('inventory_warehouse_id', $options['warehouseId']);
            }

            $allInStockProductsCount = $productsItemsQuery->where('status', InventoryProductItemStatus::InStock)->count();
            $allLoanedProductsCount = $productsItemsQuery->where('status', InventoryProductItemStatus::Loaned)->count();
            $allInRepairProductsCount = $productsItemsQuery->where('status', InventoryProductItemStatus::InRepair)->count();
            $allWriteOffProductsCount = $productsItemsQuery->where('status', InventoryProductItemStatus::WriteOff)->count();
            $allSoldProductsCount = $productsItemsQuery->where('status', InventoryProductItemStatus::Sold)->count();

            $productItemData = [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'brand' => $product->brand,
                'quantity_total' => $productsItemsQuery->count(),
                'quantity_in_stock' => $allInStockProductsCount,
                'quantity_loaned' => $allLoanedProductsCount,
                'quantity_in_repair' => $allInRepairProductsCount,
                'quantity_write_off' => $allWriteOffProductsCount,
                'quantity_sold' => $allSoldProductsCount,
            ];
            return $productItemData;
        });


        return collect($products);
    }

    private function createTable():array{
        $items = $this->getProductsItems();

        $body = array_column($items->toArray(), null);


        $headers = [
            [
                'title' => 'ID',
                'key' => 'id'
            ],
            [
                'title' => 'Producto',
                'key' => 'name'
            ],
            [
                'title' => 'Marca',
                'key' => 'brand'
            ],
            [
                'title' => 'Categoría',
                'key' => 'category'
            ],
            [
                'title' => 'En Stock',
                'key' => 'quantity_in_stock',
            ],
            [
                'title' => 'Prestado',
                'key' => 'quantity_loaned'
            ],
            [
                'title' => 'En Reparación',
                'key' => 'quantity_in_repair'
            ],
            [
                'title' => 'Dado de Baja',
                'key' => 'quantity_write_off'
            ],
            [
                'title' => 'Vendido',
                'key' => 'quantity_sold'
            ]
        ];



        $headers[] = [
            'title' => 'Total',
            'key' => 'quantity_total'
        ];

        return [
            'headers' => $headers,
            'body' => $body,
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'warehouseId' => $this->warehouseId,
                'productId' => $this->productId,
                'brand' => $this->brand,
                'category' => $this->category,
                'status' => $this->status
            ],
        ];
    }
}
