<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseRequest;
use App\Http\Requests\UpdateInventoryWarehouseRequest;
use App\Models\InventoryWarehouse;

class InventoryWarehouseController extends Controller
{
    public function index()
    {
        return InventoryWarehouse::all();
    }

    public function listIncomes(InventoryWarehouse $warehouse)
    {
        $incomes = $warehouse->incomes;

        //Do not include ->items from eager loading:
        $incomes->makeHidden('items');

        //Include the value of method amount() as property:
        $incomes->map(function ($income) {
            $income->amount = $income->amount();
            $income->items_count = $income->items->count();
            return $income;
        });

        return response()->json($incomes);
    }

    public function listOutcomes(InventoryWarehouse $warehouse)
    {
        $outcomes = $warehouse->outcomes;

        //Do not include ->items from eager loading:
        $outcomes->makeHidden('items');

        //Include the value of method amount() as property:
        $outcomes->map(function ($outcome) {
            $outcome->amount = $outcome->amount();
            $outcome->items_count = $outcome->items->count();
            return $outcome;
        });

        return response()->json($outcomes);
    }
    public function listLoans(InventoryWarehouse $warehouse)
    {
        $loans = $warehouse->loans;
        $loans->each(function ($loan) {
            $loan->productItem;
            $loan->productItem->product;
            $loan->loanedBy;
            $loan->loanedTo;
        });

        return response()->json($loans->toArray());
    }

    public function listOutcomeRequests(InventoryWarehouse $warehouse)
    {
        $outcomes = $warehouse->outcomeRequests;
        return response()->json($outcomes->toArray());
    }

    public function listProducts(InventoryWarehouse $warehouse)
    {
        $products = $warehouse->products;
        return response()->json($products->toArray());
    }

    public function listStock(InventoryWarehouse $warehouse)
    {
        $stock = $warehouse->stock();
        return response()->json($stock);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryWarehouseRequest $request)
    {
        $validated = $request->validated();
        $warehouse = InventoryWarehouse::create($validated);
        return response()->json(['message' => 'Warehouse created', 'warehouse' => $warehouse->toArray()]);

    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryWarehouse $warehouse)
    {
        return response()->json($warehouse->toArray());
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryWarehouseRequest $request, InventoryWarehouse $warehouse)
    {
        $validated = $request->validated();
        $warehouse->update($validated);

        return response()->json(['message' => 'Warehouse updated', 'warehouse' => $warehouse->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryWarehouse $warehouse)
    {
        $warehouse->delete();
        return response()->json(['message' => 'Warehouse deleted successfully']);
    }
}
