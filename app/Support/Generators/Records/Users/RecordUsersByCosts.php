<?php

namespace App\Support\Generators\Records\Users;

use App\Helpers\Enums\AttendanceStatus;
use App\Helpers\Toolbox;
use App\Models\AttendanceDayWorker;

use App\Support\Assistants\WorkersAssistant;
use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Invoice;
use Brunoinds\SunatDolarLaravel\Exchange;
use App\Models\Report;
use App\Helpers\Enums\ReportStatus;
use App\Models\User;


class RecordUsersByCosts
{

    private DateTime $startDate;
    private DateTime $endDate;
    private string|null $jobCode = null;
    private string|null $expenseCode = null;
    private string|null $type = null;
    private string|null $userId = null;
    
    /**
     * @param array $options
     * @param DateTime $options['startDate']
     * @param DateTime $options['endDate']
     * @param null|string $options['jobCode']
     * @param null|string $options['expenseCode']
     * @param null|string $options['type']
     * @param null|string $options['userId']
     */
    
    public function __construct(array $options){
        $this->startDate = $options['startDate'];
        $this->endDate = $options['endDate'];
        $this->jobCode = $options['jobCode'];
        $this->expenseCode = $options['expenseCode'];
        $this->type = $options['type'];
        $this->userId = $options['userId'];
    }

    private function getUserInvoicesCosts():array
    {
        $instance = $this;
        $outputList = collect([]);

        $query = Report::query()->where('status', '=', ReportStatus::Approved)->orWhere('status', '=', ReportStatus::Restituted);

        if ($this->userId !== null){
            $query = $query->where('user_id', '=', $this->userId);
        }

        $reports = $query->get();

        $reports->each(function(Report $report) use (&$outputList, $instance){
            $invoices = $report->invoices()->where('date', '>=', $instance->startDate->format('c'))->where('date', '<=', $instance->endDate->format('c'));

            if ($instance->jobCode !== null){
                $invoices = $invoices->where('job_code', '=', $instance->jobCode);
            }

            if ($instance->expenseCode !== null){
                $invoices = $invoices->where('expense_code', '=', $instance->expenseCode);
            }

            if ($instance->type !== null && $instance->type !== 'Invoices'){
                $invoices = $invoices->where('type', '=', $instance->type);
            }

            $invoices = $invoices->get();


            $invoices->each(function(Invoice $invoice) use (&$outputList, $report){
                $invoiceData = [
                    'id' => $invoice->id,
                    'date' => $invoice->date,
                    'description' => $invoice->description,
                    'type' => $invoice->type,
                    'user' => [
                        'id' => $report->user()->get()->first()->id,
                        'name' => $report->user()->get()->first()->name,
                        'username' => $report->user()->get()->first()->username,
                    ],
                    'user_name' => $report->user()->get()->first()->name,
                    'job_code' => $invoice->job_code,
                    'expense_code' => $invoice->expense_code,
                    'amount_in_soles' => $invoice->amountInSoles(),
                    'amount_in_dollars' => $invoice->amountInDollars(),
                ];
                $outputList->push($invoiceData);
            });
        });

        return $outputList->toArray();
    }
    private function getUserWorkersCosts():array
    {
        $workersSpendings = collect(WorkersAssistant::getWorkersSpendings())->map(function($workerSpendings){
            return $workerSpendings['spendings'];
        })->flatten(1);

        $spendingsInSpan = $workersSpendings->where('date', '>=', $this->startDate->format('c'))->where('date', '<=', $this->endDate->format('c'));

        if ($this->jobCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('job.code', '=', $this->jobCode);
        }
        
        if ($this->expenseCode !== null){
            $spendingsInSpan = $spendingsInSpan->where('expense.code', '=', $this->expenseCode);
        }

        if ($this->type !== null && $this->type !== 'Workers'){
            $spendingsInSpan = collect([]);
        }

        if ($this->userId !== null){
            $spendingsInSpan = $spendingsInSpan->where('attendance.user_id', '=', $this->userId);
        }


        $spendings = collect($spendingsInSpan)
        ->filter(function($spending){
            //Should filter all spendings with > 0 amount
            return $spending['amount'] > 0;
        })
        ->map(function($spending){
            $spending = Toolbox::toObject($spending);
            $spending->amountInSoles = (function() use ($spending){
                return $spending->amount;
            })();
            $spending->amountInDollars = (function() use ($spending){
                $date = new DateTime($spending->date);
                return Exchange::on($date)->convert(\Brunoinds\SunatDolarLaravel\Enums\Currency::PEN, $spending->amount)->to(\Brunoinds\SunatDolarLaravel\Enums\Currency::USD);
            })();
            return $spending;
        })->map(function($item){
            $itemData = [
                'id' => $item->attendance_day->id,
                'date' => $item->attendance_day->date,
                'description' => 'Mano de obra de "' . $item->worker->name . '" (DNI: ' . $item->worker->dni . '), en el trabajo "JOB: ' . $item->job->code . '"',
                'type' => 'Worker',
                'user' => [
                    'id' => $item->attendance->user_id,
                    'name' => User::find($item->attendance->user_id)->name,
                    'username' => User::find($item->attendance->user_id)->username,
                ],
                'user_name' => User::find($item->attendance->user_id)->name,
                'job_code' => $item->job->code,
                'expense_code' => $item->expense->code,
                'amount_in_soles' => $item->amountInSoles,
                'amount_in_dollars' => $item->amountInDollars,
            ];
            return $itemData;
        });

        
        return $spendings->toArray();
    }

    private function createTable():array{
        $spendings = collect($this->getUserWorkersCosts());
        $invoices = collect($this->getUserInvoicesCosts());

        $body = $spendings->merge($invoices)->sortByDesc('date')->toArray();

        $body = array_column($body, null);


        return [
            'headers' => [
                [
                    'title' => 'Fecha',
                    'key' => 'date',
                ],
                [
                    'title' => 'Usuário',
                    'key' => 'user_name',
                ],
                [
                    'title' => 'Job',
                    'key' => 'job_code',
                ],
                [
                    'title' => 'Expense',
                    'key' => 'expense_code',
                ],
                [
                    'title' => 'Tipo',
                    'key' => 'type',
                ],
                [
                    'title' => 'Descripción',
                    'key' => 'description',
                ],
                [
                    'title' => 'Total Soles (S/.)',
                    'key' => 'amount_in_soles',
                ],
                [
                    'title' => 'Total Dólares ($)',
                    'key' => 'amount_in_dollars',
                ],
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
                'jobCode' => $this->jobCode,
                'expenseCode' => $this->expenseCode,
                'type' => $this->type,
                'userId' => $this->userId,
            ],
        ];
    }
}