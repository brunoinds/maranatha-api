<?php

namespace App\Support\Generators\Records\Jobs;

use App\Helpers\Enums\AttendanceStatus;
use App\Helpers\Toolbox;
use App\Models\AttendanceDayWorker;
use App\Support\Exchange\Exchanger;
use App\Helpers\Enums\MoneyType;


use App\Support\Assistants\WorkersAssistant;
use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Invoice;
use Brunoinds\SunatDolarLaravel\Exchange;


class RecordJobsByCosts
{

    private DateTime $startDate;
    private DateTime $endDate;
    private string|null $jobRegion = null;
    private string|null $expenseCode = null;
    
    /**
     * @param array $options
     * @param DateTime $options['startDate']
     * @param DateTime $options['endDate']
     * @param null|string $options['jobRegion']
     * @param null|string $options['expenseCode']
     */
    
    public function __construct(array $options){
        $this->startDate = $options['startDate'];
        $this->endDate = $options['endDate'];
        $this->jobRegion = $options['jobRegion'];
        $this->expenseCode = $options['expenseCode'];
    }

    private function getInvoicesData():array
    {
        //Get filtered invoices data;

        //For each day, get the invoices:
        //Carbon create array of dates between $this->startDate and $this->endDate
        //For each date, get the invoices:

        $invoicesInSpan = Invoice::query()
                    ->with('report')
                    ->join('reports', 'invoices.report_id', '=', 'reports.id')
                    ->join('jobs', 'invoices.job_code', '=', 'jobs.code')
                    ->where('invoices.date', '>=', $this->startDate)
                    ->where('invoices.date', '<=', $this->endDate)
                    ->where(function($query){
                        $query->where('reports.status', '=', 'Approved')
                                ->orWhere('reports.status', '=', 'Restituted');
                    });


        if ($this->jobRegion !== null){
            $invoicesInSpan = $invoicesInSpan->where('zone', '=', $this->jobRegion);
        }

        if ($this->expenseCode !== null){
            $invoicesInSpan = $invoicesInSpan->where('expense_code', '=', $this->expenseCode);
        }

        $invoicesInSpan = $invoicesInSpan->get();



        
        //Group by invoice->job()->code:

        $invoicesInSpan = collect($invoicesInSpan)->groupBy(function($invoice){
            return $invoice->job_code;
        });

        //Now convert the group by to an array of arrays:
        $invoicesInSpan = array_column($invoicesInSpan->map(function($invoices, $jobCode){
            return [
                'job_code' => $jobCode,
                'invoices' => $invoices,
            ];
        })->toArray(), null);

        return $invoicesInSpan;
    }
    private function getWorkersData():array
    {
        $workersSpendings = collect(WorkersAssistant::getWorkersSpendings())->map(function($workerSpendings){
            return $workerSpendings['spendings'];
        })->flatten(1);

        $spendingsInSpan = $workersSpendings->where('date', '>=', $this->startDate->format('c'))->where('date', '<=', $this->endDate->format('c'));

        if ($this->jobRegion !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.zone', '=', $this->jobRegion);
        }

        if ($this->expenseCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('expense.code', '=', $this->expenseCode);
        }


        $spendingsInSpan = collect($spendingsInSpan)->groupBy(function($spending){
            return $spending['job']['code'];
        });



        $spendingsInSpan = array_column($spendingsInSpan->map(function($spendings, $jobCode){
            return [
                'job_code' => $jobCode,
                'spendings' => collect($spendings)->map(function($spending){
                    $spending = Toolbox::toObject($spending);
                    $spending->amountInSoles = (function() use ($spending){
                        return $spending->amount;
                    })();
                    $spending->amountInDollars = (function() use ($spending){
                        $date = new DateTime($spending->date);
                        return Exchanger::on($date)->convert($spending->amount,MoneyType::PEN, MoneyType::USD);
                    })();

                    return $spending;
                }),
            ];
        })->toArray(), null);

        return $spendingsInSpan;
    }

