<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseOutcomeRequest;
use App\Http\Requests\UpdateInventoryWarehouseOutcomeRequest;
use App\Models\InventoryWarehouseOutcome;
use App\Models\InventoryProductItem;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Helpers\Enums\InventoryWarehouseOutcomeRequestStatus;
use App\Support\Creators\Inventory\WarehouseOutcome\PDFCreator;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Illuminate\Support\Facades\Storage;
class InventoryWarehouseOutcomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    public function listProductsItems(InventoryWarehouseOutcome $inventoryWarehouseOutcome)
    {
        return response()->json($inventoryWarehouseOutcome->items, 200);
    }


    public function store(StoreInventoryWarehouseOutcomeRequest $request)
    {
        $validated = $request->validated();


        //Check if every product_item listed exists and it's status is 'InStock':
        foreach ($validated['products_items'] as $productItem){
            $product = InventoryProductItem::find($productItem['id']);
            if (!$product || $product->status !== InventoryProductItemStatus::InStock){
                return response()->json([
                    'message' => 'Product item not found or not in stock',
                    'product_item_id' => $productItem['id'],
                    'status' => $product ? 'OutOfStock' : 'NotFound'
                ], 404);
            }
        }


        $inventoryWarehouseOutcome = InventoryWarehouseOutcome::create([
            'description' => $validated['description'],
            'date' => $validated['date'],
            'job_code' => $validated['job_code'],
            'expense_code' => $validated['expense_code'],
            'user_id' => auth()->id(),
            'inventory_warehouse_id' => $validated['inventory_warehouse_id'],
        ]);

        foreach ($validated['products_items'] as $productItem) {
            $productItem = InventoryProductItem::find($productItem['id']);
            $productItem->status = InventoryProductItemStatus::Sold->value;
            $productItem->sell_amount = $productItem->buy_amount;
            $productItem->sell_currency = $productItem->buy_currency;

            $productItem->inventory_warehouse_outcome_id = $inventoryWarehouseOutcome->id;
            $productItem->save();
        }


        if (isset($validated['outcome_request_id']) && $validated['outcome_request_id'] !== null){
            $inventoryWarehouseOutcomeRequest = InventoryWarehouseOutcomeRequest::find($validated['outcome_request_id']);
            $inventoryWarehouseOutcomeRequest->inventory_warehouse_outcome_id = $inventoryWarehouseOutcome->id;
            $inventoryWarehouseOutcomeRequest->save();
            $inventoryWarehouseOutcomeRequest->changeStatus(InventoryWarehouseOutcomeRequestStatus::Dispatched);
        }

        return response()->json(['message' => 'Inventory warehouse outcome created', 'outcome' => $inventoryWarehouseOutcome], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryWarehouseOutcome $warehouseOutcome)
    {
        return response()->json($warehouseOutcome, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryWarehouseOutcomeRequest $request, InventoryWarehouseOutcome $warehouseOutcome)
    {
        $validated = $request->validated();
        $warehouseOutcome->update($validated);
        return response()->json(['message' => 'Inventory warehouse outcome updated', 'outcome' => $warehouseOutcome], 200);

    }

    public function downloadPDF(InventoryWarehouseOutcome $warehouseOutcome)
    {
        //ddh($warehouseOutcome->warehouse->country);
        $pdf = PDFCreator::new($warehouseOutcome);
        $content = $pdf->create([])->output();

        $documentName = Str::slug($warehouseOutcome->id, '-') . '.pdf';


        $temporaryDirectory = (new TemporaryDirectory())->create();
        $tempPath = $temporaryDirectory->path($documentName);

        file_put_contents($tempPath, $content);

        return response()
            ->download($tempPath, $documentName, [
                'Content-Encoding' => 'base64',
                'Content-Length' => filesize($tempPath),
            ])->deleteFileAfterSend(true);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryWarehouseOutcome $warehouseOutcome)
    {
        $warehouseOutcome->delete();
        return response()->json(['message' => 'Inventory warehouse outcome deleted'], 200);
    }
}
