<?php

namespace App\Support\Assistants;

use App\Helpers\Enums\BalanceModel;
use App\Helpers\Enums\BalanceType;
use App\Models\Balance;

class BalanceAssistant{
    public static function generateUserBalanceByYear(User $user, number $year):array{
        $balances = Balance::all()->where('user_id', $user->id)->where('date', '>=', $year . '-01-01')->where('date', '<=', $year . '-12-31')->orderBy('date', 'asc');;
        $totalCredit = 0;
        $totalDebit = 0;
        $items = [];

        foreach($balances as $balance){
            if ($balance->type === BalanceType::Credit){
                $totalCredit += $balance->amount;
            }elseif ($balance->type === BalanceType::Debit){
                $totalDebit += $balance->amount;
            }


            $items[] = [
                'id' => $balance->id,
                'description' => $balance->description,
                'ticket_number' => $balance->ticket_number,
                'report_id' => $balance->report_id,
                'date' => $balance->date,
                'type' => $balance->type,
                'model' => $balance->model,
                'amount' => $balance->amount,
                'balance_here' => $totalCredit - $totalDebit,
            ];
        }
        $total = $totalCredit - $totalDebit;

        $pettyCash = [
            'given_amount' => (function() use ($year, $user){
                $directCredits = Balance::all()->where('model', BalanceModel::Direct)->where('type', BalanceType::Credit)->where('user_id', $user->id)->where('date', '>=', $year . '-01-01')->where('date', '<=', $year . '-12-31');
                $directDebits = Balance::all()->where('model', BalanceModel::Direct)->where('type', BalanceType::Debit)->where('user_id', $user->id)->where('date', '>=', $year . '-01-01')->where('date', '<=', $year . '-12-31');
                
                $total = 0;
                foreach($directCredits as $directCredit){
                    $total += $balance->amount;
                }
                foreach($directDebits as $directDebit){
                    $total -= $balance->amount;
                }
                return $total;
            })(),
            'usage_percentage' => (function() use ($year, $user){
                $directCredits = Balance::all()->where('model', BalanceModel::Direct)->where('type', BalanceType::Credit)->where('user_id', $user->id)->where('date', '>=', $year . '-01-01')->where('date', '<=', $year . '-12-31');
                $directDebits = Balance::all()->where('model', BalanceModel::Direct)->where('type', BalanceType::Debit)->where('user_id', $user->id)->where('date', '>=', $year . '-01-01')->where('date', '<=', $year . '-12-31');
                
                $totalDirects = 0;
                foreach($directCredits as $directCredit){
                    $totalDirects += $balance->amount;
                }
                foreach($directDebits as $directDebit){
                    $totalDirects -= $balance->amount;
                }


                $expensesDebits = Balance::all()->where('model', BalanceModel::Expense)->where('type', BalanceType::Debit)->where('user_id', $user->id)->where('date', '>=', $year . '-01-01')->where('date', '<=', $year . '-12-31');
                $restitutionsCredits = Balance::all()->where('model', BalanceModel::Restitution)->where('type', BalanceType::Credit)->where('user_id', $user->id)->where('date', '>=', $year . '-01-01')->where('date', '<=', $year . '-12-31');
                
                $totalExpensesAndRestitutions = 0;
                foreach($expensesDebits as $expensesDebit){
                    $totalExpensesAndRestitutions -= $expensesDebit->amount;
                }
                foreach($restitutionsCredits as $restitutionsCredit){
                    $totalExpensesAndRestitutions += $restitutionsCredit->amount;
                }

                if ($totalDirects === 0){
                    return 0;
                }

                $percentage = $totalExpensesAndRestitutions * 100 / $totalDirects;
                return $percentage;
            })(),
        ];
        
        return [
            'totals' => [
                'credit' => $totalCredit,
                'debit' => $totalDebit,
                'balance' => $total
            ],
            'items' => $items,
            'petty_cash' => [
                'given_amount' => $pettyCash['given_amount'],
                'usage_percentage' => $pettyCash['usage_percentage'],
                'balance' => $total,
                'reports' => [
                    'pendings' => [
                        'dollars' => [
                            'amount',
                            'count'
                        ],
                        'soles' => [
                            'amount',
                            'count'
                        ],
                    ],
                    'approved' => [
                        'dollars' => [
                            'amount',
                            'count'
                        ],
                        'soles' => [
                            'amount',
                            'count'
                        ],
                    ],
                ],
            ]
        ];
    }

    public static function createBalanceExpenseFromReport(Report $report, float|null $amountOverride = null):Balance{
        $balance = new Balance();
        $balance->description = 'Reporte "' . $report->title . '"';
        $balance->user_id = $report->user_id;
        $balance->ticket_number = null;
        $balance->report_id = $report->id;
        $balance->date = $report->submitted_at;
        $balance->type = BalanceType::Debit;
        $balance->model = BalanceModel::Expense;
        $balance->amount = $report->amount();
        $balance->save();
        return $balance;
    }
    public static function createBalanceRestitutionFromReport(Report  $report, float|null $amountOverride = null):Balance{
        //Check if has a balance with report_id and model = Restitution:
        $balance = Balance::all()->where('report_id', $report->id)->where('model', BalanceModel::Restitution)->first();
        if ($balance){
            throw new Exception('There is already a balance with this report_id and model = Restitution');
        }
        $balance = new Balance();
        $balance->description = 'Reposición de reporte "' . $report->title . '"';
        $balance->user_id = $report->user_id;
        $balance->ticket_number = null;
        $balance->report_id = $report->id;
        $balance->date = $report->approved_at;
        $balance->type = BalanceType::Credit;
        $balance->model = BalanceModel::Restitution;
        $balance->amount = $report->amount();
        $balance->save();
        return $balance;
    }
}