    private function createTable():array
    {
        $spendings = $this->getWorkersData();
        $invoices = $this->getInvoicesData();



        $jobs = collect([]);


        foreach ($invoices as $invoice){
            $job = $jobs->where('job_code', '=', $invoice['job_code'])->first();
            if (!$job){
                $job = [
                    'job_code' => $invoice['job_code'],
                    'invoices' => [],
                    'spendings' => [],
                ];
                $jobs->push($job);

                $job = $jobs->where('job_code', '=', $invoice['job_code'])->first();
            }
            $job['invoices'] = collect($job['invoices'])->merge($invoice['invoices']);
            $jobs = $jobs->map(function($jobInJobs) use ($job){
                if ($jobInJobs['job_code'] === $job['job_code']){
                    return $job;
                }
                return $jobInJobs;
            });
        }

        foreach ($spendings as $spending){
            $job = $jobs->where('job_code', '=', $spending['job_code'])->first();
            if (!$job){
                $job = [
                    'job_code' => $spending['job_code'],
                    'invoices' => [],
                    'spendings' => [],
                ];
                $jobs->push($job);

                $job = $jobs->where('job_code', '=', $spending['job_code'])->first();
            }

            $job['spendings'] = collect($job['spendings'])->merge($spending['spendings']);
            $jobs = $jobs->map(function($jobInJobs) use ($job){
                if ($jobInJobs['job_code'] === $job['job_code']){
                    return $job;
                }
                return $jobInJobs;
            });
        }


        $jobs = $jobs->map(function($job){
            $templateReturn = [
                'job_code' => $job['job_code'],
                'invoices' => [
                    'count' => 0,
                    'amount' => [
                        'PEN' => 0,
                        'USD' => 0,
                    ]
                ],
                'workers' => [
                    'count' => 0,
                    'amount' => [
                        'PEN' => 0,
                        'USD' => 0,
                    ]
                ],
                'totals' => [
                    'count' => 0,
                    'amount' => [
                        'PEN' => 0,
                        'USD' => 0,
                    ]
                ],
            ];

            $templateReturn['invoices']['count'] = count($job['invoices']);
            $templateReturn['invoices']['amount']['PEN'] = collect($job['invoices'])->sum(function($invoice){
                return $invoice->amountInSoles();
            });
            $templateReturn['invoices']['amount']['USD'] = collect($job['invoices'])->sum(function($invoice){
                return $invoice->amountInDollars();
            });
            $templateReturn['workers']['count'] = count($job['spendings']);
            $templateReturn['workers']['amount']['PEN'] = collect($job['spendings'])->sum(function($spending){
                return $spending->amountInSoles;
            });
            $templateReturn['workers']['amount']['USD'] = collect($job['spendings'])->sum(function($spending){
                return $spending->amountInDollars;
            });
            $templateReturn['totals']['count'] = $templateReturn['invoices']['count'] + $templateReturn['workers']['count'];
            $templateReturn['totals']['amount']['PEN'] = $templateReturn['invoices']['amount']['PEN'] + $templateReturn['workers']['amount']['PEN'];
            $templateReturn['totals']['amount']['USD'] = $templateReturn['invoices']['amount']['USD'] + $templateReturn['workers']['amount']['USD'];

            return $templateReturn;
        });

        $body = collect($jobs)->map(function($job){
            return [
                'job_code' => $job['job_code'],
                'invoices' => $job['invoices']['amount']['USD'],
                'workers' => $job['workers']['amount']['USD'],
                'total_dollars' => $job['totals']['amount']['USD'],
                'total_soles' => $job['totals']['amount']['PEN'],
            ];
        });

        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'Job',
                    'key' => 'job_code',
                ],
                [
                    'title' => 'Trabajadores ($)',
                    'key' => 'workers',
                ],
                [
                    'title' => 'Boletas/Facturas ($)',
                    'key' => 'invoices',
                ],
                [
                    'title' => 'Total Dólares ($)',
                    'key' => 'total_dollars',
                ],
                [
                    'title' => 'Total Soles (S/.)',
                    'key' => 'total_soles',
                ]
            ],
            'body' => $body,
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'startDate' => $this->startDate->format('c'),
                'endDate' => $this->endDate->format('c'),
                'jobRegion' => $this->jobRegion,
                'expenseCode' => $this->expenseCode
            ],
        ];
    }
}