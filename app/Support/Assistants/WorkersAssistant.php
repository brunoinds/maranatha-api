<?php

namespace App\Support\Assistants;

use App\Helpers\Enums\BalanceModel;
use App\Helpers\Enums\BalanceType;
use App\Models\AttendanceDayWorker;
use App\Models\Balance;
use App\Support\GoogleSheets\Excel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\Attendance;
use App\Helpers\Enums\AttendanceStatus;
use App\Helpers\Enums\MoneyType;
use Illuminate\Support\Facades\Cache;
use App\Models\Worker;
use App\Models\WorkerPayment;
use App\Support\Exchange\Exchanger;



class WorkersAssistant{
    public static function getListWorkers():array
    {
        $workers = collect(Worker::all())->map(function($item){
            return [
                'dni' => $item['dni'],
                'name' => $item['name'],
                'team' => $item['team'],
                'supervisor' => $item['supervisor'],
                'function' => $item['role'],
                'is_active' => $item['is_active'],
            ];
        });
        return $workers->toArray();
    }
    public static function getListWorkersWithPayments():array
    {
        $workers = collect(Worker::all())->map(function($item){
            $payments = $item->payments->map(function($payment){
                $month = str_pad($payment['month'], 2, '0', STR_PAD_LEFT);
                $monthYear =  $month . '/' . $payment['year'];
                $amount = $payment['amount'];
                $moneyType = $payment['currency'];
                return [
                    'month_year' => $monthYear,
                    'month' => $payment['month'],
                    'year' => $payment['year'],
                    'amount' => (function() use ($amount, $moneyType, $monthYear){
                        if ($moneyType === MoneyType::PEN){
                            return $amount;
                        }else{
                            $date = Carbon::createFromFormat('m/Y', $monthYear)->timezone('America/Lima')->startOfMonth()->toDateTime();
                            return Exchanger::on($date)->convert($amount, $moneyType, MoneyType::PEN);
                        }
                    })(),
                    'amount_data' => [
                        'original' => [
                            'amount' => $amount,
                            'money_type' => $moneyType
                        ]
                    ],
                    'timespan' => [
                        'start' => Carbon::createFromFormat('m/Y', $monthYear)->timezone('America/Lima')->startOfMonth()->format('c'),
                        'end' => Carbon::createFromFormat('m/Y', $monthYear)->timezone('America/Lima')->endOfMonth()->endOfDay()->format('c'),
                    ],
                ];
            });

            //Complete the payments with the missing months of 2024:
            $paymentsMonths = collect($payments)->map(function($payment){
                return $payment['month_year'];
            });
            $months = collect(range(1, 12))->map(function($month){
                return str_pad($month, 2, '0', STR_PAD_LEFT);
            });
            $years = collect(range(2024, 2024));
            $monthsYears = $years->map(function($year) use ($months){
                return $months->map(function($month) use ($year){
                    return $month . '/' . $year;
                });
            })->flatten();
            $missingMonths = $monthsYears->diff($paymentsMonths);
            $missingPayments = $missingMonths->map(function($monthYear){
                $month = intval(explode('/', $monthYear)[0]);
                $year = intval(explode('/', $monthYear)[1]);
                return [
                    'month_year' => $monthYear,
                    'month' => $month,
                    'year' => $year,
                    'amount' => 0,
                    'amount_data' => [
                        'original' => [
                            'amount' => 0,
                            'money_type' => MoneyType::PEN
                        ]
                    ],
                    'timespan' => [
                        'start' => Carbon::createFromFormat('m/Y', $monthYear)->timezone('America/Lima')->startOfMonth()->format('c'),
                        'end' => Carbon::createFromFormat('m/Y', $monthYear)->timezone('America/Lima')->endOfMonth()->endOfDay()->format('c'),
                    ],
                ];
            });
            $payments = collect($payments)->merge($missingPayments);

            return [
                'dni' => $item['dni'],
                'name' => $item['name'],
                'team' => $item['team'],
                'supervisor' => $item['supervisor'],
                'function' => $item['role'],
                'payments' => $payments->toArray(),
                'is_active' => $item['is_active'],
            ];
        });

        return $workers->toArray();
    }
    public static function getWorkerByDNI(string $dni):array
    {
        $workers = self::getListWorkers();
        foreach($workers as $worker){
            if ($worker['dni'] === $id){
                return $worker;
            }
        }
        return null;
    }

