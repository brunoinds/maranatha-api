<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseIncomeRequest;
use App\Http\Requests\UpdateInventoryWarehouseIncomeRequest;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryProductItem;
use App\Helpers\Toolbox;
use Illuminate\Support\Facades\Storage;



class InventoryWarehouseIncomeController extends Controller
{
    public function index()
    {
        //
    }

    public function listProductsItems(InventoryWarehouseIncome $inventoryWarehouseIncome)
    {
        return response()->json($inventoryWarehouseIncome->items, 200);
    }

    public function store(StoreInventoryWarehouseIncomeRequest $request)
    {
        $validated = $request->validated();

        if ($validated['image'] !== null){
            $imageValidation = Toolbox::validateImageBase64($validated['image']);
            if ($imageValidation->isImage){
                if (!$imageValidation->isValid){
                    return response()->json([
                        'error' => [
                            'message' => $imageValidation->message,
                        ]
                    ], 400);
                }
            }
        }


        $inventoryWarehouseIncome = InventoryWarehouseIncome::create([
            'description' => $validated['description'],
            'date' => $validated['date'],
            'ticket_type' => $validated['ticket_type'],
            'ticket_number' => $validated['ticket_number'],
            'commerce_number' => $validated['commerce_number'],
            'qrcode_data' => $validated['qrcode_data'],
            'image' => null,
            'currency' => $validated['currency'],
            'job_code' => $validated['job_code'],
            'expense_code' => $validated['expense_code'],
            'inventory_warehouse_id' => $validated['inventory_warehouse_id'],
        ]);

        if ($validated['image'] !== null){
            $wasSuccessfull = $inventoryWarehouseIncome->setImageFromBase64($validated['image']);
            if (!$wasSuccessfull) {
                $inventoryWarehouseIncome->delete();
                return response()->json([
                    'error' => [
                        'message' => 'Image upload failed',
                    ]
                ], 500);
            }
        }

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

    public function showImage(InventoryWarehouseIncome $inventoryWarehouseIncome)
    {
        $imageId = $inventoryWarehouseIncome->image;
        if (!$imageId){
            return response()->json([
                'error' => [
                    'message' => 'Image not uploaded yet',
                ]
            ], 400);
        }

        $path = 'warehouse-incomes/' . $imageId;
        $imageExists = Storage::disk('public')->exists($path);
        if (!$imageExists){
            return response()->json([
                'error' => [
                    'message' => 'Image missing',
                ]
            ], 400);
        }

        $image = Storage::disk('public')->get($path);

        //Send back as base64 encoded image:
        return response()->json(['image' => base64_encode($image)]);
    }

    public function show(InventoryWarehouseIncome $warehouseIncome)
    {
        return response()->json($warehouseIncome, 200);
    }

    public function update(UpdateInventoryWarehouseIncomeRequest $request, InventoryWarehouseIncome $warehouseIncome)
    {
        $validated = $request->validated();

        $imageBase64 = null;
        if ($validated['image'] !== null){
            $imageValidation = Toolbox::validateImageBase64($validated['image']);
            $imageBase64 = $validated['image'];
            unset($validated['image']);
            if ($imageValidation->isImage){
                if (!$imageValidation->isValid){
                    return response()->json([
                        'error' => [
                            'message' => $imageValidation->message,
                        ]
                    ], 400);
                }
            }
        }

        $warehouseIncome->update($validated);
        if ($imageBase64 !== null){
            $warehouseIncome->deleteImage();
            $wasSuccessfull = $warehouseIncome->setImageFromBase64($imageBase64);
            if (!$wasSuccessfull) {
                return response()->json([
                    'error' => [
                        'message' => 'Image upload failed',
                    ]
                ], 500);
            }
        }

        return response()->json(['message' => 'Inventory warehouse income updated', 'income' => $warehouseIncome], 200);
    }

    public function destroy(InventoryWarehouseIncome $warehouseIncome)
    {
        $warehouseIncome->delete();
        return response()->json(['message' => 'Inventory warehouse income deleted'], 200);
    }
}
