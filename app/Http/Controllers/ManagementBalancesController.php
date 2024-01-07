<?php

namespace App\Http\Controllers;

use App\Support\Assistants\BalanceAssistant;
use App\Support\Generators\Records\Attendances\RecordAttendancesByWorker;
use App\Support\Generators\Records\Jobs\RecordJobsByCosts;
use App\Support\Generators\Records\Attendances\RecordAttendancesByJobs;
use App\Support\Generators\Records\Users\RecordUsersByCosts;
use DateTime;
use App\Models\User;


class ManagementBalancesController extends Controller
{

    public function usersBalances()
    {
        $validatedData = request()->validate([
            'year' => 'required|int',
        ]);


        $balances = User::all()->map(function($user) use ($validatedData){
            $year = $validatedData['year'];
            $userBalance = BalanceAssistant::generateUserBalanceByYear($user, $year);

            return [
                'user' => $user,
                'balance' => $userBalance,
            ];
        });

        
        return response()->json([
            'balances' => $balances,
            'year' => $validatedData['year'],
        ]);
    }
}
