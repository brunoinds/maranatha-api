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
                'receipt_image_url' => $balance->getReceiptImageUrl(),
            ];
        }
        $total = $totalCredit - $totalDebit;

        $notApprovedReports = (function() use ($user, $timeBounds){
            //Where  ReportStatus::Submitted or ReportStatus::Rejected or ReportStatus::Draft:
            $reports = Report::query()->where('user_id', $user->id)->where('status', '!=', ReportStatus::Approved)->where('status', '!=', ReportStatus::Restituted)->where('from_date', '>=', $timeBounds['start'])->where('to_date', '<=', $timeBounds['end'])->orderBy('from_date', 'asc')->get();


            $totalInSoles = 0;
            $totalInDollars = 0;
            $itemsInDollar = [];
            $itemsInSoles = [];

            $items = [];

            foreach($reports as $report){
                $item = [
                    'id' => $report->id,
                    'title' => $report->title,
                    'date' => $report->firstInvoiceDate() || $report->submitted_at,
                    'amount' => $report->amount(),
                    'money_type' => $report->money_type,
                ];

                if ($report->money_type === MoneyType::USD){
                    $itemsInDollar[] = $item;
                }elseif ($report->money_type === MoneyType::PEN){
                    $itemsInSoles[] = $item;
                }

                $totalInSoles += $report->amountInSoles();
                $totalInDollars += $report->amountInDollars();

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
                'amount_in' => [
                    'soles' => $totalInSoles,
                    'dollars' => $totalInDollars,
                ]
            ];
        })();

        $pendingRestitution = (function() use ($user, $timeBounds){
            //Where ReportStatus::Approved
            $reports = Report::query()->where('user_id', $user->id)->where('status', '=', ReportStatus::Approved)->where('from_date', '>=', $timeBounds['start'])->where('to_date', '<=', $timeBounds['end'])->orderBy('from_date', 'asc')->get();

            $totalInSoles = 0;
            $totalInDollars = 0;
            $itemsInDollar = [];
            $itemsInSoles = [];

            $items = [];

            foreach($reports as $report){
                $item = [
                    'id' => $report->id,
                    'title' => $report->title,
                    'date' => $report->firstInvoiceDate() || $report->submitted_at,
                    'amount' => $report->amount(),
                    'money_type' => $report->money_type,
                ];

                if ($report->money_type === MoneyType::USD){
                    $itemsInDollar[] = $item;
                }elseif ($report->money_type === MoneyType::PEN){
                    $itemsInSoles[] = $item;
                }

                $totalInSoles += $report->amountInSoles();
                $totalInDollars += $report->amountInDollars();

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
                'amount_in' => [
                    'soles' => $totalInSoles,
                    'dollars' => $totalInDollars,
                ]
            ];
        })();


        $byReportStatus = (function() use ($user, $timeBounds){
            $parseByStatus = function($reports){
                $totalInSoles = 0;
                $totalInDollars = 0;
                $itemsInDollar = [];
                $itemsInSoles = [];

                $items = [];

                foreach($reports as $report){
                    $item = [
                        'id' => $report->id,
                        'title' => $report->title,
                        'date' => $report->firstInvoiceDate() || $report->submitted_at,
                        'amount' => $report->amount(),
                        'money_type' => $report->money_type,
                    ];

                    if ($report->money_type === MoneyType::USD){
                        $itemsInDollar[] = $item;
                    }elseif ($report->money_type === MoneyType::PEN){
                        $itemsInSoles[] = $item;
                    }

                    $totalInSoles += $report->amountInSoles();
                    $totalInDollars += $report->amountInDollars();

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
                    'amount_in' => [
                        'soles' => $totalInSoles,
                        'dollars' => $totalInDollars,
                    ]
                ];
            };

            //Get list of ReportStatus enums, returning an array with: [ReportStatus::Approved, ReportStatus::Restituted, ReportStatus::Rejected, ReportStatus::Submitted, ReportStatus::Draft]:
            $reportStatuses = ReportStatus::cases();
            foreach ($reportStatuses as $reportStatus){
                $reports = Report::query()->where('user_id', $user->id)->where('status', '=', $reportStatus)->where('from_date', '>=', $timeBounds['start'])->where('to_date', '<=', $timeBounds['end'])->orderBy('from_date', 'asc')->get();
                
                $byStatus[] = [
                    'status' => $reportStatus->name,
                    'data' => $parseByStatus($reports)
                ];
            }
            return $byStatus;
        })();


        $pittyCashGivenAmount = (function() use ($user, $yearBounds){
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
        })();

        $pettyCash = [
            'given_amount' => $pittyCashGivenAmount,
            'usage_percentage' => (function() use ($total, $pittyCashGivenAmount){
                $currentBalance = $total;
                if ($pittyCashGivenAmount == 0){
                    return 100;
                }
                if ($currentBalance < 0){
                    $limit = $pittyCashGivenAmount;
                    $used = $pittyCashGivenAmount - $currentBalance;

                    if($used <= $limit) {
                        $limit_usage_percentage = $used / $limit * 100;
                    } else {
                        $over_limit = $used - $limit;
                        $limit_usage_percentage = 100 + ($over_limit / $limit * 100);  
                    }
                    return $limit_usage_percentage;
                }elseif ($currentBalance === 0){
                    return 100;
                }elseif ($currentBalance > 0){
                    $limit = $pittyCashGivenAmount;
                    $used = $pittyCashGivenAmount - $currentBalance;
                    if($used <= $limit) {
                        $limit_usage_percentage = $used / $limit * 100;
                    } else {
                        $over_limit = $used - $limit;
                        $limit_usage_percentage = 100 + ($over_limit / $limit * 100);  
                    }
                    return $limit_usage_percentage;
                }
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
                    'by_status' => $byReportStatus,
                ],
            ]
        ];
    }

    public static function createBalanceExpenseFromReport(Report $report, float|null $amountOverride = null):Balance{
        $balance = Balance::create([
            'description' => 'Gastos del reporte "' . $report->title . '"',
            'user_id' => $report->user_id,
            'ticket_number' => null,
            'report_id' => $report->id,
            'date' => $report->firstInvoiceDate(),
            'type' => BalanceType::Debit,
            'model' => BalanceModel::Expense,
            'amount' => $report->amountInSoles(),
        ]);
        return $balance;
    }
    public static function createBalanceRestitutionFromReport(Report  $report, float|null $amountOverride = null):Balance{
        //Check if has a balance with report_id and model = Restitution:
        $balance = Balance::all()->where('report_id', $report->id)->where('model', BalanceModel::Restitution)->first();
        if ($balance){
            throw new Exception('There is already a balance with this report_id and model = Restitution');
        }


        $balance = Balance::create([
            'description' => 'Reembolso de reporte "' . $report->title . '"',
            'user_id' => $report->user_id,
            'ticket_number' => null,
            'report_id' => $report->id,
            'date' => Carbon::now()->timezone('America/Lima')->toISOString(true),
            'type' => BalanceType::Credit,
            'model' => BalanceModel::Restitution,
            'amount' => $report->amountInSoles(),
        ]);

        return $balance;
    }
    public static function deleteBalancesFromReport(Report $report):void{
        $balances = Balance::all()->where('report_id', $report->id);
        foreach($balances as $balance){
            $balance->delete();
        }
    }
}