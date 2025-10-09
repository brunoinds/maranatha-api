<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryProduct;
use App\Models\InventoryWarehouseOutcome;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Helpers\Toolbox;
use App\Models\Job;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;



class RecordInventoryProductsKardex
{

    private DateTime|null $startDate = null;
    private DateTime|null $endDate = null;
    private string|null $moneyType = null;
    private array|null $warehouseIds = null;
    private string|null $expenseCode = null;
    private string|null $jobCode = null;
    private string|null $productId = null;
    private array|null $categories = null;
    private array|null $subCategories = null;
    /* private array|null $country = null;
    private array|null $jobRegion = null; */



    /**
        * RecordInventoryProductsKardex constructor.

        * @param array $options
        * @param DateTime|null $options['startDate']
        * @param DateTime|null $options['endDate']
        * @param string|null $options['moneyType']
        * @param string|null $options['warehouseIds']
        * @param string|null $options['expenseCode']
        * @param string|null $options['jobCode']
        * @param string|null $options['productId']
        * @param string|null $options['categories']
        * @param string|null $options['subCategories']


     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'] ?? null;
        $this->endDate = $options['endDate'] ?? null;
        $this->moneyType = $options['moneyType'] ?? null;
        $this->warehouseIds = $options['warehouseIds'] ?? null;
        $this->expenseCode = $options['expenseCode'] ?? null;
        $this->jobCode = $options['jobCode'] ?? null;
        $this->productId = $options['productId'] ?? null;
        $this->categories = $options['categories'] ?? null;
        $this->subCategories = $options['subCategories'] ?? null;
        /* $this->country = $options['country'] ?? null;
        $this->jobRegion = $options['jobRegion'] ?? null; */
    }

    private function getKardex():Collection
    {
        $options = [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'moneyType' => $this->moneyType,
            'warehouseIds' => $this->warehouseIds,
            'expenseCode' => $this->expenseCode,
            'jobCode' => $this->jobCode,
            'productId' => $this->productId,
            'categories' => $this->categories,
            'subCategories' => $this->subCategories,
            /* 'country' => $this->country,
            'jobRegion' => $this->jobRegion */
        ];

        $productsLines = collect([]);

        $countableProductsIds = InventoryProductItem::whereIn('inventory_warehouse_id', $options['warehouseIds'])->where('buy_currency', $options['moneyType'])->distinct('inventory_product_id')->select('inventory_product_id')->pluck('inventory_product_id');
        $uncountableProductsIds = InventoryProductItemUncountable::whereIn('inventory_warehouse_id', $options['warehouseIds'])->where('buy_currency', $options['moneyType'])->distinct('inventory_product_id')->select('inventory_product_id')->pluck('inventory_product_id');

        $productsIds = $countableProductsIds->merge($uncountableProductsIds);


        InventoryProduct::whereIn('id', $productsIds)->where('is_loanable', false)->get()->each(function($product) use ($options, &$productsLines){
            $lines = [];
            $previousBalance = $this->getProductPreviousBalance($product);

            $balance = [
                'quantity' => $previousBalance['quantity'],
                'total_amount' => $previousBalance['total_amount'],
            ];

            $incomes = InventoryWarehouseIncome::whereIn('inventory_warehouse_id', $options['warehouseIds'])
                ->where('date', '>=', $this->startDate)
                ->where(function($query) use ($product, $options) {
                    $query->whereHas('items', function($q) use ($product, $options){
                        $q->where('inventory_product_id', $product->id)
                        ->where('buy_currency', $options['moneyType']);
                    })
                    ->orWhereHas('uncountableItems', function($q) use ($product, $options){
                        $q->where('inventory_product_id', $product->id)
                        ->where('buy_currency', $options['moneyType']);
                    });
                })->get();

            $outcomes = InventoryWarehouseOutcome::whereIn('inventory_warehouse_id', $options['warehouseIds'])
                ->where('date', '>=', $this->startDate)
                ->where(function($query) use ($product, $options) {
                    $query->whereHas('items', function($q) use ($product, $options){
                        $q->where('inventory_product_id', $product->id)
                        ->where('buy_currency', $options['moneyType']);
                    })
                    ->orWhereHas('relationUncountableItems', function($q) use ($product, $options){
                        $q->where('inventory_product_id', $product->id)
                        ->where('buy_currency', $options['moneyType']);
                    });
                })->get();




            $datesOrganized = collect([]);

            $incomes->each(function($income) use (&$datesOrganized){
                if ($datesOrganized->contains($income->date)){
                    return;
                }
                $datesOrganized->push($income->date);
            });

            $outcomes->each(function($outcome) use ($datesOrganized){
                if ($datesOrganized->contains($outcome->date)){
                    return;
                }
                $datesOrganized->push($outcome->date);
            });

            $datesOrganized->sort();

            $datesOrganized->each(function($date) use ($incomes, $outcomes, $product, &$lines, &$balance, $options){
                $incomes = $incomes->where('date', $date);
                $outcomes = $outcomes->where('date', $date);

                $incomes->each(function($income) use ($product, &$lines, &$balance, $options){
                    $line = [
                        'product' => $product,
                        'transaction' => $income,
                        'operation_type' => 'Income',
                        'quantity' => 0,
                        'unit_price' => 0,
                        'total_price' => 0,
                        'balance_quantity' => $balance['quantity'],
                        'balance_total_amount' => $balance['total_amount'],
                    ];

                    if ($product->unitNature() === 'Integer'){
                        //Countable products:
                        $products = $income->items()->where('inventory_product_id', $product->id)
                            ->where('date', '>=', $this->startDate)
                            ->where('buy_currency', $options['moneyType'])
                            ->get();
                        $line['quantity'] = $products->count();
                        $line['unit_price'] = $products->first()->buy_amount;
                        $line['total_price'] = $products->count() * $products->first()->buy_amount;

                        if ($line['quantity'] > 0){
                            $balance['quantity'] += $line['quantity'];
                            $balance['total_amount'] += $line['total_price'];

                            $line['balance_quantity'] = $balance['quantity'];
                            $line['balance_total_amount'] = $balance['total_amount'];

                            $lines[] = $line;
                        }
                    }else{
                        //Uncountable products:
                        $products = $income->uncountableItems()->where('inventory_product_id', $product->id)
                            ->where('date', '>=', $this->startDate)
                            ->where('buy_currency', $options['moneyType'])
                            ->get();
                        $totalQuantity = $products->sum('quantity_inserted');
                        $line['quantity'] = $totalQuantity;
                        $line['unit_price'] = $totalQuantity > 0 ? $products->first()->buy_amount / $totalQuantity : 0;
                        $line['total_price'] = $products->first()->buy_amount;
                        if ($line['quantity'] > 0){
                            $balance['quantity'] += $line['quantity'];
                            $balance['total_amount'] += $line['total_price'];

                            $line['balance_quantity'] = $balance['quantity'];
                            $line['balance_total_amount'] = $balance['total_amount'];

                            $lines[] = $line;
                        }
                    }
                });

                $outcomes->each(function($outcome) use ($product, &$lines, &$balance, $options){
                    $line = [
                        'product' => $product,
                        'transaction' => $outcome,
                        'operation_type' => 'Outcome',
                        'quantity' => 0,
                        'unit_price' => 0,
                        'total_price' => 0,
                        'balance_quantity' => $balance['quantity'],
                        'balance_total_amount' => $balance['total_amount'],
                    ];

                    if ($product->unitNature() === 'Integer'){
                        //Countable products:
                        $products = $outcome->items()->where('inventory_product_id', $product->id)
                            ->where('date', '>=', $this->startDate)
                            ->where('buy_currency', $options['moneyType'])
                            ->get();
                        $line['quantity'] = $products->count();
                        $line['unit_price'] = $products->first()->sell_amount;
                        $line['total_price'] = $products->count() * $products->first()->sell_amount;

                        if ($line['quantity'] > 0){
                            $balance['quantity'] -= $line['quantity'];
                            $balance['total_amount'] -= $line['total_price'];

                            $line['balance_quantity'] = $balance['quantity'];
                            $line['balance_total_amount'] = $balance['total_amount'];

                            $lines[] = $line;
                        }
                    }else{
                        //Uncountable products:
                        $outcome->relationUncountableItems()->where('inventory_product_id', $product->id)->where('date', '>=', $this->startDate)->where('buy_currency', $options['moneyType'])->each(function ($uncountableItem) use ($outcome, &$line, $options) {
                            if (!isset($uncountableItem->outcomes_details[$outcome->id])) {
                                return;
                            }
                            $outcomeDetails = $uncountableItem->outcomes_details[$outcome->id];


                            $line['total_price'] += $outcomeDetails['sell_amount'];
                            $line['unit_price'] = $outcomeDetails['sell_amount'] / $outcomeDetails['quantity'];
                            $line['quantity'] += $outcomeDetails['quantity'];
                        });

                        if ($line['quantity'] > 0){
                            $line['unit_price'] = $line['total_price'] / $line['quantity'];
                            $balance['quantity'] -= $line['quantity'];
                            $balance['total_amount'] -= $line['total_price'];

                            $line['balance_quantity'] = $balance['quantity'];
                            $line['balance_total_amount'] = $balance['total_amount'];

                            $lines[] = $line;
                        }
                    }
                });
            });

            $list = collect($lines)->map(function($line, $index){
                $item = [
                    'order' => $index + 1,
                    'product_name' => $line['product']?->name,
                    'product_category' => $line['product']?->category,
                    'product_sub_category' => $line['product']?->sub_category,
                    'operation_type' => $line['operation_type'],
                    'date' => $line['transaction']->date,
                    'transaction_number' => $line['transaction']->ticket_number,
                    'job_code' => $line['transaction']?->job?->code,
                    'job_name' => $line['transaction']?->job?->name,
                    'expense_code' => $line['transaction']?->expense?->code,
                    'expense_name' => $line['transaction']?->expense?->name,
                    'warehouse_name' => $line['transaction']?->warehouse?->name,

                    'income_quantity' => '',
                    'income_unit_price' => '',
                    'income_total' => '',

                    'outcome_quantity' => '',
                    'outcome_unit_price' => '',
                    'outcome_total' => '',

                    'balance_quantity' => $line['balance_quantity'],
                    'balance_unit_price' => $line['balance_quantity'] > 0 ? $line['balance_total_amount'] / $line['balance_quantity'] : 0,
                    'balance_total' => $line['balance_total_amount'],
                ];

                if ($line['operation_type'] === 'Income'){
                    $item['income_quantity'] = $line['quantity'];
                    $item['income_unit_price'] = $line['unit_price'];
                    $item['income_total'] = $line['total_price'];
                }else{
                    $item['outcome_quantity'] = $line['quantity'];
                    $item['outcome_unit_price'] = $line['unit_price'];
                    $item['outcome_total'] = $line['total_price'];
                }

                return $item;
            });






            if ($productsLines->count() > 0){
                $productsLines = $productsLines->push([
                    'order' => '',
                    'product_name' => '',
                    'product_category' => '',
                    'product_sub_category' => '',
                    'operation_type' => '',
                    'date' => '',
                    'transaction_number' => '',
                    'job_code' => '',
                    'job_name' => '',
                    'expense_code' => '',
                    'expense_name' => '',
                    'warehouse_name' => '',

                    'income_quantity' => '',
                    'income_unit_price' => '',
                    'income_total' => '',

                    'outcome_quantity' => '',
                    'outcome_unit_price' => '',
                    'outcome_total' => '',

                    'balance_quantity' => '',
                    'balance_unit_price' => '',
                    'balance_total' => '',
                ]);
            }


            $productsLines = $productsLines->push([
                'order' => '0',
                'product_name' => $product->name,
                'product_category' => $product->category,
                'product_sub_category' => $product->sub_category,
                'operation_type' => 'Stock',
                'date' => '',
                'transaction_number' => '',
                'job_code' => '',
                'job_name' => '',
                'expense_code' => '',
                'expense_name' => '',
                'warehouse_name' => '',

                'income_quantity' => '',
                'income_unit_price' => '',
                'income_total' => '',

                'outcome_quantity' => '',
                'outcome_unit_price' => '',
                'outcome_total' => '',

                'balance_quantity' => $previousBalance['quantity'],
                'balance_unit_price' => $previousBalance['quantity'] > 0 ? $previousBalance['total_amount'] / $previousBalance['quantity'] : 0,
                'balance_total' => $previousBalance['total_amount'],
            ]);

            $productsLines = $productsLines->merge($list);
        });




        return collect($productsLines);
    }

    private function getProductPreviousBalance(InventoryProduct $product)
    {
        $incomes = InventoryWarehouseIncome::whereIn('inventory_warehouse_id', $this->warehouseIds)
            ->where('date', '<', $this->startDate)
            ->where(function($query) use ($product) {
                $query->whereHas('items', function($q) use ($product){
                    $q->where('inventory_product_id', $product->id)
                    ->where('buy_currency', $this->moneyType);
                })
                ->orWhereHas('uncountableItems', function($q) use ($product){
                    $q->where('inventory_product_id', $product->id)
                    ->where('buy_currency', $this->moneyType);
                });
            })->get();

        $outcomes = InventoryWarehouseOutcome::whereIn('inventory_warehouse_id', $this->warehouseIds)
            ->where('date', '<', $this->startDate)
            ->where(function($query) use ($product) {
                $query->whereHas('items', function($q) use ($product){
                    $q->where('inventory_product_id', $product->id)
                    ->where('buy_currency', $this->moneyType);
                })
                ->orWhereHas('relationUncountableItems', function($q) use ($product){
                    $q->where('inventory_product_id', $product->id)
                    ->where('buy_currency', $this->moneyType);
                });
            })->get();


        if ($product->unitNature() === 'Integer'){
            $balance = [
                'quantity' => 0,
                'total_amount' => 0
            ];



            $incomes->each(function($income) use ($product, &$balance){
                $incomeQuery =  $income->items()->where('inventory_product_id', $product->id)
                    ->where('buy_currency', $this->moneyType);
                $balance['quantity'] += $incomeQuery->count();
                $balance['total_amount'] += $incomeQuery->sum('buy_amount');
            });

            $outcomes->each(function($outcome) use ($product, &$balance){
                $outcomeQuery = $outcome->items()->where('inventory_product_id', $product->id)
                    ->where('buy_currency', $this->moneyType);
                $balance['quantity'] -= $outcomeQuery->count();
                $balance['total_amount'] -= $outcomeQuery->sum('sell_amount');
            });

            return $balance;
        }else{
            $balance = [
                'quantity' => 0,
                'total_amount' => 0
            ];


            $incomes->each(function($income) use ($product, &$balance){
                $incomeQuery = $income->uncountableItems()->where('inventory_product_id', $product->id)
                    ->where('buy_currency', $this->moneyType);
                $balance['quantity'] += $incomeQuery->sum('quantity_inserted');
                $balance['total_amount'] += $incomeQuery->sum('buy_amount');
            });

            $outcomes->each(function($outcome) use ($product, &$balance){
                $outcome->relationUncountableItems()->where('inventory_product_id', $product->id)
                    ->where('buy_currency', $this->moneyType)->get()->each(function($uncountableItem) use ($outcome, &$balance) {
                        if (!isset($uncountableItem->outcomes_details[$outcome->id])) {
                            return;
                        }
                        $outcomeDetails = $uncountableItem->outcomes_details[$outcome->id];
                        $balance['quantity'] -= $outcomeDetails['quantity'];
                        $balance['total_amount'] -= $outcomeDetails['sell_amount'];
                    });
            });

            return $balance;
        }
    }

    private function createTable():array{
        $items = $this->getKardex();
        $body = collect($items)->map(function($item){
            return $item;
        });

        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'Producto',
                    'key' => 'product_name'
                ],
                [
                    'title' => 'Categoría',
                    'key' => 'product_category'
                ],
                [
                    'title' => 'Sub Categoría',
                    'key' => 'product_sub_category'
                ],
                [
                    'title' => 'N°',
                    'key' => 'order'
                ],
                [
                    'title' => 'Operación',
                    'key' => 'operation_type'
                ],
                [
                    'title' => 'Fecha',
                    'key' => 'date'
                ],
                [
                    'title' => 'Número de ticket',
                    'key' => 'transaction_number'
                ],
                [
                    'title' => 'Job Code',
                    'key' => 'job_code'
                ],
                [
                    'title' => 'Job',
                    'key' => 'job_name'
                ],
                [
                    'title' => 'Expense Code',
                    'key' => 'expense_code'
                ],
                [
                    'title' => 'Expense',
                    'key' => 'expense_name'
                ],
                [
                    'title' => 'Almacén',
                    'key' => 'warehouse_name'
                ],
                [
                    'title' => 'Ingresos: Cantidad',
                    'key' => 'income_quantity'
                ],
                [
                    'title' => 'Ingresos: Precio unitario',
                    'key' => 'income_unit_price'
                ],
                [
                    'title' => 'Ingresos: Monto Total',
                    'key' => 'income_total'
                ],
                [
                    'title' => 'Salidas: Cantidad',
                    'key' => 'outcome_quantity'
                ],
                [
                    'title' => 'Salidas: Precio unitario',
                    'key' => 'outcome_unit_price'
                ],
                [
                    'title' => 'Salidas: Monto Total',
                    'key' => 'outcome_total'
                ],
                [
                    'title' => 'Saldo: Cantidad',
                    'key' => 'balance_quantity'
                ],
                [
                    'title' => 'Saldo: Precio Unitario',
                    'key' => 'balance_unit_price'
                ],
                [
                    'title' => 'Saldo: Monto Total',
                    'key' => 'balance_total'
                ],
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
                'moneyType' => $this->moneyType,
                'warehouseIds' => $this->warehouseIds,
                'expenseCode' => $this->expenseCode,
                'jobCode' => $this->jobCode,
                'productId' => $this->productId,
                'categories' => $this->categories,
                'subCategories' => $this->subCategories
            ],
        ];
    }
}
