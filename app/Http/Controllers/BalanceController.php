<?php

namespace App\Http\Controllers;

use App\Helpers\Enums\BalanceModel;
use App\Http\Requests\StoreBalanceRequest;
use App\Http\Requests\UpdateBalanceRequest;
use App\Models\Balance;
use App\Models\User;
use App\Support\Assistants\BalanceAssistant;
use App\Helpers\Enums\BalanceType;
use DateTime;
use App\Http\Requests\Balances\AddDirectCreditBalanceRequest;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;


class BalanceController extends Controller
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
    public function store(StoreBalanceRequest $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBalanceRequest $request, Balance $balance)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Balance $balance)
    {
        //
    }


    public function userBalance(User $user)
    {
        $report = BalanceAssistant::generateUserBalanceByYear($user, 2024);
        return response()->json($report);
    }
    public function meBalance()
    {
        $user = auth()->user();
        $report = BalanceAssistant::generateUserBalanceByYear($user, 2024);
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

        //Validate receipt_base64:
        if (!is_null($validatedData['receipt_base64']) && mb_strlen($validatedData['receipt_base64']) > 40){
            //Has image to upload
            $maxSizeInBytes = 2048 * 1024; // 2MB
            $base64Image = $validatedData['receipt_base64'];

            $imageSize = (fn() => strlen(base64_decode($base64Image)))();
            if ($imageSize > $maxSizeInBytes) {
                $balance->delete();
                return response()->json([
                    'error' => [
                        'message' => "Image exceeds max size (maximum $maxSizeInBytes bytes)",
                    ]
                ], 400);
            }


            try{
                $imageResource = Image::make($base64Image);
                $imageEncoded = $imageResource->encode('png')->getEncoded();
            } catch(\Exception $e){
                $balance->delete();
                return response()->json([
                    'error' => [
                        'message' => 'Invalid image data',
                        'details' => $e->getMessage()
                    ]
                ], 400);
            }

            $imageId = $balance->id;

            $path = 'balances/' . $imageId;

            $wasSuccessfull = Storage::disk('public')->put($path, $imageEncoded);

            if (!$wasSuccessfull) {
                return response()->json([
                    'error' => [
                        'message' => 'Image upload failed',
                    ]
                ], 500);
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
}
