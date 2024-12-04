<?php

namespace App\Http\Controllers;

use App\Helpers\Enums\InventoryWarehouseProductItemLoanStatus;
use App\Http\Requests\StoreInventoryWarehouseProductItemLoanBulkRequest;
use App\Http\Requests\UpdateInventoryWarehouseProductItemLoanRequest;
use App\Models\User;
use App\Models\InventoryWarehouse;
use App\Models\InventoryWarehouseProductItemLoan;
use Illuminate\Http\Request;
use App\Helpers\Toolbox;

use Illuminate\Support\Str;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Helpers\Enums\InventoryWarehouseOutcomeRequestStatus;
use OneSignal;
use App\Support\Cache\DataCache;

class InventoryWarehouseProductItemLoanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeBulk(StoreInventoryWarehouseProductItemLoanBulkRequest $request)
    {
        $validated = $request->validated();

        $loans = [];
        foreach ($validated['products_items_ids'] as $productItemId) {
            $loan = InventoryWarehouseProductItemLoan::create([
                'loaned_by_user_id' => auth()->id(),
                'loaned_to_user_id' => $validated['loaned_to_user_id'],
                'status' => InventoryWarehouseProductItemLoanStatus::SendingToLoan,
                'movements' => [
                    [
                        'id' => Str::uuid(),
                        'user_id' => auth()->id(),
                        'to_user_id' => auth()->id(),
                        'job_code' => $validated['job_code'],
                        'expense_code' => $validated['expense_code'],
                        'date' => now(),
                        'description' => 'Envío de producto a préstamo',
                    ]
                ],
                'intercurrences' => [],
                'inventory_product_item_id' => $productItemId,
                'inventory_warehouse_id' => $validated['inventory_warehouse_id'],
                'job_code' => $validated['job_code'],
                'expense_code' => $validated['expense_code'],
                'inventory_warehouse_outcome_request_id' => isset($validated['inventory_warehouse_outcome_request_id']) ? $validated['inventory_warehouse_outcome_request_id'] : null,
            ]);

            $loan->doSendToLoan();
            $loans[] = $loan;
        }

        if (isset($validated['inventory_warehouse_outcome_request_id']) && $validated['inventory_warehouse_outcome_request_id'] !== null){
            $inventoryWarehouseOutcomeRequest = InventoryWarehouseOutcomeRequest::find($validated['inventory_warehouse_outcome_request_id']);
            $inventoryWarehouseOutcomeRequest->changeStatus(InventoryWarehouseOutcomeRequestStatus::Dispatched);
        }


        DataCache::clearRecord('warehouseStockList', [$validated['inventory_warehouse_id']]);


        return response()->json(['message' => 'Préstamo de productos realizado con éxito', 'loans' => $loans], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryWarehouseProductItemLoan $warehouseLoan)
    {
        $warehouseLoan->productItem;
        $warehouseLoan->productItem?->product;
        $warehouseLoan->loanedBy;
        $warehouseLoan->loanedTo;

        $warehouseLoan->intercurrences = collect($warehouseLoan->intercurrences)->map(function ($intercurrence){
            $intercurrence['user'] = User::where('id', $intercurrence['user_id'])->first();
            return $intercurrence;
        });

        $warehouseLoan->movements = collect($warehouseLoan->movements)->map(function ($movement){
            $movement['user'] = User::where('id', $movement['user_id'])->first();
            $movement['to_user'] = User::where('id', $movement['to_user_id'])->first();
            return $movement;
        });

        return response()->json($warehouseLoan->toArray());
    }

    public function listMeLoans()
    {
        $loans = InventoryWarehouseProductItemLoan::where('loaned_to_user_id', auth()->id())->get();
        $loans->each(function ($loan) {
            $loan->productItem;
            $loan->productItem?->product;
            $loan->loanedBy;
            $loan->loanedTo;
        });
        return response()->json($loans);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInventoryWarehouseProductItemLoanRequest $request, InventoryWarehouseProductItemLoan $warehouseLoan)
    {
        $validated = $request->validated();

        $notificationUrlOnUserReports = env('APP_WEB_URL') . '/inventory/loans/' . $warehouseLoan->id;

        $notifications = [];


        //Progressive status:
        if ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::SendingToLoan->value && $validated['status'] == InventoryWarehouseProductItemLoanStatus::OnLoan->value){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->doReceivedFromWarehouse();
        }elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::OnLoan->value && $validated['status'] == InventoryWarehouseProductItemLoanStatus::ReturningToWarehouse->value){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->doReturnToWarehouse();
            $notifications[] = [
                'headings' => '↩️ Devolución de préstamo',
                'message' => $warehouseLoan->loanedTo->name . ' ha devuelto el producto "' . $warehouseLoan->productItem->product->name . '" a tu almacén. Confirma la recepción del producto en la aplicación para finalizar el proceso.',
                'users_ids' => $warehouseLoan->warehouse->owners,
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports
                ]
            ];
        }elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::ReturningToWarehouse->value && $validated['status'] == InventoryWarehouseProductItemLoanStatus::Returned->value){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->doConfirmReturnedToWarehouse();

            $notifications[] = [
                'headings' => '✅ Devolución de producto confirmada',
                'message' => 'El producto "' . $warehouseLoan->productItem->product->name . '" ha sido devuelto y confirmado en el almacén.',
                'users_ids' => [$warehouseLoan->loanedTo->id],
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports
                ]
            ];
        }

        //Regressive status:
        elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::OnLoan->value && $validated['status'] == InventoryWarehouseProductItemLoanStatus::SendingToLoan->value){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->undoReceivedFromWarehouse();
        }elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::ReturningToWarehouse->value && $validated['status'] == InventoryWarehouseProductItemLoanStatus::OnLoan->value){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->undoReturnToWarehouse();
        }elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::Returned->value && $validated['status'] == InventoryWarehouseProductItemLoanStatus::OnLoan->value){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->undoConfirmReturnedToWarehouse();
        }



        //Check new movements:
        if (count($validated['movements']) > count($warehouseLoan->movements)){
            $newMovements = array_slice($validated['movements'], count($warehouseLoan->movements));
            foreach ($newMovements as $newMovement){

                $validated['loaned_to_user_id'] = $newMovement['to_user_id'];
                $validated['job_code'] = $newMovement['job_code'];
                $validated['expense_code'] = $newMovement['expense_code'];


                $notifications[] = [
                    'headings' => '↔️ Movimiento de producto prestado',
                    'message' => $warehouseLoan->loanedTo->name . ' ha realizado un movimiento con "' . $warehouseLoan->productItem->product->name . '".',
                    'users_ids' => $warehouseLoan->warehouse->owners,
                    'data' => [
                        'deepLink' => $notificationUrlOnUserReports
                    ]
                ];
            }
        }

        //Check new intercurrences:
        if (count($validated['intercurrences']) > count($warehouseLoan->intercurrences)){
            $newIntercurrences = array_slice($validated['intercurrences'], count($warehouseLoan->intercurrences));
            foreach ($newIntercurrences as $newIntercurrence){
                $notifications[] = [
                    'headings' => '‼️ Nueva intercurrencia registrada',
                    'message' => $warehouseLoan->loanedTo->name . ' ha registrado una intercurrencia con producto "' . $warehouseLoan->productItem->product->name . '".',
                    'users_ids' => $warehouseLoan->warehouse->owners,
                    'data' => [
                        'deepLink' => $notificationUrlOnUserReports
                    ]
                ];
            }
        }

        $warehouseLoan->update($validated);

        DataCache::clearRecord('warehouseStockList', [$warehouseLoan->inventory_warehouse_id]);


        if (env('APP_ENV') !== 'local'){
            foreach ($notifications as $notification) {
                foreach ($notification['users_ids'] as $userId) {
                    OneSignal::sendNotificationToExternalUser(
                        headings: $notification['headings'],
                        message: $notification['message'],
                        userId: Toolbox::getOneSignalUserId($userId),
                        data: $notification['data']
                    );
                }
            }
        }

        return response()->json(['message' => 'Préstamo de producto actualizado', 'loan' => $warehouseLoan], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InventoryWarehouseProductItemLoan $warehouseLoan)
    {
        //
    }
}
