<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use App\Models\InventoryWarehouseProductItemLoan;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Helpers\Enums\InventoryWarehouseProductItemLoanStatus;
use App\Models\InventoryProduct;
use App\Helpers\Toolbox;

use App\Models\InventoryWarehouseIncome;

class RecordInventoryIncomesLoanables
{

    private DateTime|null $startDate = null;
    private DateTime|null $endDate = null;
    private array|null $warehouseIds = null;


    /**
        * RecordInventoryIncomesLoanables constructor.

        * @param array $options
        * @param DateTime|null $options['startDate']
        * @param DateTime|null $options['endDate']
        * @param string|null $options['warehouseId']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'] ?? null;
        $this->endDate = $options['endDate'] ?? null;
        $this->warehouseIds = $options['warehouseIds'] ?? null;
    }

    private function getIncomes():Collection
    {
        $options = [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'warehouseIds' => $this->warehouseIds,
        ];

        $lines = [];

        // Get loanable items with eager loading and database-level filtering
        $incomes = InventoryWarehouseIncome::select(['id', 'date', 'ticket_number'])
            ->where('date', '>=', $options['startDate'])
            ->where('date', '<=', $options['endDate'])
            ->whereIn('inventory_warehouse_id', $options['warehouseIds'])
            ->with([
                'items' => function($query) {
                    $query->select(['id', 'inventory_warehouse_income_id', 'inventory_product_id', 'buy_amount', 'buy_currency'])
                        ->whereHas('product', function($productQuery) {
                            $productQuery->where('is_loanable', true);
                        });
                },
                'items.product' => function($query) {
                    $query->select(['id', 'name', 'is_loanable']);
                },
                'uncountableItems' => function($query) {
                    $query->select(['id', 'inventory_warehouse_income_id', 'inventory_product_id', 'buy_amount', 'buy_currency'])
                        ->whereHas('product', function($productQuery) {
                            $productQuery->where('is_loanable', true);
                        });
                },
                'uncountableItems.product' => function($query) {
                    $query->select(['id', 'name', 'is_loanable']);
                }
            ])
            ->get();

        $incomes->each(function($income) use (&$lines) {
            // Process loanable items
            $loanableItemsGrouped = $income->items->groupBy(function ($item) {
                return $item->product_id . '|' . $item->buy_currency . '|' . $item->buy_amount;
            });

            $loanableItemsGrouped->each(function($items, $identifier) use (&$lines, $income) {
                $firstItem = $items->first();
                $lines[] = [
                    'income_id' => $income->id,
                    'product_id' => $firstItem->product_id,
                    'product_name' => $firstItem->product->name,
                    'date' => $income->date,
                    'ticket_number' => $income->ticket_number,
                    'quantity' => $items->count(),
                    'buy_amount' => $firstItem->buy_amount,
                    'buy_currency' => $firstItem->buy_currency,
                    'total_amount' => $items->count() * $firstItem->buy_amount,
                ];
            });

            // Process loanable uncountable items
            $loanableUncountableItemsGrouped = $income->uncountableItems->groupBy(function ($item) {
                return $item->product_id . '|' . $item->buy_currency . '|' . $item->buy_amount;
            });

            $loanableUncountableItemsGrouped->each(function($items, $identifier) use (&$lines, $income) {
                $firstItem = $items->first();
                $lines[] = [
                    'income_id' => $income->id,
                    'product_id' => $firstItem->product_id,
                    'product_name' => $firstItem->product->name,
                    'date' => $income->date,
                    'ticket_number' => $income->ticket_number,
                    'quantity' => $items->count(),
                    'buy_amount' => $firstItem->buy_amount,
                    'buy_currency' => $firstItem->buy_currency,
                    'total_amount' => $items->count() * $firstItem->buy_amount,
                ];
            });
        });

        return collect($lines);
    }

    private function createTable():array{
        $items = $this->getIncomes();

        $body = collect($items)->map(function($item){
            return [
                'income_id' => '#00' . $item['income_id'],
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'date' => Carbon::parse($item['date'])->format('d/m/Y'),
                'ticket_number' => $item['ticket_number'],
                'quantity' => $item['quantity'],
                'buy_currency' => $item['buy_currency'],
                'buy_amount' => $item['buy_amount'],
                'total_amount' => $item['total_amount'],
                'unit_price' => Toolbox::moneyFormat($item['buy_amount'], $item['buy_currency']),
                'total_price' => Toolbox::moneyFormat($item['total_amount'], $item['buy_currency']),
            ];
        });

        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'ID de Ingreso',
                    'key' => 'income_id'
                ],
                [
                    'title' => 'Producto',
                    'key' => 'product_name'
                ],
                [
                    'title' => 'Fecha',
                    'key' => 'date'
                ],
                [
                    'title' => 'N° Ticket',
                    'key' => 'ticket_number'
                ],
                [
                    'title' => 'Cantidad',
                    'key' => 'quantity'
                ],
                [
                    'title' => 'Precio Unitario',
                    'key' => 'unit_price'
                ],
                [
                    'title' => 'Precio Total',
                    'key' => 'total_price'
                ]
            ],
            'body' => $body
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'startDate' => $this->startDate,
                'endDate' => $this->endDate,
                'warehouseIds' => $this->warehouseIds,
            ],
        ];
    }
}
