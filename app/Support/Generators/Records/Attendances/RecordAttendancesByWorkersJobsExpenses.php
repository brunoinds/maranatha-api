<?php

namespace App\Support\Generators\Records\Attendances;

use App\Helpers\Enums\MoneyType;
use App\Helpers\Toolbox;
use App\Support\Assistants\WorkersAssistant;
use DateTime;
use App\Models\Job;
use App\Models\Expense;
use App\Models\Worker;



class RecordAttendancesByWorkersJobsExpenses
{

    private DateTime $startDate;
    private DateTime $endDate;
    private string|null $jobCode = null;
    private string|null $expenseCode = null;
    private string|null $supervisor = null;
    private string|null $workerDni = null;
    private string|null $jobZone = null;

    /**
     * @param array $options
     * @param DateTime $options['startDate']
     * @param DateTime $options['endDate']
     * @param null|string $options['jobCode']
     * @param null|string $options['expenseCode']
     * @param null|string $options['supervisor']
     * @param null|string $options['workerDni']
     * @param null|string $options['jobZone']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'];
        $this->endDate = $options['endDate'];
        $this->jobCode = $options['jobCode'];
        $this->expenseCode = $options['expenseCode'];
        $this->supervisor = $options['supervisor'];
        $this->workerDni = $options['workerDni'];
        $this->jobZone = $options['jobZone'];
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

        if ($this->expenseCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('expense.code', '=', $this->expenseCode);
        }

        if ($this->supervisor !== null){
            $spendingsInSpan = $spendingsInSpan->where('worker.supervisor', '=', $this->supervisor);
        }

        if ($this->jobZone !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.zone', '=', $this->jobZone);
        }

        $workersSpendings = $spendingsInSpan;

        $workers = Worker::all();

        $workersJobsAndExpensesSpengings = $workers->map(function($worker) use ($workersSpendings){
            $workerSpendings = $workersSpendings->filter(function($spending) use ($worker){
                return $spending['worker']['dni'] == $worker->dni;
            });

            $workerSpendings = $workerSpendings->groupBy(function($spending){
                return $spending['job']['code'] . '/~/' . $spending['expense']['code'];
            })->sortKeys();


            $jobsAndExpensesSpendings = [];
            $workerSpendings->each(function($spendings, $identificator) use (&$jobsAndExpensesSpendings){
                $jobCode = explode('/~/', $identificator)[0];
                $expenseCode = explode('/~/', $identificator)[1];


                $spendingsCosts = [
                    'days_present' => 0,
                    'payments_money' => MoneyType::toAssociativeArray(0),
                    'divisions_money' => []
                ];

                $spendings->each(function($spending) use (&$spendingsCosts){
                    $spendingsCosts['days_present']++;
                    $spendingsCosts['payments_money'][$spending['payment']['amount_data']['money_type']->value] += $spending['payment']['amount_data']['amount'];

                    collect($spending['payment']['amount_data']['divisions'])->each(function ($division) use (&$spendingsCosts, $spending) {
                        $divisionName = $division['name'];
                        $moneyType = $spending['payment']['amount_data']['money_type']->value;
                        $amount = $division['amount'];

                        $index = collect($spendingsCosts['divisions_money'])->search(function ($item) use ($divisionName) {
                            return $item['name'] === $divisionName;
                        });

                        if ($index === false) {
                            $spendingsCosts['divisions_money'][] = [
                                'name' => $divisionName,
                                'amount' => MoneyType::toAssociativeArray(0)
                            ];
                            $index = count($spendingsCosts['divisions_money']) - 1;
                        }
                        $spendingsCosts['divisions_money'][$index]['amount'][$moneyType] += $amount;
                    });
                });

                $jobsAndExpensesSpendings[] = [
                    'job_code' => $jobCode,
                    'expense_code' => $expenseCode,
                    'spendings' => $spendingsCosts
                ];
            });

            $workerTotalization = [
                'days_present' => 0,
                'payments_money' => MoneyType::toAssociativeArray(0),
                'payments_money_per_day' => MoneyType::toAssociativeArray(0),
                'divisions_money' => MoneyType::toAssociativeArray(0)
            ];

            $jobsAndExpensesSpendings = collect($jobsAndExpensesSpendings);


            $jobsAndExpensesSpendings->each(function($jobAndExpenseSpendings) use (&$workerTotalization, $jobsAndExpensesSpendings){
                $workerTotalization['days_present'] += $jobAndExpenseSpendings['spendings']['days_present'];

                $workerTotalization['payments_money'] = (function() use ($jobsAndExpensesSpendings){
                    $total = MoneyType::toAssociativeArray(0);
                    foreach($jobsAndExpensesSpendings as $jobAndExpenseSpendings){
                        foreach($jobAndExpenseSpendings['spendings']['payments_money'] as $moneyType => $amount){
                            $total[$moneyType] += $amount;

                            $total[$moneyType] = $total[$moneyType];
                        }
                    }
                    return $total;
                })();

                $workerTotalization['payments_money_per_day'] = (function() use ($workerTotalization, $jobsAndExpensesSpendings){
                    $total = MoneyType::toAssociativeArray(0);
                    foreach($jobsAndExpensesSpendings as $jobAndExpenseSpendings){
                        foreach($jobAndExpenseSpendings['spendings']['payments_money'] as $moneyType => $amount){
                            $total[$moneyType] += $amount;

                            $total[$moneyType] = $total[$moneyType];
                        }
                    }

                    if ($workerTotalization['days_present'] === 0){
                        return $total;
                    }

                    foreach ($total as $moneyType => $amount){
                        $total[$moneyType] = $amount / $workerTotalization['days_present'];
                    }
                    return $total;
                })();

                $workerTotalization['divisions_money'] = (function() use ($jobsAndExpensesSpendings){
                    if (collect($jobsAndExpensesSpendings)->flatMap(function ($item) {return $item['spendings']['divisions_money'];})->count() === 0){
                        return [];
                    }
                    return collect($jobsAndExpensesSpendings)
                        ->flatMap(function ($item) {
                            return $item['spendings']['divisions_money'];
                        })
                        ->groupBy('name')
                        ->map(function ($group) {
                            return [
                                'name' => $group->first()['name'],
                                'amount' => $group->reduce(function ($carry, $item) {
                                    foreach ($item['amount'] as $moneyType => $amount) {
                                        if (!isset($carry[$moneyType])) {
                                            $carry[$moneyType] = 0;
                                        }
                                        $carry[$moneyType] += $amount;
                                        $carry[$moneyType] = $carry[$moneyType];
                                    }
                                    return $carry;
                                }, MoneyType::toAssociativeArray(0))
                            ];
                        })
                        ->values()
                        ->toArray();
                })();
            });

            if ($jobsAndExpensesSpendings->count() === 0){
                $workerTotalization['divisions_money'] = [];
            }

            return [
                'dni' => $worker->dni,
                'totalization' => $workerTotalization,
                'jobs_and_expenses_spendings' => $jobsAndExpensesSpendings->toArray()
            ];
        });



        return $workersJobsAndExpensesSpengings->toArray();
    }

    private function createTable():array{
        $workers = collect($this->getWorkersData());

        $metadata = [
            'spendings_tree' => [
                'jobs' => []
            ],
            'workers' => [],
            'headers' => []
        ];

        $metadata['workers'] = $workers->map(function($worker){
            return [
                'name' => Worker::where('dni', '=', $worker['dni'])->first()->name,
                'dni' => $worker['dni'],
                'spending_totals' => $worker['totalization'],
            ];
        })->toArray();

        $leftTableData = (function() use ($workers){
            $headers = (function() use ($workers){
                $headers = [
                    [
                        'title' => 'ID',
                        'key' => 'id',
                    ],
                    [
                        'title' => 'Name',
                        'key' => 'name',
                    ],
                    [
                        'title' => 'DNI',
                        'key' => 'dni',
                    ],
                    [
                        'title' => 'Días Trabajados',
                        'key' => 'worker_total_days_present'
                    ]
                ];



                $divisionsAvailable = [];

                $workers->each(function($worker) use (&$divisionsAvailable){
                    collect($worker['totalization']['divisions_money'])->each(function($division) use (&$divisionsAvailable){
                        if (!in_array($division['name'], $divisionsAvailable)){
                            $divisionsAvailable[] = $division['name'];
                        }
                    });
                });

                $divisionsAvailable = collect($divisionsAvailable)->map(function($division){
                    return collect(MoneyType::toArray())->map(function($moneyType) use ($division){
                        return [
                            'title' => 'División ' . $division . ' en ' . $moneyType,
                            'key' => 'worker_total_amount_in_division_/~/' . $division . '_/~/_in_' . $moneyType . '_money',
                        ];
                    });
                })->flatten(1)->each(function($header) use (&$headers){
                    $headers[] = $header;
                });

                collect(MoneyType::toArray())->map(function($moneyType){
                    return [
                        'title' => 'Sueldo en ' . $moneyType,
                        'key' => 'worker_total_amount_in_' . $moneyType . '_money',
                    ];
                })->each(function($header) use (&$headers){
                    $headers[] = $header;
                });
                collect(MoneyType::toArray())->map(function($moneyType){
                    return [
                        'title' => 'Sueldo Diário en ' . $moneyType,
                        'key' => 'worker_daily_total_amount_in_' . $moneyType . '_money',
                    ];
                })->each(function($header) use (&$headers){
                    $headers[] = $header;
                });

                return $headers;
            })();
            $body = (function() use ($workers){
                $lines = $workers->map(function($worker){
                    $workerInstance = Worker::where('dni', '=', $worker['dni'])->first();
                    $data = [
                        'id' => $workerInstance->id,
                        'name' => $workerInstance->name,
                        'dni' => $worker['dni'],
                    ];

                    collect(MoneyType::toArray())->each(function($moneyType) use (&$data, &$worker){
                        $data['worker_total_amount_in_' . $moneyType . '_money'] = $worker['totalization']['payments_money'][$moneyType];
                    });

                    collect(MoneyType::toArray())->each(function($moneyType) use (&$data, &$worker){
                        $data['worker_daily_total_amount_in_' . $moneyType . '_money'] = $worker['totalization']['payments_money_per_day'][$moneyType];
                    });

                    $data['worker_total_days_present'] = $worker['totalization']['days_present'];

                    collect($worker['totalization']['divisions_money'])->each(function($division) use (&$data){
                        collect(MoneyType::toArray())->each(function($moneyType) use (&$data, &$division){
                            $data['worker_total_amount_in_division_/~/' . $division['name'] . '_/~/_in_' . $moneyType . '_money'] = $division['amount'][$moneyType];
                        });
                    });

                    return $data;
                });

                return $lines->toArray();
            })();
            $footers = (function() use ($body, $headers){
                $footers = [];
                foreach ($headers as $header){
                     //Check if line  ends with '_money', if not, skip iteration:
                    if (strpos($header['key'], '_money') === false){
                        continue;
                    }

                    $key = $header['key'];
                    $sum = 0;
                    foreach ($body as $line){
                        if (isset($line[$key])){
                            $sum += $line[$key];
                        }
                    }
                    $footers[$key] = Toolbox::toFixed($sum, 2);
                }
                return $footers;
            })();

            return [
                'headers' => $headers,
                'body' => $body,
                'footer' => $footers
            ];
        })();

        $rightTableData = (function() use ($workers, &$metadata){
            $jobsExpensesTree = (function() use ($workers){
                $jobs = [];
                $workers->each(function($worker) use (&$jobs){
                    foreach ($worker['jobs_and_expenses_spendings'] as $jobAndExpenseSpendings){
                        $jobCode = $jobAndExpenseSpendings['job_code'];
                        $expenseCode = $jobAndExpenseSpendings['expense_code'];

                        if (!isset($jobs[$jobCode])){
                            $jobs[$jobCode] = [
                                'job_code' => $jobCode,
                                'expenses' => []
                            ];
                        }

                        if (!isset($jobs[$jobCode]['expenses'][$expenseCode])){
                            $jobs[$jobCode]['expenses'][$expenseCode] = [
                                'expense_code' => $expenseCode,
                                'workers' => []
                            ];
                        }

                        $jobs[$jobCode]['expenses'][$expenseCode]['workers'][] = [
                            'dni' => $worker['dni'],
                            'days_present' => $jobAndExpenseSpendings['spendings']['days_present'],
                            'payments_money' => $jobAndExpenseSpendings['spendings']['payments_money'],
                        ];
                    }
                });
                return $jobs;
            })();

            $metadata['spendings_tree']['jobs'] = (function() use ($jobsExpensesTree){
                $jobs = [];
                foreach ($jobsExpensesTree as $job => $jobData){
                    $jobItem = [
                        'job_name' => Job::where('code', '=', $job)->first()->name,
                        'job_code' => $job,
                        'expenses' => []
                    ];
                    foreach ($jobData['expenses'] as $expense => $expenseData){
                        $jobItem['expenses'][] = [
                            'expense_name' => Expense::where('code', '=', $expense)->first()->name,
                            'expense_code' => $expense,
                            'workers' => $expenseData['workers']
                        ];
                    }
                    $jobs[] = $jobItem;
                }
                return $jobs;
            })();



            $body = (function() use ($jobsExpensesTree){
                $lines = [];
                foreach ($jobsExpensesTree as $job){
                    foreach ($job['expenses'] as $expense){
                        foreach ($expense['workers'] as $worker){
                            //Check if there is an line with this dni, if so, use that line, if not, create a new line:
                            $line = collect($lines)->filter(function($line) use ($worker){
                                return $line['dni'] == $worker['dni'];
                            })->first();

                            if ($line === null){
                                $line = [
                                    'dni' => $worker['dni'],
                                ];
                                $lines[] = $line;
                            }

                            $line['job_' . $job['job_code'] . '_expense_' . $expense['expense_code'] . '_days_present'] = $worker['days_present'];

                            foreach ($worker['payments_money'] as $moneyType => $amount){
                                $line['job_' . $job['job_code'] . '_expense_' . $expense['expense_code'] . '_payment_' . $moneyType . '_money'] = $amount;
                            }

                            $lines = collect($lines)->map(function($lineItem) use ($line){
                                if ($lineItem['dni'] === $line['dni']){
                                    return $line;
                                }else{
                                    return $lineItem;
                                }
                            })->toArray();
                        }
                    }
                }

                return $lines;
            })();


            $headers = (function() use ($jobsExpensesTree, $body){
                $headers = [];

                foreach ($jobsExpensesTree as $job){
                    foreach ($job['expenses'] as $expense){
                        $headersToAdd = [];


                        $key = 'job_' . $job['job_code'] . '_expense_' . $expense['expense_code'] . '_days_present';


                        $sumWorkersThatWorkedInThisExpenseJob = 0;
                        foreach ($body as $line){
                            if (isset($line[$key]) && $line[$key] > 0){
                                $sumWorkersThatWorkedInThisExpenseJob++;
                            }
                        }

                        if ($sumWorkersThatWorkedInThisExpenseJob === 0){
                            continue;
                        }

                        $headersToAdd[] = [
                            'title' => $job['job_code'] . ' - ' . $expense['expense_code'] . ' - Days Present',
                            'key' => $key
                        ];

                        $hasEnoughWorkers = false;

                        foreach (MoneyType::toArray() as $moneyType){
                            $key = 'job_' . $job['job_code'] . '_expense_' . $expense['expense_code'] . '_payment_' . $moneyType . '_money';
                            $sumWorkersThatReceivedInThisExpenseJob = 0;
                            foreach ($body as $line){
                                if (isset($line[$key]) && $line[$key] > 0){
                                    $sumWorkersThatReceivedInThisExpenseJob++;
                                }
                            }
                            if ($sumWorkersThatReceivedInThisExpenseJob === 0){
                            }else{
                                $hasEnoughWorkers = true;
                            }
                            $headersToAdd[] = [
                                'title' => $job['job_code'] . ' - ' . $expense['expense_code'] . ' - ' . $moneyType,
                                'key' => $key,
                            ];
                        }

                        if ($hasEnoughWorkers){
                            $headers = array_merge($headers, $headersToAdd);
                        }
                    }
                }

                return $headers;
            })();

            $footers = (function() use ($body, $headers){
                $footers = [];


                foreach ($headers as $header){
                     //Check if line starts with 'job_' and ends with '_money', if not, skip iteration:
                    if (strpos($header['key'], 'job_') !== 0 && strpos($header['key'], '_money') === false){
                        continue;
                    }

                    $key = $header['key'];
                    $sum = 0;
                    foreach ($body as $line){
                        if (isset($line[$key])){
                            $sum += $line[$key];
                        }
                    }
                    $footers[$key] = Toolbox::toFixed($sum, 2);
                }
                return $footers;
            })();


            return [
                'headers' => $headers,
                'body' => $body,
                'footer' => $footers
            ];
        })();

        $headers = array_merge($leftTableData['headers'], $rightTableData['headers']);
        $footers = array_merge($leftTableData['footer'], $rightTableData['footer']);

        $body = (function() use ($leftTableData, $rightTableData){
            $rightTableBody = $rightTableData['body'];
            foreach ($leftTableData['body'] as &$line){
                $dni = $line['dni'];
                $rightTableLine = collect($rightTableBody)->filter(function($rightTableLine) use ($dni){
                    return $rightTableLine['dni'] == $dni;
                })->first();

                if ($rightTableLine === null){
                    continue;
                }

                foreach ($rightTableLine as $key => $value){
                    if ($key === 'dni'){
                        continue;
                    }

                    $line[$key] = $value;
                }
            }

            return $leftTableData['body'];
        })();


        return [
            'headers' => $headers,
            'body' => $body,
            'footer' => $footers
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
