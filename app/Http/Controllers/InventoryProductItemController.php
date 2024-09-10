<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryProductItemRequest;
use App\Http\Requests\UpdateInventoryProductItemRequest;
use App\Models\InventoryProductItem;
use App\Models\User;


class InventoryProductItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function loans(InventoryProductItem $inventoryProductItem)
    {
        $inventoryProductItemLoans = $inventoryProductItem->loans;
        $inventoryProductItemLoans->each(function ($loan) {
            $loan->productItem;
            $loan->productItem->product;
            $loan->loanedBy;
            $loan->loanedTo;
        });
        return response()->json($inventoryProductItemLoans);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInventoryProductItemRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryProductItem $item)
    {
        $item->product;
        $item->warehouse;
        $item->income;
        $item->outcome;
        $item->loans->each(function ($loan) {
            $loan->loanedBy;
            $loan->loanedTo;


            $loan->intercurrences = collect($loan->intercurrences)->map(function ($intercurrence){
                $intercurrence['user'] = User::where('id', $intercurrence['user_id'])->first();
                return $intercurrence;
            });

            $loan->movements = collect($loan->movements)->map(function ($movement){
                $movement['user'] = User::where('id', $movement['user_id'])->first();
                return $movement;
            });

        });

        return response()->json($item);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryProductItemRequest $request, InventoryProductItem $item)
    {
        $validated = $request->validated();

        $item->update($validated);

        return response()->json($item);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryProductItem $item)
    {
        //
    }
}
