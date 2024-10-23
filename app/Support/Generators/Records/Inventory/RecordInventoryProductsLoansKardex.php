<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use App\Models\InventoryWarehouseProductItemLoan;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Helpers\Enums\InventoryWarehouseProductItemLoanStatus;

class RecordInventoryProductsLoansKardex
{

    private DateTime|null $startDate = null;
    private DateTime|null $endDate = null;
    private array|null $warehouseIds = null;
    private string|null $productId = null;
    private array|null $categories = null;


    /**
        * RecordInventoryProductsLoansKardex constructor.

        * @param array $options
        * @param DateTime|null $options['startDate']
        * @param DateTime|null $options['endDate']
        * @param string|null $options['warehouseId']
        * @param string|null $options['productId']
        * @param string|null $options['categories']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'] ?? null;
        $this->endDate = $options['endDate'] ?? null;
        $this->warehouseIds = $options['warehouseIds'] ?? null;
        $this->productId = $options['productId'] ?? null;
        $this->categories = $options['categories'] ?? null;
    }

    private function getKardex():Collection
    {
        $options = [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'warehouseIds' => $this->warehouseIds,
            'productId' => $this->productId,
            'categories' => $this->categories
        ];

        $list = [];


        $query = InventoryWarehouseProductItemLoan::query();

        if ($options['startDate'] !== null){
            $query = $query->where('loaned_at', '>=', $options['startDate']);
        }
        if ($options['endDate'] !== null){
            $query = $query->where('loaned_at', '<=', $options['endDate']);
        }
        if ($options['warehouseIds'] !== null){
            $query = $query->whereIn('inventory_warehouse_id', $options['warehouseIds']);
        }



        $loans = $query->orderBy('loaned_at')->get();

        $productsLoans = $loans->groupBy('inventory_product_item_id')->map(function($productLoans) use ($options){


            if ($options['productId'] || $options['categories']){
                $product = $productLoans->first()->productItem->product;

                if ($options['productId'] !== null && $product->id != $options['productId']){
                    return null;
                }

                if ($options['categories'] !== null && !in_array($product->category, $options['categories'])){
                    return null;
                }
            }


            return (object) [
                'product' => $productLoans->first()->productItem->product,
                'productItem' => $productLoans->first()->productItem,
                'loans' => $productLoans
            ];
        })->filter(function($item){
            return $item !== null;
        });

        $productsLoans->each((function($item) use (&$list, $options){
            $product = $item->product;
            $productItem = $item->productItem;
            $loans = $item->loans;

            if (count($list) > 0){
                //Add empty list item:
                $list[] = [
                    'order' => null,
                    'product' => null,
                    'serial_number' => null,
                    'product_status' => null,
                    'loan' => null,
                    'loaned_by_user_id' => null,
                    'loaned_to_user_id' => null,
                    'loan_status' => null,
                    'loaned_at' => null,
                    'received_at' => null,
                    'returned_at' => null,
                    'confirm_returned_at' => null,
                    'movements' => null,
                    'intercurrences' => null,
                    'warehouse' => null
                ];
            }

            $internalListIndex = 1;

            foreach ($loans as $loan){
                $item = [
                    'order' => $internalListIndex,
                    'product' => $product->name,
                    'serial_number' => $productItem->batch,
                    'product_status' => $productItem->status,
                    'warehouse' => $productItem->warehouse->name,

                    'loan' => '#00' . $loan->id,
                    'loaned_by_user_id' => $loan->loanedBy->name,
                    'loaned_to_user_id' => $loan->loanedTo->name,
                    'loan_status' => $loan->status,
                    'loaned_at' => $loan->loaned_at,
                    'received_at' => $loan->received_at,
                    'returned_at' => $loan->returned_at,
                    'confirm_returned_at' => $loan->confirm_returned_at,
                    'movements' => $loan->movements,
                    'intercurrences' => $loan->intercurrences,
                ];

                if ($internalListIndex > 1){
                    $item['order'] = '';
                    $item['product'] = '';
                    $item['serial_number'] = '';
                    $item['product_status'] = '';
                    $item['warehouse'] = '';
                }

                $list[] = $item;
                $internalListIndex++;
            }
        }));

        return collect($list);
    }

    private function createTable():array{
        $items = $this->getKardex();

        $body = collect($items)->map(function($item){
            return [
                'order' => $item['order'] ?? '',
                'product' => $item['product'] ?? '',
                'serial_number' => $item['serial_number'] ?? '',
                'product_status' => $item['product_status'] ? InventoryProductItemStatus::getDescription($item['product_status']->value) : '',
                'loan' => $item['loan'] ?? '',
                'loaned_by_user_id' => $item['loaned_by_user_id'] ?? '',
                'loaned_to_user_id' => $item['loaned_to_user_id'] ?? '',
                'loan_status' => $item['loan_status'] ? InventoryWarehouseProductItemLoanStatus::getDescription($item['loan_status']) : '',
                'loaned_at' => $item['loaned_at'] ? Carbon::parse($item['loaned_at'])->format('d/m/Y H:i:s') : '',
                'received_at' => $item['received_at'] ? Carbon::parse($item['received_at'])->format('d/m/Y H:i:s') : '',
                'returned_at' => $item['returned_at'] ? Carbon::parse($item['returned_at'])->format('d/m/Y H:i:s') : '',
                'confirm_returned_at' => $item['confirm_returned_at'] ? Carbon::parse($item['confirm_returned_at'])->format('d/m/Y H:i:s') : '',
                'movements' => $item['movements'] ? implode(', ' , array_map(function($movement){
                    return '[Fecha: ' . Carbon::parse($movement['date'])->format('d/m/Y H:i:s') . ' | Job: ' . $movement['job_code'] . ' | Expense: ' . $movement['expense_code'] . ' | Descripción: ' . $movement['description'] . ']';
                }, $item['movements'])) : '',
                'intercurrences' => $item['intercurrences'] ? implode(', ' , array_map(function($intercurrence){
                    return '[Fecha: ' . Carbon::parse($intercurrence['date'])->format('d/m/Y H:i:s') . ' | Descripción: ' . $intercurrence['description'] . ']';
                }, $item['intercurrences'])) : '',
                'warehouse' => $item['warehouse'] ?? ''
            ];
        });

        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'N°',
                    'key' => 'order'
                ],
                [
                    'title' => 'Producto',
                    'key' => 'product'
                ],
                [
                    'title' => 'S/N',
                    'key' => 'serial_number'
                ],
                [
                    'title' => 'Estado de producto',
                    'key' => 'product_status'
                ],
                [
                    'title' => 'Almacén',
                    'key' => 'warehouse'
                ],
                [
                    'title' => 'Préstamo',
                    'key' => 'loan'
                ],

                [
                    'title' => 'Prestado en',
                    'key' => 'loaned_at'
                ],
                [
                    'title' => 'Prestado para',
                    'key' => 'loaned_to_user_id'
                ],
                [
                    'title' => 'Prestado por',
                    'key' => 'loaned_by_user_id'
                ],
                [
                    'title' => 'Estado de préstamo',
                    'key' => 'loan_status'
                ],
                [
                    'title' => 'Recibido en',
                    'key' => 'received_at'
                ],
                [
                    'title' => 'Devuelto en',
                    'key' => 'returned_at'
                ],
                [
                    'title' => 'Devolución recibida en',
                    'key' => 'confirm_returned_at'
                ],
                [
                    'title' => 'Movimientos',
                    'key' => 'movements'
                ],
                [
                    'title' => 'Intercurrencias',
                    'key' => 'intercurrences'
                ]
            ],
            'body' => $body,
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'warehouseIds' => $this->warehouseIds,
                'productId' => $this->productId,
                'categories' => $this->categories
            ],
        ];
    }
}
