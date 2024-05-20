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
use App\Support\Exchange\Exchanger;
use App\Helpers\Enums\MoneyType;


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

    private function getUserInvoicesCosts(): array
    {
        $outputList = [];

        $reports = Report::with('invoices', 'user')
            ->where('status', ReportStatus::Approved)
            ->orWhere('status', ReportStatus::Restituted)
            ->when($this->userId !== null, function ($query) {
                return $query->where('user_id', $this->userId);
            })
            ->get();

        foreach ($reports as $report) {
            foreach ($report->invoices as $invoice) {
                // Filter invoices based on conditions. Is the same of:
                /*
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
                */

                if (   ($this->startDate === null || Carbon::parse($invoice->date)->format('Y-m-d') >= $this->startDate->format('Y-m-d'))
                    && ($this->endDate === null || Carbon::parse($invoice->date)->format('Y-m-d') <= $this->endDate->format('Y-m-d'))
                    && ($this->jobCode === null || $invoice->job_code === $this->jobCode)
                    && ($this->expenseCode === null || $invoice->expense_code === $this->expenseCode)
                    && ($this->type === null || $this->type === 'Invoices' || $invoice->type === $this->type)) {

                    $invoiceData = [
                        'id' => $invoice->id,
                        'date' => $invoice->date,
                        'description' => $invoice->description,
                        'type' => $invoice->type,
                        'user' => [
                            'id' => $report->user->id,
                            'name' => $report->user->name,
                            'username' => $report->user->username,
                        ],
                        'user_name' => $report->user->name,
                        'job_code' => $invoice->job_code,
                        'expense_code' => $invoice->expense_code,
                        'amount_in_soles' => $invoice->amountInSoles(),
                        'amount_in_dollars' => $invoice->amountInDollars(),
                    ];

                    $outputList[] = $invoiceData;
                }
            }
        }

        return $outputList;
    }

    private function getUserWorkersCosts():array
    {
        //TODO: Refactor to use Eagle Loading in the future, on the getWorkersSpendings
        $workersSpendings = collect(WorkersAssistant::getWorkersSpendings())->map(function($workerSpendings){
            return $workerSpendings['spendings'];
        })->flatten(1);

        $spendingsInSpan = $workersSpendings->where('date_day', '>=', $this->startDate->format('Y-m-d'))->where('date_day', '<=', $this->endDate->format('Y-m-d'));

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
            return $spending['amount'] > 0;
        })
        ->map(function($spending){
            $spending = Toolbox::toObject($spending);
            $spending->amountInSoles = (function() use ($spending){
                return $spending->amount;
            })();
            $spending->amountInDollars = (function() use ($spending){
                $date = new DateTime($spending->date);
                return Exchanger::on($date)->convert($spending->amount,MoneyType::PEN, MoneyType::USD);
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
