<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryProductRequest;
use App\Http\Requests\UpdateInventoryProductRequest;
use App\Models\InventoryProduct;

class InventoryProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return InventoryProduct::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryProductRequest $request)
    {
        $validated = $request->validated();
        $product = InventoryProduct::create($validated);

        return response()->json(['message' => 'Product created', 'product' => $product->toArray()]);
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryProduct $inventoryProduct)
    {
        return response()->json($inventoryProduct->toArray());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryProductRequest $request, InventoryProduct $inventoryProduct)
    {
        $validated = $request->validated();
        $inventoryProduct->update($validated);

        return response()->json(['message' => 'Product updated', 'product' => $inventoryProduct->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryProduct $inventoryProduct)
    {
        $inventoryProduct->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
