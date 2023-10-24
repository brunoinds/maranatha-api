<?php

namespace App\Support\Generators;

use App\Models\Report;
use App\Models\Invoice;
use Carbon\Carbon;


class ReportGenerator{
    public static function generateExcelOutput(){
        $outputList = collect([]);


        Report::all()->where('status', '=', 'Submitted')->each(function(Report $report) use (&$outputList){
            $reportAmount = $report->amount();
            $reportUsername = $report->user()->get()->first()->username;
            $reportDate = Carbon::parse($report->to_date)->format('d/m/Y');
            $report->invoices()->each(function(Invoice $invoice) use (&$outputList, $reportAmount, $reportUsername, $reportDate){
                $invoiceTypeAbbreviationShort = $invoice->type === 'Facture' ? 'FT' : 'BV';
                $invoiceTypeAbbreviation = $invoice->type === 'Facture' ? 'FACTURAS' : 'BOLETAS';

                $invoiceDate = Carbon::parse($invoice->date)->format('d/m/Y');
                $invoiceData = [
                    'identifier' => $reportUsername . '-' . $invoiceTypeAbbreviationShort . '-' . $invoiceDate  . '-' . $invoice->amount,
                    'consumption_date' => $invoiceDate,
                    'creation_date' => Carbon::parse($invoice->created_at)->format('d/m/Y'),
                    'type' => $invoiceTypeAbbreviation,
                    'description' => $invoice->description,
                    'user' => $reportUsername,
                    'report' => [
                        'identifier' => $reportUsername . '-' . $invoiceTypeAbbreviationShort . '-' . $reportDate  . '-' . $reportAmount,
                        'amount' => number_format($reportAmount, 2),
                        'date' => $reportDate,
                    ],
                    'ticket_number' => $invoice->ticket_number,
                    'commerce_number' => $invoice->commerce_number,
                    'job_code' => $invoice->job_code,
                    'expense_code' => $invoice->expense_code,
                    'amount' => number_format($invoice->amount, 2),
                ];
                $outputList->push($invoiceData);
            });
        });
        return $outputList;
    }
}