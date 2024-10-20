<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseIncomeRequest;
use App\Http\Requests\UpdateInventoryWarehouseIncomeRequest;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryProductItem;
use App\Models\InventoryProduct;

use App\Helpers\Toolbox;
use App\Support\Toolbox\TString;
use Illuminate\Support\Facades\Storage;
use App\Support\Cache\DataCache;



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

    public function listProductsState(InventoryWarehouseIncome $inventoryWarehouseIncome)
    {
        $inventoryProductIds = $inventoryWarehouseIncome->items()
            ->groupBy('inventory_product_id')
            ->pluck('inventory_product_id')
            ->flatten()
            ->unique()
            ->toArray();


        $products = [];
        foreach ($inventoryProductIds as $productId){
            $product = InventoryProduct::find($productId);
            $quantity = $inventoryWarehouseIncome->items()->where('inventory_product_id', $productId)->count();
            $amount = $inventoryWarehouseIncome->items()->where('inventory_product_id', $productId)->first()->buy_amount;


            $sellings = [
                'in_stock' => [
                    'count' => $inventoryWarehouseIncome->items()->where('inventory_product_id', $productId)->whereNull('inventory_warehouse_outcome_id')->count()
                ],
                'sold' => [
                    'count' => $inventoryWarehouseIncome->items()->where('inventory_product_id', $productId)->whereNotNull('inventory_warehouse_outcome_id')->count(),
                    'details' => (function() use ($inventoryWarehouseIncome, $productId){
                        $outcomesIds = $inventoryWarehouseIncome->items()
                            ->where('inventory_product_id', $productId)
                            ->whereNotNull('inventory_warehouse_outcome_id')
                            ->groupBy('inventory_warehouse_outcome_id')
                            ->pluck('inventory_warehouse_outcome_id')
                            ->flatten()
                            ->unique()
                            ->toArray();

                        $outcomes = [];
                        foreach ($outcomesIds as $outcomeId){
                            $count = $inventoryWarehouseIncome->items()->where('inventory_product_id', $productId)->where('inventory_warehouse_outcome_id', $outcomeId)->count();
                            $outcomes[] = [
                                'outcome_id' => $outcomeId,
                                'count' => $count,
                            ];
                        }
                        return $outcomes;
                    })()
                ]
            ];

            if (is_null($product)){
                continue;
            }

            $products[] = [
                'product' => $product,
                'quantity' => $quantity,
                'amount' => $amount,
                'sellings' => $sellings,
            ];
        }

        return response()->json($products, 200);
    }

    public function store(StoreInventoryWarehouseIncomeRequest $request)
    {
        $validated = $request->validated();

        if (isset($validated['image']) && $validated['image'] !== null){
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

        if (isset($validated['image']) && $validated['image'] !== null){
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
                    'batch' => TString::generateRandomBatch(),
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

        DataCache::clearRecord('warehouseStockList', [$validated['inventory_warehouse_id']]);

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
        if (isset($validated['image']) && $validated['image'] !== null){
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

        if ($validated['currency'] !== $warehouseIncome->currency){
            InventoryProductItem::where('inventory_warehouse_income_id', $warehouseIncome->id)
                ->update([
                    'buy_currency' => $validated['currency'],
                    'sell_currency' => $validated['currency'],
                ]);
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

        DataCache::clearRecord('warehouseStockList', [$warehouseIncome->inventory_warehouse_id]);


        $resuméInfo = [];

        if (isset($validated['products_changes'])){
            foreach ($validated['products_changes'] as $productChange) {
                if (isset($productChange['amount']) && $productChange['amount']){
                    $warehouseIncome->items()
                        ->where('inventory_product_id', $productChange['product_id'])
                        ->update([
                            'buy_amount' => $productChange['amount'],
                            'sell_amount' => $productChange['amount'],
                        ]);
                }

                if (isset($productChange['replaces_by_product_id']) && $productChange['replaces_by_product_id']){
                    $warehouseIncome->items()
                        ->where('inventory_product_id', $productChange['product_id'])
                        ->update([
                            'inventory_product_id' => $productChange['replaces_by_product_id'],
                        ]);
                }


                if (isset($productChange['quantity']) && !is_null($productChange['quantity']) && $warehouseIncome->items()->where('inventory_product_id', $productChange['product_id'])->count() !== (int) $productChange['quantity']){
                    $items = $warehouseIncome->items()->where('inventory_product_id', $productChange['product_id']);

                    if ($items->count() < $productChange['quantity']){
                        //Create new items:
                        $itemsCountToCreate = $productChange['quantity'] - $items->count();

                        $lastOrder = InventoryProductItem::orderBy('order', 'desc')->first();
                        $lastOrder = $lastOrder ? $lastOrder->order : -1;

                        $i = 0;
                        while ($i < $itemsCountToCreate) {
                            $buyAmount = (isset($productChange['amount'])) ? $productChange['amount'] : $items->first()->buy_amount;
                            $sellAmount = (isset($productChange['amount'])) ? $productChange['amount'] : $items->first()->sell_amount;
                            $productId = (isset($productChange['replaces_by_product_id'])) ? $productChange['replaces_by_product_id'] : $productChange['product_id'];

                            InventoryProductItem::create([
                                'batch' => TString::generateRandomBatch(),
                                'order' => $lastOrder + $i + 1,
                                'buy_amount' => (float) $buyAmount,
                                'sell_amount' => (float) $sellAmount,
                                'buy_currency' => $validated['currency'],
                                'sell_currency' => $validated['currency'],
                                'inventory_product_id' => $productId,
                                'inventory_warehouse_id' => $warehouseIncome->inventory_warehouse_id,
                                'inventory_warehouse_income_id' => $warehouseIncome->id,
                            ]);
                            $i++;
                        }
                    }elseif ($items->count() > $productChange['quantity']){
                        //Delete items:
                        $itemsCountToDelete = $items->count() - $productChange['quantity'];
                        //Delete the last items, based on the order column:

                        $itemsToDelete = $items->orderBy('order', 'desc')->take($itemsCountToDelete)->get();
                        foreach ($itemsToDelete as $itemToDelete){
                            $itemToDelete->delete();
                        }
                    }
                }
            }
        }

        return response()->json(['message' => 'Inventory warehouse income updated', 'income' => $warehouseIncome], 200);
    }

    public function destroy(InventoryWarehouseIncome $warehouseIncome)
    {
        DataCache::clearRecord('warehouseStockList', [$warehouseIncome->inventory_warehouse_id]);

        $warehouseIncome->delete();
        return response()->json(['message' => 'Inventory warehouse income deleted'], 200);
    }
}
