<?php

namespace App\Support\Generators\Records\Invoices;

use App\Helpers\Enums\AttendanceStatus;
use App\Helpers\Enums\ReportStatus;
use App\Helpers\Toolbox;
use App\Models\Report;

use App\Support\Assistants\WorkersAssistant;
use Illuminate\Support\Collection;
use DateTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Models\Invoice;
use Brunoinds\SunatDolarLaravel\Exchange;


class RecordInvoicesByItems
{

    private DateTime $startDate;
    private DateTime $endDate;
    private string|null $country = null;
    private string|null $moneyType = null;
    private string|null $invoiceType = null;
    private string|null $jobRegion = null;
    private string|null $expenseCode = null;
    private string|null $jobCode = null;

    /**
     * @param array $options
     * @param DateTime $options['startDate']
     * @param DateTime $options['endDate']
     * @param string $options['country']
     * @param string $options['moneyType']
     * @param string $options['invoiceType']
     * @param string|null $options['jobRegion']
     * @param string|null $options['expenseCode']
     * @param string|null $options['jobCode']
     */

    public function __construct(array $options){
        $this->startDate = $options['startDate'];
        $this->endDate = $options['endDate'];
        $this->country = $options['country'];
        $this->moneyType = $options['moneyType'];
        $this->invoiceType = $options['invoiceType'];
        $this->jobRegion = $options['jobRegion'];
        $this->expenseCode = $options['expenseCode'];
        $this->jobCode = $options['jobCode'];
    }

    private function getInvoicesData():Collection
    {
        //Get filtered reports data:

        $invoicesInSpan = Invoice::query()
                    ->with(['report', 'report.user'])
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

        if ($this->jobCode !== null){
            $invoicesInSpan = $invoicesInSpan->where('job_code', '=', $this->jobCode);
        }


        if ($this->country !== null){
            $invoicesInSpan = $invoicesInSpan->where('country', '=', $this->country);
        }

        if ($this->moneyType !== null){
            $invoicesInSpan = $invoicesInSpan->where('money_type', '=', $this->moneyType);
        }

        if ($this->invoiceType !== null){
            $invoicesInSpan = $invoicesInSpan->where('type', '=', $this->invoiceType);
        }

        $invoicesInSpan = $invoicesInSpan->get();

        return $invoicesInSpan;
    }

    private function createTable():array{
        $invoices = $this->getInvoicesData();


        $body = collect($invoices)->map(function($invoice){
            return [
                'report_type' => $invoice['type'] === 'Bill' ? 'Boleta' : 'Factura',
                'username' => $invoice['report']['user']['username'],
                'report_date' => Carbon::parse($invoice['report']['submitted_at'])->format('d/m/Y'),
                'invoice_date' => Carbon::parse($invoice['date'])->format('d/m/Y'),
                'ticket_number' => $invoice['ticket_number'],
                'invoice_description' => $invoice['description'],
                'job_code' => $invoice['job_code'],
                'expense_code' => $invoice['expense_code'],
                'invoice_amount' => number_format($invoice['amount'], 2),
                'report_amount' => number_format($invoice['report']->amount(), 2),
                'money_type' => $invoice['report']['money_type'],
                'country' => $invoice['country'],
            ];
        });

        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'Comprobante',
                    'key' => 'report_type',
                ],
                [
                    'title' => 'Usuario',
                    'key' => 'username',
                ],
                [
                    'title' => 'Fecha de reporte',
                    'key' => 'report_date',
                ],
                [
                    'title' => 'Fecha de comprobante',
                    'key' => 'invoice_date',
                ],
                [
                    'title' => 'Serie y número de comprobante',
                    'key' => 'ticket_number',
                ],
                [
                    'title' => 'Descripción',
                    'key' => 'invoice_description',
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
                    'title' => 'Monto comprobante',
                    'key' => 'invoice_amount',
                ],
                [
                    'title' => 'Monto reporte',
                    'key' => 'report_amount',
                ],
                [
                    'title' => 'Moneda',
                    'key' => 'money_type',
                ],
                [
                    'title' => 'País',
                    'key' => 'country',
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
                'country' => $this->country,
                'moneyType' => $this->moneyType,
                'invoiceType' => $this->invoiceType,
                'jobRegion' => $this->jobRegion,
                'expenseCode' => $this->expenseCode,
                'jobCode' => $this->jobCode,
            ],
        ];
    }
}
