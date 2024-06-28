<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseRequest;
use App\Http\Requests\UpdateInventoryWarehouseRequest;
use App\Models\InventoryWarehouse;

class InventoryWarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return InventoryWarehouse::all();
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
    public function update(UpdateInventoryWarehouseRequest $request, InventoryWarehouse $inventoryWarehouse)
    {
        $validated = $request->validated();
        $inventoryWarehouse->update($validated);

        return response()->json(['message' => 'Warehouse updated', 'warehouse' => $inventoryWarehouse->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryWarehouse $inventoryWarehouse)
    {
        $inventoryWarehouse->delete();
        return response()->json(['message' => 'Warehouse deleted successfully']);
    }
}
