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

class WorkersAssistant{
    public static function getListWorkers():array{
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
    public static function getListWorkersWithPayments():array{
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
    public static function getWorkerByDNI(string $dni):array{
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
                    if ($payment && $countDaysPresent > 0 && $payment['amount'] > 0){
                        $amountPerDayInMonthYear = $payment['amount'] / $countDaysPresent;
                    }

                    $attendancesWithPaymentAmount = $attendances->map(function($item) use ($monthYear, $payment, $amountPerDayInMonthYear, $worker){
                        if ($item['status'] == AttendanceStatus::Present->value){
                            $item['payment'] = [
                                'period' => $monthYear,
                                'amount' => $amountPerDayInMonthYear
                            ];
                        }else{
                            $item['payment'] = [
                                'period' => $monthYear,
                                'amount' => 0
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
                            'job' => [
                                'code' => null
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
                            ],
                            'amount' => $item['payment']['amount'],
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

                $listAttendances = Attendance::query()->whereIn('id', $listAttendancesIds->unique()->toArray())->get();

                foreach ($spendingsNonFilled as &$spending){
                    $attendance = $listAttendances->where('id', '=', $spending['attendance']['id'])->first();
                    if ($attendance !== null){
                        $spending['attendance']['user_id'] = (int) $attendance->user_id;
                        $spending['attendance']['created_at'] = $attendance->created_at;
                        $spending['job']['code'] = $attendance->job()->code;
                        $spending['expense']['code'] = $attendance->expense()->code;
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
}