    public static function getWorkersSpendings():array{
        $listWorkersWithPayments = WorkersAssistant::getListWorkersWithPayments();
        $workersPaymentDistribution = [];
        foreach ($listWorkersWithPayments as $worker){
            $attendances = AttendanceDayWorker::query()->where('worker_dni', '=', $worker['dni'])->get();
            $attendances = collect($attendances)->map(function($item){
                return array_merge($item->toArray(), [
                    'month_year' => (new Carbon($item->date))->format('m/Y'),
                ]);
            });

            $attendancesByMonthYear = $attendances->groupBy('month_year');

            foreach ($attendancesByMonthYear as $monthYear => $attendances){
                $attendances = collect($attendances);
                $payment = collect($worker['payments'])->where('month_year', '=', $monthYear)->first();
                $perDayPresentDistribution = (function() use ($monthYear, $attendances, $payment, $worker){
                    $countDaysPresent = $attendances->where('status', '=', AttendanceStatus::Present->value)->count();
                    $amountPerDayInMonthYear = 0;
                    $amountPerDayInOriginalCurrencyInMonthYear = 0;
                    if ($payment && $countDaysPresent > 0 && $payment['amount'] > 0){
                        $amountPerDayInMonthYear = $payment['amount'] / $countDaysPresent;
                        $amountPerDayInOriginalCurrencyInMonthYear = $payment['amount_data']['original']['amount'] / $countDaysPresent;

                        if ($payment['amount_data']['original']['money_type'] === MoneyType::PYG->value){
                            $amountPerDayInOriginalCurrencyInMonthYear = round($amountPerDayInOriginalCurrencyInMonthYear, 0);
                        }
                    }

                    $attendancesWithPaymentAmount = $attendances->map(function($item) use ($monthYear, $payment, $amountPerDayInMonthYear, $amountPerDayInOriginalCurrencyInMonthYear, $worker){
                        if ($item['status'] == AttendanceStatus::Present->value){
                            $item['payment'] = [
                                'period' => $monthYear,
                                'amount' => $amountPerDayInMonthYear,
                                'amount_data' => [
                                    'amount' => $amountPerDayInOriginalCurrencyInMonthYear,
                                    'money_type' => $payment['amount_data']['original']['money_type']
                                ]
                            ];
                        }else{
                            $item['payment'] = [
                                'period' => $monthYear,
                                'amount' => 0,
                                'amount_data' => [
                                    'amount' => 0,
                                    'money_type' => $payment['amount_data']['original']['money_type']
                                ]
                            ];
                        }

                        $worker = (function() use ($worker){
                            unset($worker['payments']);
                            return $worker;
                        })();

                        return [
                            'worker' => $worker,
                            'attendance_day' => [
                                'id' => $item['id'],
                                'date' => $item['date'],
                                'worker_dni' => $item['worker_dni'],
                                'status' => $item['status'],
                                'attendance_id' => $item['attendance_id'],
                            ],
                            'attendance' => [
                                'id' => $item['attendance_id'],
                                'created_at' => null,
                                'user_id' => null,
                            ],
                            'date' => $item['date'],
                            'date_day' => Carbon::parse($item['date'])->format('Y-m-d'),
                            'job' => [
                                'code' => null,
                                'zone' => null,
                            ],
                            'expense' => [
                                'code' => null
                            ],
                            'payment' => [
                                'period' => [
                                    'month_year' => $monthYear,
                                    'start' => $payment['timespan']['start'],
                                    'end' => $payment['timespan']['end'],
                                    'amount' => $payment['amount'],
                                    'amount_per_day' => $amountPerDayInMonthYear,
                                ],
                                'amount' => $item['payment']['amount'],
                                'amount_data' => $item['payment']['amount_data']
                            ],
                            'amount' => $item['payment']['amount'],
                            'amount_data' => $item['payment']['amount_data']
                        ];

                        return $item;
                    });
                    return [
                        'attendances_with_payments' => $attendancesWithPaymentAmount
                    ];
                })();
                $attendancesByMonthYear[$monthYear] = $perDayPresentDistribution;
            }


            $worker = (function() use ($worker){
                unset($worker['payments']);
                return $worker;
            })();
            $spendingsNonFilled = (function() use ($attendancesByMonthYear){
                $attendancesWithPayments = [];
                $payments = [];
                foreach ($attendancesByMonthYear as $monthYear => $period){
                    $payments[] = $period;
                }

                foreach ($payments as $payment){
                    foreach ($payment['attendances_with_payments'] as $attendance){
                        $attendancesWithPayments[] = $attendance;
                    }
                }
                return $attendancesWithPayments;
            })();


            $spendingsFilled = (function() use ($spendingsNonFilled){
                $listAttendancesIds = collect([]);
                foreach ($spendingsNonFilled as $spending){
                    $listAttendancesIds->push($spending['attendance']['id']);
                }

                $listAttendances = Attendance::query()
                    ->whereIn('id', $listAttendancesIds->unique()->toArray())
                    ->with('job', 'expense') // Eager load the job and expense relationships
                    ->get();

                foreach ($spendingsNonFilled as &$spending){
                    $attendance = $listAttendances->where('id', '=', $spending['attendance']['id'])->first();
                    if ($attendance !== null){
                        $spending['attendance']['user_id'] = (int) $attendance->user_id;
                        $spending['attendance']['created_at'] = $attendance->created_at;
                        $spending['job']['code'] = $attendance->job->code;
                        $spending['job']['zone'] = $attendance->job->zone;
                        $spending['expense']['code'] = $attendance->expense->code;
                    }
                }
                return $spendingsNonFilled;
            })();



            $workersPaymentDistribution[] = [
                'worker' => $worker,
                'spendings' => $spendingsFilled,
            ];
        }

        return $workersPaymentDistribution;
    }



    public static function getListWorkersLegacy():array
    {
        $workers = collect(Excel::getWorkersSheet())->map(function($item){
            return [
                'dni' => $item['dni'],
                'name' => $item['name'],
                'team' => $item['team'],
                'supervisor' => $item['supervisor'],
                'function' => $item['function'],
                'is_active' => $item['is_active'],
            ];
        });
        return $workers->toArray();
    }
    public static function getListWorkersWithPaymentsLegacy():array
    {
        $workers = collect(Excel::getWorkersSheet())->map(function($item){
            return [
                'dni' => $item['dni'],
                'name' => $item['name'],
                'team' => $item['team'],
                'supervisor' => $item['supervisor'],
                'function' => $item['function'],
                'payments' => $item['payments'],
                'is_active' => $item['is_active'],
            ];
        });
        return $workers->toArray();
    }
}
