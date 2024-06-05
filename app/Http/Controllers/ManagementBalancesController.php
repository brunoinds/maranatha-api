<?php

namespace App\Http\Controllers;

use App\Support\Assistants\BalanceAssistant;
use App\Support\Generators\Records\Attendances\RecordAttendancesByWorker;
use App\Support\Generators\Records\Jobs\RecordJobsByCosts;
use App\Support\Generators\Records\Attendances\RecordAttendancesByJobs;
use App\Support\Generators\Records\Users\RecordUsersByCosts;
use DateTime;
use App\Models\User;
use App\Support\Cache\RecordsCache;



class ManagementBalancesController extends Controller
{

    public function usersBalances()
    {
        $validatedData = request()->validate([
            'year' => 'required|int',
        ]);


        if (RecordsCache::getRecord('usersBalances', $validatedData)){
            return response()->json([
                'balances' => RecordsCache::getRecord('usersBalances', $validatedData),
                'year' => $validatedData['year'],
                'is_cached' => true
            ]);
        }

        $balances = User::all()->map(function($user) use ($validatedData){
            $year = $validatedData['year'];
            $userBalance = BalanceAssistant::generateUserBalanceByYear($user, $year);

            return [
                'user' => $user,
                'balance' => $userBalance,
            ];
        });

        RecordsCache::storeRecord('usersBalances', $validatedData, $balances->toArray());


        return response()->json([
            'balances' => $balances,
            'year' => $validatedData['year'],
            'is_cached' => false
        ]);
    }
}
