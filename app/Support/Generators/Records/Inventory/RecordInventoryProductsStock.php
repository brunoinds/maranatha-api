<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;
use App\Models\InventoryProduct;
use App\Helpers\Toolbox;


class RecordInventoryProductsStock
{

    private array|null $warehouseIds = null;
    private string|null $productId = null;
    private  string|null $brand = null;
    private  array|null $categories = null;
    private array|null $subCategories = null;
    private  string|null $status = null;

    /**
        * RecordInventoryProductsStock constructor.

        * @param array $options
        * @param string|null $options['categories']
        * @param string|null $options['subCategories']
        * @param string|null $options['brand']
        * @param string|null $options['status']
        * @param string|null $options['warehouseIds']
        * @param string|null $options['productId']
     */



    public function __construct(array $options){
        $this->warehouseIds = $options['warehouseIds'] ?? null;
        $this->productId = $options['productId'] ?? null;
        $this->brand = $options['brand'] ?? null;
        $this->categories = $options['categories'] ?? null;
        $this->subCategories = $options['subCategories'] ?? null;
        $this->status = $options['status'] ?? null;
    }

    private function getProductsItems():Collection
    {
        $options = [
            'warehouseIds' => $this->warehouseIds,
            'productId' => $this->productId,
            'brand' => $this->brand,
            'categories' => $this->categories,
            'subCategories' => $this->subCategories,
            'status' => $this->status
        ];

        $query = InventoryProduct::query();

        if ($options['productId'] !== null){
            $query = $query->where('inventory_product_id', $options['productId']);
        }
        if ($options['brand'] !== null){
            $query = $query->where('brand', $options['brand']);
        }
        if ($options['categories'] !== null){
            $query = $query->whereIn('category', $options['categories']);
        }
        if ($options['subCategories'] !== null){
            $query = $query->whereIn('sub_category', $options['subCategories']);
        }
        if ($options['status'] !== null){
            $query = $query->where('status', $options['status']);
        }


        $countableProducts = $query->get()->map(function($product) use ($options){
            $productsItemsQuery = InventoryProductItem::query()
                ->where('inventory_product_id', $product->id);

            if ($options['warehouseIds'] !== null){
                $productsItemsQuery = $productsItemsQuery->whereIn('inventory_warehouse_id', $options['warehouseIds']);
            }

            if ($productsItemsQuery->count() === 0){
                return null;
            }

            $allInStockProductsCount = (clone $productsItemsQuery)->where('status', InventoryProductItemStatus::InStock)->count();
            $allLoanedProductsCount = (clone $productsItemsQuery)->where('status', InventoryProductItemStatus::Loaned)->count();
            $allInRepairProductsCount = (clone $productsItemsQuery)->where('status', InventoryProductItemStatus::InRepair)->count();
            $allWriteOffProductsCount = (clone $productsItemsQuery)->where('status', InventoryProductItemStatus::WriteOff)->count();
            $allSoldProductsCount = (clone $productsItemsQuery)->where('status', InventoryProductItemStatus::Sold)->count();


            $productItemData = [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'sub_category' => $product->sub_category,
                'brand' => $product->brand,
                'quantity_total' => $productsItemsQuery->count(),
                'quantity_in_stock' => $allInStockProductsCount,
                'quantity_loaned' => $allLoanedProductsCount,
                'quantity_in_repair' => $allInRepairProductsCount,
                'quantity_write_off' => $allWriteOffProductsCount,
                'quantity_sold' => $allSoldProductsCount,
            ];
            return $productItemData;
        })->filter(function($product){
            return $product !== null;
        });

        $uncountableProducts = $query->get()->map(function($product) use ($options){
            $productsItemsQuery = InventoryProductItemUncountable::query()
                ->where('inventory_product_id', $product->id);

            if ($options['warehouseIds'] !== null){
                $productsItemsQuery = $productsItemsQuery->whereIn('inventory_warehouse_id', $options['warehouseIds']);
            }

            if ($productsItemsQuery->count() === 0){
                return null;
            }



            $productItemData = [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category,
                'sub_category' => $product->sub_category,
                'brand' => $product->brand,
                'quantity_total' => $productsItemsQuery->sum('quantity_inserted'),
                'quantity_in_stock' => $productsItemsQuery->sum('quantity_remaining'),
                'quantity_loaned' => 0,
                'quantity_in_repair' => 0,
                'quantity_write_off' => 0,
                'quantity_sold' => $productsItemsQuery->sum('quantity_used'),
            ];
            return $productItemData;
        })->filter(function($product){
            return $product !== null;
        });

        return collect($countableProducts)->merge(collect($uncountableProducts));
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
                'title' => 'Sub Categoría',
                'key' => 'sub_category'
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
                'warehouseIds' => $this->warehouseIds,
                'productId' => $this->productId,
                'brand' => $this->brand,
                'categories' => $this->categories,
                'subCategories' => $this->subCategories,
                'status' => $this->status
            ],
        ];
    }
}
