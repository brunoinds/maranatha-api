<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryProductRequest;
use App\Http\Requests\UpdateInventoryProductRequest;
use App\Models\InventoryProduct;
use App\Helpers\Enums\InventoryProductUnit;
use Illuminate\Http\Request;
use  App\Support\Search\GoogleImages\GoogleImageSearch;
use App\Support\Cache\DataCache;

class InventoryProductController extends Controller
{
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

        $response = GoogleImageSearch::search($query);

        return response()->json($response);
    }

    public function store(StoreInventoryProductRequest $request)
    {
        $validated = $request->validated();
        $product = InventoryProduct::create($validated);
        $product->clearStockCaches();
        return response()->json(['message' => 'Product created', 'product' => $product->toArray()]);
    }

    public function show(InventoryProduct $inventoryProduct)
    {
        return response()->json($inventoryProduct->toArray());
    }

    public function update(UpdateInventoryProductRequest $request, InventoryProduct $product)
    {
        $previousProductUnit = $product->unit;

        $validated = $request->validated();

        if ($previousProductUnit !== $validated['unit']) {
            $product->convertItemsUnitNature(InventoryProductUnit::getNature(InventoryProductUnit::from($validated['unit'])));
        }

        $product->update($validated);


        $product->clearStockCaches();
        return response()->json(['message' => 'Product updated', 'product' => $product->toArray()]);
    }

    public function destroy(InventoryProduct $product)
    {
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }
}
