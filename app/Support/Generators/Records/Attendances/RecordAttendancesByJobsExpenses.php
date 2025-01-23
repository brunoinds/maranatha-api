<?php

namespace App\Support\Generators\Records\Attendances;

use App\Helpers\Enums\MoneyType;
use App\Support\Exchange\Exchanger;

use App\Helpers\Toolbox;

use App\Support\Assistants\WorkersAssistant;
use DateTime;
use App\Models\Job;
use App\Models\Expense;



class RecordAttendancesByJobsExpenses
{

    private DateTime $startDate;
    private DateTime $endDate;
    private string|null $jobCode = null;
    private string|null $expenseCode = null;
    private string|null $supervisor = null;
    private string|null $workerDni = null;
    private string|null $jobZone = null;
    private string|null $country = null;


    /**
     * @param array $options
     * @param DateTime $options['startDate']
     * @param DateTime $options['endDate']
     * @param null|string $options['jobCode']
     * @param null|string $options['expenseCode']
     * @param null|string $options['supervisor']
     * @param null|string $options['workerDni']
     * @param null|string $options['jobZone']
     * @param null|string $options['country']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'];
        $this->endDate = $options['endDate'];
        $this->jobCode = $options['jobCode'];
        $this->expenseCode = $options['expenseCode'];
        $this->supervisor = $options['supervisor'];
        $this->workerDni = $options['workerDni'];
        $this->jobZone = $options['jobZone'];
        $this->country = $options['country'];
    }

    private function getWorkersData():array
    {
        $workersSpendings = collect(WorkersAssistant::getWorkersSpendings())->map(function($workerSpendings){
            return $workerSpendings['spendings'];
        })->flatten(1);

        $spendingsInSpan = $workersSpendings->where('date_day', '>=', $this->startDate->format('Y-m-d'))->where('date_day', '<=', $this->endDate->format('Y-m-d'));

        if ($this->workerDni !== null){
            $spendingsInSpan = $spendingsInSpan->where('worker.dni', '=', $this->workerDni);
        }

        if ($this->jobCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.code', '=', $this->jobCode);
        }

        if ($this->country !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.country', '=', $this->country);
        }

        if ($this->expenseCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('expense.code', '=', $this->expenseCode);
        }

        if ($this->supervisor !== null){
            $spendingsInSpan = $spendingsInSpan->where('worker.supervisor', '=', $this->supervisor);
        }

        if ($this->jobZone !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.zone', '=', $this->jobZone);
        }

        $spendingsInSpan = collect($spendingsInSpan)->groupBy(function($spending){
            return $spending['job']['code'] . '/~/' . $spending['expense']['code'] . '/~/' . $spending['job']['zone'];
        })->sortKeys();


        $jobs = Job::all();
        $expenses = Expense::all();

        $attendancesByJobExpense = collect($spendingsInSpan)->map(function($spendings, $identificator) use ($jobs, $expenses) {
            $jobCode = explode('/~/', $identificator)[0];
            $expenseCode = explode('/~/', $identificator)[1];
            $jobZone = explode('/~/', $identificator)[2];

            return [
                'job' => Job::sanitizeCode($jobCode) . ' - ' . $jobs->where('code', $jobCode)->first()->name,
                'job_zone' => $jobZone,
                'expense' => $expenseCode . ' - ' . $expenses->where('code', $expenseCode)->first()->name,
                'country' => $jobs->where('code', $jobCode)->first()->country,
                'spendings' => collect($spendings)->map(function($spending){
                    $spending = Toolbox::toObject($spending);
                    $spending->amountInSoles = (function() use ($spending){
                        return $spending->amount;
                    })();
                    $spending->amountInDollars = (function() use ($spending){
                        $date = new DateTime($spending->date);
                        return Exchanger::on($date)->convert($spending->amount,MoneyType::PEN, MoneyType::USD);
                    })();

                    $spending->amountOriginalData = (function() use ($spending){
                        return $spending->payment->amount_data;
                    })();

                    return $spending;
                })
            ];
        })->toArray();


        $attendancesByJobExpense = collect($attendancesByJobExpense)->map(function($item){
            $spendings = $item['spendings'];

            $amountInCurrencies = MoneyType::toAssociativeArray(0);


            $item['totals'] = [
                'amount_in_soles' => 0,
                'amount_in_dollars' => 0
            ];


            $item['totals']['amount_in_soles'] = $spendings->sum(function($spending){
                return $spending->amountInSoles;
            });
            $item['totals']['amount_in_dollars'] = $spendings->sum(function($spending){
                return $spending->amountInDollars;
            });


            $spendings->each(function($spending) use (&$amountInCurrencies){
                $amountInCurrencies[$spending->amountOriginalData->money_type] += $spending->amountOriginalData->amount;
            });


            $return = [
                'job' => $item['job'],
                'job_zone' => $item['job_zone'],
                'country' => $item['country'],
                'expense' => $item['expense'],
                'amount_in_soles' => $item['totals']['amount_in_soles'],
                'amount_in_dollars' => $item['totals']['amount_in_dollars'],
            ];

            foreach ($amountInCurrencies as $currency => $amount){
                $amount = number_format($amount, 2, '.', '');

                if ($currency === MoneyType::PYG){
                    $amount = round($amount, 0);
                }

                $return['parcial_amount_in_' . strtolower($currency)] = $amount;
            }

            return $return;
        });

        $attendancesByJobExpense = $attendancesByJobExpense->values();
        return $attendancesByJobExpense->toArray();
    }

    private function createTable():array{
        $spendings = $this->getWorkersData();

        $spendings = array_column($spendings, null);


        $mergingJobCodeRows = (function() use ($spendings){
            $indexes = [];
            $currentJobCode = null;
            $currentIndex = null;
            foreach($spendings as $index => $spending){
                if ($currentJobCode === null){
                    $currentJobCode = $spending['job'];
                    $currentIndex = $index;
                } else {
                    if ($currentJobCode === $spending['job']){
                        continue;
                    } else {
                        if ($currentIndex !== $index - 1){
                            $indexes[] = ['from' => $currentIndex, 'to' => $index - 1];
                        }
                        $currentJobCode = $spending['job'];
                        $currentIndex = $index;
                    }
                }
            }
            return $indexes;
        })();

        $mergingJobZoneRows = (function() use ($spendings){
            $indexes = [];
            $currentJobZone = null;
            $currentIndex = null;
            foreach($spendings as $index => $spending){
                if ($currentJobZone === null){
                    $currentJobZone = $spending['job_zone'];
                    $currentIndex = $index;
                } else {
                    if ($currentJobZone === $spending['job_zone']){
                        continue;
                    } else {
                        if ($currentIndex !== $index - 1){
                            $indexes[] = ['from' => $currentIndex, 'to' => $index - 1];
                        }
                        $currentJobZone = $spending['job_zone'];
                        $currentIndex = $index;
                    }
                }
            }
            return $indexes;
        })();

        $headers = [
            [
                'title' => 'Job',
                'key' => 'job',
            ],
            [
                'title' => 'Expense',
                'key' => 'expense',
            ],
            [
                'title' => 'Zona',
                'key' => 'job_zone',
            ],
            [
                'title' => 'País',
                'key' => 'country',
            ]
        ];

        foreach (MoneyType::toArray() as $moneyType){
            $headers[] = [
                'title' => 'Gasto Parcial (' . $moneyType . ')',
                'key' => 'parcial_amount_in_' . strtolower($moneyType),
            ];
        }

        $headers = [
            ...$headers,
            [
                'title' => 'Costo Total (Dólares)',
                'key' => 'amount_in_dollars',
            ],
            [
                'title' => 'Costo Total (Soles)',
                'key' => 'amount_in_soles',
            ],
        ];

        return [
            'headers' => $headers,
            'body' => $spendings,
            'footer' => [
                'totals' => [
                    'title' => 'Totales',
                    'items' => [
                        [
                            'key' => 'amount_in_soles',
                            'value' => round(array_sum(array_column($spendings, 'amount_in_soles')), 2),
                        ],
                        [
                            'key' => 'amount_in_dollars',
                            'value' => round(array_sum(array_column($spendings, 'amount_in_dollars')),2),
                        ]
                    ]
                ]
            ],
            'rules' => [
                'merging' => [
                    'rows' => [
                        [
                            'key' => 'job',
                            'indexes' => $mergingJobCodeRows
                        ],
                        [
                            'key' => 'job_zone',
                            'indexes' => $mergingJobZoneRows
                        ]
                    ]
                ]
            ]
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'startDate' => $this->startDate->format('c'),
                'endDate' => $this->endDate->format('c'),
                'supervisor' => $this->supervisor,
                'jobCode' => $this->jobCode,
                'expenseCode' => $this->expenseCode,
                'workerDni' => $this->workerDni,
                'jobZone' => $this->jobZone,
            ],
        ];
    }
}
