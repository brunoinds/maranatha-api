<?php

namespace App\Support\Assistants;

use App\Helpers\Enums\BalanceModel;
use App\Helpers\Enums\BalanceType;
use App\Helpers\Enums\ReportStatus;
use App\Models\Balance;
use App\Models\Report;
use App\Models\User;
use App\Helpers\Enums\MoneyType;
use Carbon\Carbon;
use DateTime;
use DateTimeInterface;

class BalanceAssistant{
    public static function generateUserBalanceByYear(User $user, int $year):array{
        $timeSpan = [
            'start' => DateTime::createFromFormat('Y-m-d\\TH:i:sP', $year . '-01-01T00:00:00-05:00'),
            'end' => DateTime::createFromFormat('Y-m-d\\TH:i:sP', $year . '-12-31T23:59:59-05:00'),
        ];
        return BalanceAssistant::generateUserBalanceByTimeRange($user, $timeSpan['start'], $timeSpan['end']);
    }
    public static function generateUserBalanceByMonthYear(User $user, int $month, int $year):array{
        $startCarbon = Carbon::createFromIsoFormat('YYYY-MM-DDTHH:mm:ssZ', $year . '-' . $month . '-01T00:00:00-05:00');
        $endCarbon = Carbon::createFromIsoFormat('YYYY-MM-DDTHH:mm:ssZ', $year . '-' . $month . '-01T00:00:00-05:00')->endOfMonth();

        $timeSpan = [
            'start' => $startCarbon->toDateTime(),
            'end' => $endCarbon->toDateTime(),
        ];
        return BalanceAssistant::generateUserBalanceByTimeRange($user, $timeSpan['start'], $timeSpan['end']);
    }
    public static function generateUserBalanceByTimeRange(User $user, DateTime $start, DateTime $end):array{
        $timeBounds = [
            'start' => $start->format('c'),
            'end' => $end->format('c'),
        ];

        $year = $start->format('Y');

        $yearBounds = [
            'start' => DateTime::createFromFormat('Y-m-d\\TH:i:sP', $year . '-01-01T00:00:00-05:00')->format('c'),
            'end' => DateTime::createFromFormat('Y-m-d\\TH:i:sP', $year . '-12-31T23:59:59-05:00')->format('c'),
        ];

        $balances = Balance::query()->where('user_id', $user->id)->where('date', '>=', $timeBounds['start'])->where('date', '<=', $timeBounds['end'])->orderBy('date', 'asc')->get();
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

        $notApprovedReports = (function() use ($user, $timeBounds){
            //Where  ReportStatus::Submitted or ReportStatus::Rejected or ReportStatus::Draft:
            $reports = Report::query()->where('user_id', $user->id)->where('status', '!=', ReportStatus::Approved)->where('status', '!=', ReportStatus::Restituted)->where('from_date', '>=', $timeBounds['start'])->where('to_date', '<=', $timeBounds['end'])->orderBy('from_date', 'asc')->get();


            $totalInSoles = 0;
            $itemsInDollar = [];
            $itemsInSoles = [];

            $items = [];

            foreach($reports as $report){
                $item = [
                    'id' => $report->id,
                    'title' => $report->title,
                    'date' => $report->firstInvoiceDate(),
                    'amount' => $report->amount(),
                    'money_type' => $report->money_type,
                ];

                if ($report->money_type === MoneyType::USD){
                    $itemsInDollar[] = $item;
                }elseif ($report->money_type === MoneyType::PEN){
                    $itemsInSoles[] = $item;
                }

                $totalInSoles += $report->amountInSoles();

                $items[] = $item;
            }

            return [
                'currencies' => [
                    'dollars' => [
                        'amount' => array_sum(array_column($itemsInDollar, 'amount')),
                        'count' => count($itemsInDollar),
                    ],
                    'soles' => [
                        'amount' => array_sum(array_column($itemsInSoles, 'amount')),
                        'count' => count($itemsInSoles),
                    ],
                ],
                'items' => $items,
                'amount' => $totalInSoles,
            ];
        })();

        $pendingRestitution = (function() use ($user, $timeBounds){
            //Where ReportStatus::Approved
            $reports = Report::query()->where('user_id', $user->id)->where('status', '=', ReportStatus::Approved)->where('from_date', '>=', $timeBounds['start'])->where('to_date', '<=', $timeBounds['end'])->orderBy('from_date', 'asc')->get();

            $totalInSoles = 0;
            $itemsInDollar = [];
            $itemsInSoles = [];

            $items = [];

            foreach($reports as $report){
                $item = [
                    'id' => $report->id,
                    'title' => $report->title,
                    'date' => $report->firstInvoiceDate(),
                    'amount' => $report->amount(),
                    'money_type' => $report->money_type,
                ];

                if ($report->money_type === MoneyType::USD){
                    $itemsInDollar[] = $item;
                }elseif ($report->money_type === MoneyType::PEN){
                    $itemsInSoles[] = $item;
                }

                $totalInSoles += $report->amountInSoles();

                $items[] = $item;
            }

            return [
                'currencies' => [
                    'dollars' => [
                        'amount' => array_sum(array_column($itemsInDollar, 'amount')),
                        'count' => count($itemsInDollar),
                    ],
                    'soles' => [
                        'amount' => array_sum(array_column($itemsInSoles, 'amount')),
                        'count' => count($itemsInSoles),
                    ],
                ],
                'items' => $items,
                'amount' => $totalInSoles,
            ];
        })();


        $pettyCash = [
            'given_amount' => (function() use ($user, $yearBounds){
                $directCredits = Balance::all()->where('model', BalanceModel::Direct)->where('type', BalanceType::Credit)->where('user_id', $user->id)->where('date', '>=', $yearBounds['start'])->where('date', '<=', $yearBounds['end']);
                $directDebits = Balance::all()->where('model', BalanceModel::Direct)->where('type', BalanceType::Debit)->where('user_id', $user->id)->where('date', '>=', $yearBounds['start'])->where('date', '<=', $yearBounds['end']);
                
                $total = 0;
                foreach($directCredits as $directCredit){
                    $total += $directCredit->amount;
                }
                foreach($directDebits as $directDebit){
                    $total -= $directDebit->amount;
                }
                return $total;
            })(),
            'usage_percentage' => (function() use ($user, $timeBounds, $yearBounds){
                $directCredits = Balance::all()->where('model', BalanceModel::Direct)->where('type', BalanceType::Credit)->where('user_id', $user->id)->where('date', '>=', $yearBounds['start'])->where('date', '<=', $yearBounds['end']);
                $directDebits = Balance::all()->where('model', BalanceModel::Direct)->where('type', BalanceType::Debit)->where('user_id', $user->id)->where('date', '>=', $yearBounds['start'])->where('date', '<=', $yearBounds['end']);
                
                $totalDirects = 0;
                foreach($directCredits as $directCredit){
                    $totalDirects += $directCredit->amount;
                }
                foreach($directDebits as $directDebit){
                    $totalDirects -= $directDebit->amount;
                }


                $expensesDebits = Balance::all()->where('model', BalanceModel::Expense)->where('type', BalanceType::Debit)->where('user_id', $user->id)->where('date', '>=', $timeBounds['start'])->where('date', '<=', $timeBounds['end']);
                $restitutionsCredits = Balance::all()->where('model', BalanceModel::Restitution)->where('type', BalanceType::Credit)->where('user_id', $user->id)->where('date', '>=', $timeBounds['start'])->where('date', '<=', $timeBounds['end']);
                
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
            'period' => [
                'start' => $timeBounds['start'],
                'end' => $timeBounds['end'],
                'years' => (function() use ($timeBounds){
                    //Return array of years in the range:
                    $start = DateTime::createFromFormat('Y-m-d\\TH:i:sP', $timeBounds['start']);
                    $end = DateTime::createFromFormat('Y-m-d\\TH:i:sP', $timeBounds['end']);
                    $years = [];
                    for($i = $start->format('Y'); $i <= $end->format('Y'); $i++){
                        $years[] = $i;
                    }
                    return $years;
                })(),
                'months' => (function() use ($timeBounds){
                    //Return array of months (year-month (YYYY-MM)) in the range:
                    $start = DateTime::createFromFormat('Y-m-d\\TH:i:sP', $timeBounds['start']);
                    $end = DateTime::createFromFormat('Y-m-d\\TH:i:sP', $timeBounds['end']);
                    $months = [];
                    for($i = $start->format('m'); $i <= $end->format('m'); $i++){
                        $months[] = $start->format('Y') . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
                    }
                    return $months;
                })(),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
            ],
            'totals' => [
                'credit' => $totalCredit,
                'debit' => $totalDebit,
                'balance' => $total
            ],
            'items' => $items,
            'petty_cash' => [
                'period' => [
                    'year' => $year,
                ],
                'given_amount' => $pettyCash['given_amount'],
                'usage_percentage' => $pettyCash['usage_percentage'],
                'balance' => $total,
                'reports' => [
                    'not_approved' => $notApprovedReports,
                    'pending_reposition' => $pendingRestitution,
                ],
            ]
        ];
    }

    public static function createBalanceExpenseFromReport(Report $report, float|null $amountOverride = null):Balance{
        $balance = Balance::create([
            'description' => 'Reporte "' . $report->title . '"',
            'user_id' => $report->user_id,
            'ticket_number' => null,
            'report_id' => $report->id,
            'date' => $report->submitted_at,
            'type' => BalanceType::Debit,
            'model' => BalanceModel::Expense,
            'amount' => $amountOverride ?? $report->amount(),
        ]);
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
    public static function deleteBalancesFromReport(Report $report):void{
        $balances = Balance::all()->where('report_id', $report->id);
        foreach($balances as $balance){
            $balance->delete();
        }
    }
}