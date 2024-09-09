<?php

namespace App\Http\Controllers;

use App\Helpers\Enums\InventoryWarehouseProductItemLoanStatus;
use App\Http\Requests\StoreInventoryWarehouseProductItemLoanBulkRequest;
use App\Http\Requests\UpdateInventoryWarehouseProductItemLoanRequest;
use App\Models\InventoryWarehouse;
use App\Models\InventoryWarehouseProductItemLoan;
use Illuminate\Http\Request;
use App\Helpers\Toolbox;

use Illuminate\Support\Str;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Helpers\Enums\InventoryWarehouseOutcomeRequestStatus;
use OneSignal;

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
                        'job_code' => $validated['job_code'],
                        'expense_code' => $validated['expense_code'],
                        'date' => now(),
                        'description' => 'Envío de producto a préstamo',
                    ]
                ],
                'intercurrences' => [],
                'inventory_product_item_id' => $productItemId,
                'inventory_warehouse_id' => $validated['inventory_warehouse_id'],
                'inventory_warehouse_outcome_request_id' => isset($validated['inventory_warehouse_outcome_request_id']) ? $validated['inventory_warehouse_outcome_request_id'] : null,
            ]);

            $loan->doSendToLoan();
            $loans[] = $loan;
        }

        if (isset($validated['inventory_warehouse_outcome_request_id']) && $validated['inventory_warehouse_outcome_request_id'] !== null){
            $inventoryWarehouseOutcomeRequest = InventoryWarehouseOutcomeRequest::find($validated['inventory_warehouse_outcome_request_id']);
            $inventoryWarehouseOutcomeRequest->changeStatus(InventoryWarehouseOutcomeRequestStatus::Dispatched);
        }

        return response()->json(['message' => 'Préstamo de productos realizado con éxito', 'loans' => $loans], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(InventoryWarehouseProductItemLoan $warehouseLoan)
    {
        $warehouseLoan->productItem;
        $warehouseLoan->productItem->product;
        $warehouseLoan->loanedBy;
        $warehouseLoan->loanedTo;

        return response()->json($warehouseLoan->toArray());
    }

    public function listMeLoans()
    {
        $loans = InventoryWarehouseProductItemLoan::where('loaned_to_user_id', auth()->id())->get();
        $loans->each(function ($loan) {
            $loan->productItem;
            $loan->productItem->product;
            $loan->loanedBy;
            $loan->loanedTo;
        });
        return response()->json($loans);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InventoryWarehouseProductItemLoan $warehouseLoan)
    {
        //
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
        if ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::SendingToLoan && $validated['status'] == InventoryWarehouseProductItemLoanStatus::OnLoan){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->doReceivedFromWarehouse();
        }elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::OnLoan && $validated['status'] == InventoryWarehouseProductItemLoanStatus::ReturningToWarehouse){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->doReturnToWarehouse();

            $notifications[] = [
                'headings' => '📦 Devolución de producto',
                'message' => $warehouseLoan->loanedTo->name . " ha devuelto un producto a tu almacén. Confirma la recepción del producto en la aplicación para finalizar el proceso.",
                'users_ids' => $warehouseLoan->warehouse->owners,
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports
                ]
            ];
        }elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::ReturningToWarehouse && $validated['status'] == InventoryWarehouseProductItemLoanStatus::Returned){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->doConfirmReturnedToWarehouse();

            $notifications[] = [
                'headings' => '✅ Devolución de producto confirmada',
                'message' => "El producto ha sido devuelto y confirmado en el almacén.",
                'users_ids' => $warehouseLoan->loanedTo->id,
                'data' => [
                    'deepLink' => $notificationUrlOnUserReports
                ]
            ];
        }

        //Regressive status:
        elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::OnLoan && $validated['status'] == InventoryWarehouseProductItemLoanStatus::SendingToLoan){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->undoReceivedFromWarehouse();
        }elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::ReturningToWarehouse && $validated['status'] == InventoryWarehouseProductItemLoanStatus::OnLoan){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->undoReturnToWarehouse();
        }elseif ($warehouseLoan->status == InventoryWarehouseProductItemLoanStatus::Returned && $validated['status'] == InventoryWarehouseProductItemLoanStatus::OnLoan){
            unset($validated['status'], $validated['loaned_at'], $validated['received_at'], $validated['returned_at'], $validated['confirm_returned_at']);
            $warehouseLoan->undoConfirmReturnedToWarehouse();
        }



        //Check new movements:
        if (count($validated['movements']) > count($warehouseLoan->movements)){
            $newMovements = array_slice($validated['movements'], count($warehouseLoan->movements));
            foreach ($newMovements as $newMovement){
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
