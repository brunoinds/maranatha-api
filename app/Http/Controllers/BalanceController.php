<?php

namespace App\Http\Controllers;

use App\Helpers\Enums\BalanceModel;
use App\Http\Requests\StoreBalanceRequest;
use App\Http\Requests\UpdateBalanceRequest;
use App\Models\Balance;
use App\Models\User;
use App\Models\Report;
use App\Support\Assistants\BalanceAssistant;
use App\Helpers\Enums\BalanceType;
use App\Helpers\Toolbox;
use DateTime;
use App\Http\Requests\Balances\AddDirectCreditBalanceRequest;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;



class BalanceController extends Controller
{
    public function index()
    {
        //
    }

    public function store(StoreBalanceRequest $request)
    {
        //
    }

    public function update(UpdateBalanceRequest $request, Balance $balance)
    {
        $validatedData = $request->validated();

        if ($validatedData['receipt_type'] === 'Pdf'){
            $wasSuccessfull = $balance->setReceiptPdfFromBase64($validatedData['receipt_base64']);

            if (!$wasSuccessfull) {
                return response()->json([
                    'error' => [
                        'message' => 'Pdf upload failed',
                    ]
                ], 500);
            }
        }elseif ($validatedData['receipt_type'] === 'Image'){
            $imageValidation = Toolbox::validateImageBase64($validatedData['receipt_base64']);
            if ($imageValidation->isImage){
                if (!$imageValidation->isValid){
                    return response()->json([
                        'error' => [
                            'message' => $imageValidation->message,
                        ]
                    ], 400);
                }

                $wasSuccessfull = $balance->setReceiptImageFromBase64($validatedData['receipt_base64']);

                if (!$wasSuccessfull) {
                    return response()->json([
                        'error' => [
                            'message' => 'Image upload failed',
                        ]
                    ], 500);
                }
            }
        }
        unset($validatedData['receipt_base64']);
        unset($validatedData['receipt_type']);

        $balance->update($validatedData);

        return response()->json([
            'message' => 'Balance updated',
            'balance' => $balance,
        ]);
    }

    public function destroy(Balance $balance)
    {
        if (!auth()->user()->isAdmin()){
            return response()->json([
                'error' => [
                    'message' => 'Only admins can delete balances',
                ]
            ], 403);
        }

        if ($balance->model !== BalanceModel::Direct){
            return response()->json([
                'error' => [
                    'message' => 'Only direct balances can be deleted',
                ]
            ], 403);
        }

        $balance->delete();
        return response()->json(['message' => 'Balance deleted']);
    }

    public function userBalanceYear(User $user, string $year)
    {
        if (!auth()->user()->isAdmin()){
            return response()->json([
                'error' => [
                    'message' => 'Only admins can see balances',
                ]
            ], 403);
        }

        $report = BalanceAssistant::generateUserBalanceByYear($user, (int) $year);
        return response()->json($report);
    }

    public function meBalanceYear(string $year)
    {
        $user = auth()->user();
        $report = BalanceAssistant::generateUserBalanceByYear($user, (int) $year);
        return response()->json($report);
    }

    public function userBalanceAddCredit(User $user, AddDirectCreditBalanceRequest $request)
    {
        //Get data from request:
        $validatedData = $request->validated();

        $receiptBase64 = $validatedData['receipt_base64'];
        $receiptType = $validatedData['receipt_type'];

        unset($validatedData['receipt_base64']);
        unset($validatedData['receipt_type']);

        $balance = Balance::create([
            'user_id' => $user->id,
            'description' => $validatedData['description'],
            'ticket_number' => $validatedData['ticket_number'] ? $validatedData['ticket_number'] : null,
            'report_id' => null,
            'date' => $validatedData['date'],
            'type' => BalanceType::Credit,
            'model' => BalanceModel::Direct,
            'amount' => $validatedData['amount'],
        ]);

        if ($receiptType === 'Pdf'){
            $wasSuccessfull = $balance->setReceiptPdfFromBase64($receiptBase64);

            if (!$wasSuccessfull) {
                return response()->json([
                    'error' => [
                        'message' => 'Pdf upload failed',
                    ]
                ], 500);
            }
        }elseif ($receiptType === 'Image'){
            $imageValidation = Toolbox::validateImageBase64($receiptBase64);
            if ($imageValidation->isImage){
                if (!$imageValidation->isValid){
                    return response()->json([
                        'error' => [
                            'message' => $imageValidation->message,
                        ]
                    ], 400);
                }

                $wasSuccessfull = $balance->setReceiptImageFromBase64($receiptBase64);

                if (!$wasSuccessfull) {
                    return response()->json([
                        'error' => [
                            'message' => 'Image upload failed',
                        ]
                    ], 500);
                }
            }
        }

        return response()->json($balance);
    }

    public function userBalanceRemoveCredit(User $user, Balance $balance)
    {
        $balance->delete();
        return response()->json($balance);
    }

    public function userBalanceAddDebit(User $user)
    {
        $balance = Balance::create([
            'user_id' => $user->id,
            'description' => 'Caja chica',
            'ticket_number' => null,
            'report_id' => null,
            'date' => (new DateTime())->format('c'),
            'type' => BalanceType::Debit,
            'model' => BalanceModel::Direct,
            'amount' => 100,
        ]);
        return response()->json($balance);
    }

    public function userBalanceRemoveDebit(User $user, Balance $balance)
    {
        $balance->delete();
        return response()->json($balance);
    }

    public function getBalancesFromReport(Report $report)
    {
        $balances = Balance::where('report_id', $report->id);

        if ($balances->count() === 0) {
            return response()->json([
                'error' => [
                    'message' => 'No balance found for this report',
                ]
            ], 404);
        }

        return response()->json($balances->get());
    }

    public function getBalanceReceiptFromReport(Report $report)
    {
        $balance = Balance::where('report_id', $report->id)->first();

        if (!$balance) {
            return response()->json([
                'error' => [
                    'message' => 'No balance found for this report',
                ]
            ], 404);
        }

        if (!$balance->hasReceipt()) {
            return [
                'has_receipt' => false,
            ];
        }

        return response()->json([
            'has_receipt' => true,
            'type' => $balance->getReceiptType(),
            'data' => $balance->getReceiptInBase64(),
        ]);
    }

    public function getReceipt(Balance $balance)
    {
        if (!$balance->hasReceipt()) {
            return response()->json([
                'has_receipt' => false,
            ]);
        }

        $data = $balance->getReceiptInBase64();
        return response()->json([
            'has_receipt' => true,
            'data' => $data,
            'type' => $balance->getReceiptType(),
        ]);
    }
}
