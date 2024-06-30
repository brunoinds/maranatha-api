<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseIncomeRequest;
use App\Http\Requests\UpdateInventoryWarehouseIncomeRequest;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryProductItem;

class InventoryWarehouseIncomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    public function listProductsItems(InventoryWarehouseIncome $inventoryWarehouseIncome)
    {
        return response()->json(['products' => $inventoryWarehouseIncome->products], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryWarehouseIncomeRequest $request)
    {
        $validated = $request->validated();

        $inventoryWarehouseIncome = InventoryWarehouseIncome::create([
            'description' => $validated['description'],
            'date' => $validated['date'],
            'ticket_number' => $validated['ticket_number'],
            'commerce_number' => $validated['commerce_number'],
            'qrcode_data' => $validated['qrcode_data'],
            'image' => $validated['image'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'job_code' => $validated['job_code'],
            'expense_code' => $validated['expense_code'],
            'inventory_warehouse_id' => $validated['inventory_warehouse_id'],
        ]);

        foreach ($validated['products'] as $product) {
            $lastOrder = InventoryProductItem::orderBy('order', 'desc')->first();
            $lastOrder = $lastOrder ? $lastOrder->order : -1;

            $i = 0;
            while ($i < $product['quantity']) {
                InventoryProductItem::create([
                    'order' => $lastOrder + $i + 1,
                    'buy_amount' => (float) $product['amount'],
                    'sell_amount' => (float)  $product['amount'],
                    'buy_currency' => $validated['currency'],
                    'sell_currency' => $validated['currency'],
                    'inventory_product_id' => $product['product_id'],
                    'inventory_warehouse_id' => $validated['inventory_warehouse_id'],
                    'inventory_warehouse_income_id' => $inventoryWarehouseIncome->id,
                ]);
                $i++;
            }
        }
        return response()->json(['message' => 'Inventory warehouse income created', 'income' => $inventoryWarehouseIncome], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryWarehouseIncome $inventoryWarehouseIncome)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InventoryWarehouseIncome $inventoryWarehouseIncome)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryWarehouseIncomeRequest $request, InventoryWarehouseIncome $inventoryWarehouseIncome)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryWarehouseIncome $inventoryWarehouseIncome)
    {
        //
    }
}
