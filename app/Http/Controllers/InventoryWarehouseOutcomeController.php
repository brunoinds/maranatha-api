<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryWarehouseOutcomeRequest;
use App\Http\Requests\UpdateInventoryWarehouseOutcomeRequest;
use App\Models\InventoryWarehouseOutcome;
use App\Models\InventoryProductItem;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Helpers\Enums\InventoryWarehouseOutcomeRequestStatus;
use App\Support\Creators\Inventory\WarehouseOutcomeProducts\WarehouseOutcomeProductsPdfCreator;
use Illuminate\Support\Str;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Support\Cache\DataCache;


class InventoryWarehouseOutcomeController extends Controller
{
    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function resumeAnalisys(InventoryWarehouseOutcome $inventoryWarehouseOutcome)
    {
        $productsIds = $inventoryWarehouseOutcome->items()->groupBy('inventory_product_id')->select('inventory_product_id')->pluck('inventory_product_id')->toArray();

        $productsResume = [];
        foreach ($productsIds as $productId) {
            $itemsChosenToSellBuyCurrenciesFounds = $inventoryWarehouseOutcome->items()->where('inventory_product_id', $productId)
                ->groupBy('buy_currency')
                ->pluck('buy_currency')
                ->flatten()
                ->unique()
                ->toArray();


            $prices = [];
            foreach ($itemsChosenToSellBuyCurrenciesFounds as $buyCurrency) {
                $itemsChosenToSellBuyAmount = $inventoryWarehouseOutcome->items()->where('inventory_product_id', $productId)->where('buy_currency', $buyCurrency)->sum('buy_amount');
                $itemsChosenToSellBuyCount = $inventoryWarehouseOutcome->items()->where('inventory_product_id', $productId)->where('buy_currency', $buyCurrency)->count();

                $prices[] = [
                    'currency' => $buyCurrency,
                    'amount' => $itemsChosenToSellBuyAmount,
                    'count' => $itemsChosenToSellBuyCount,
                ];
            }

            $itemsChosenToSellAggregation = $inventoryWarehouseOutcome->items()->where('inventory_product_id', $productId)
                ->groupBy(['buy_currency', 'buy_amount'])
                ->select('buy_currency', 'buy_amount', DB::raw('COUNT(*) as count'), DB::raw('SUM(buy_amount) as total_buy_amount'))
                ->get()->toArray();

            $productsResume[] = [
                'product_id' => $productId,
                'quantity' => $inventoryWarehouseOutcome->items()->where('inventory_product_id', $productId)->count(),
                'do_loan' => false,
                'items_aggregated' => collect($itemsChosenToSellAggregation)->map(function ($item) {
                    $return = [
                        'currency' => $item['buy_currency'],
                        'unit_amount' => $item['buy_amount'],
                        'count' => $item['count'],
                        'total_amount' => $item['total_buy_amount'],
                    ];
                    return $return;
                })->toArray(),
                'prices' => $prices,
            ];
        }


        $outcomeResume = [
            'products' => $productsResume,
            'summary' => [
                'prices' => (function() use ($productsResume){
                    //Based on each producsResume prices, calculate the total amount to sell, returning an array [{currency: string, amount: number, count: number}]:
                    $prices = [];
                    foreach ($productsResume as $productResume){
                        foreach ($productResume['prices'] as $price){
                            $found = false;
                            foreach ($prices as $index => $priceFound){
                                if ($priceFound['currency'] === $price['currency']){
                                    $prices[$index]['amount'] += $price['amount'];
                                    $prices[$index]['count'] += $price['count'];
                                    $found = true;
                                    break;
                                }
                            }
                            if (!$found){
                                $prices[] = $price;
                            }
                        }
                    }
                    return $prices;
                })()
            ]
        ];

        return response()->json($outcomeResume);
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

        DataCache::clearRecord('warehouseStockList', [$validated['inventory_warehouse_id']]);

        return response()->json(['message' => 'Inventory warehouse outcome created', 'outcome' => $inventoryWarehouseOutcome], 200);
    }

    public function show(InventoryWarehouseOutcome $warehouseOutcome)
    {
        return response()->json($warehouseOutcome, 200);
    }

    public function update(UpdateInventoryWarehouseOutcomeRequest $request, InventoryWarehouseOutcome $warehouseOutcome)
    {
        $validated = $request->validated();
        $warehouseOutcome->update($validated);

        DataCache::clearRecord('warehouseStockList', [$warehouseOutcome->inventory_warehouse_id]);

        return response()->json(['message' => 'Inventory warehouse outcome updated', 'outcome' => $warehouseOutcome], 200);

    }

    public function downloadPDF(InventoryWarehouseOutcome $warehouseOutcome)
    {
        $pdf = WarehouseOutcomeProductsPdfCreator::new($warehouseOutcome);

        $withImages = request()->query('withImages') === 'true' ? true : false;

        $content = $pdf->create(['withImages' => $withImages])->output();

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

    public function destroy(InventoryWarehouseOutcome $warehouseOutcome)
    {
        DataCache::clearRecord('warehouseStockList', [$warehouseOutcome->inventory_warehouse_id]);

        $warehouseOutcome->delete();
        return response()->json(['message' => 'Inventory warehouse outcome deleted'], 200);
    }
}
