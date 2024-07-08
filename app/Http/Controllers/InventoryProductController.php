<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryProductRequest;
use App\Http\Requests\UpdateInventoryProductRequest;
use App\Models\InventoryProduct;
use Illuminate\Http\Request;
use  App\Support\Search\BingImages\BingImageSearch;

class InventoryProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return InventoryProduct::all();
    }


    public function queryImageSearch()
    {
        $validated = request()->validate([
            'query' => 'required|string'
        ]);

        $query = $validated['query'];

        $response = BingImageSearch::search($query);

        return response()->json($response);
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
    public function update(UpdateInventoryProductRequest $request, InventoryProduct $product)
    {
        $validated = $request->validated();
        $product->update($validated);

        return response()->json(['message' => 'Product updated', 'product' => $product->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryProduct $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
