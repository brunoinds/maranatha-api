<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryProductsPackRequest;
use App\Http\Requests\UpdateInventoryProductsPackRequest;
use App\Models\InventoryProductsPack;

class InventoryProductsPackController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(InventoryProductsPack::all());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryProductsPackRequest $request)
    {
        $data = $request->validated();
        $inventoryProductsPack = InventoryProductsPack::create($data);
        return response()->json(['message' => 'Product Pack created', 'inventoryProductsPack' => $inventoryProductsPack->toArray()]);
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryProductsPack $productsPack)
    {
        return response()->json($productsPack);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryProductsPackRequest $request, InventoryProductsPack $productsPack)
    {
        $productsPack->update($request->validated());
        return response()->json(['message' => 'ProductsPack updated', 'productsPack' => $productsPack->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryProductsPack $productsPack)
    {
        $productsPack->delete();
        return response()->json(['message' => 'InventoryProductsPack deleted']);
    }
}
