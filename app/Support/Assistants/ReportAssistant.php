<?php

namespace App\Support\Assistants;

use App\Models\Report;
use \avadim\FastExcelWriter\Excel;

class ReportAssistant{
    public static function generateExcelDocument(Report $report): Excel{
        $invoices = $report->invoices()->orderBy('date', 'asc')->get();

        $user = $report->user()->get()->first();

        $excel = Excel::create([$report->title]);
        $sheet = $excel->getSheet();

        $invoicesPeriod = (function() use ($invoices){
            //Get first and last invoice dates:
            $firstInvoice = $invoices->first();
            $lastInvoice = $invoices->last();

            $firstInvoiceDate = strtotime($firstInvoice->date);
            $lastInvoiceDate = strtotime($lastInvoice->date);

            return 'del ' . date('d/m/y', $firstInvoiceDate) . ' hasta el ' . date('d/m/y', $lastInvoiceDate);
        })();
        $currency = $report->money_type;


        $sheet->writeRow(['MARANATHA'], [
            'font' => [
                'style' => 'bold',
                'size' => 20,
            ],
            'text-align' => 'center',
            'vertical-align' => 'center',
            'height' => 24,
        ]);
        $sheet->writeRow(['EXPENSE REPORT'], [
            'font' => [
                'style' => 'bold',
                'size' => 14,
            ],
            'text-align' => 'center',
            'vertical-align' => 'center',
            'height' => 24,
        ]);
        $sheet->writeRow(['Country - Peru'], [
            'font' => [
                'size' => 12,
            ],
            'text-align' => 'center',
            'vertical-align' => 'center',
            'height' => 24,
        ]);
        $sheet->writeRow(['Report Dates: ' . $invoicesPeriod], [
            'font' => [
                'size' => 12,
            ],
            'text-align' => 'center',
            'vertical-align' => 'center',
            'height' => 24,
        ]);
        $sheet->writeRow(['Submitted by: ' . $user->name], [
            'font' => [
                'size' => 12,
            ],
            'text-align' => 'center',
            'vertical-align' => 'center',
            'height' => 24,
        ]);
        $sheet->writeRow(['Currency: ' . $currency->value], [
            'font' => [
                'size' => 12,
            ],
            'text-align' => 'center',
            'vertical-align' => 'center',
            'height' => 24,
        ]);

        $sheet->mergeCells("A1:G1");
        $sheet->mergeCells("A2:G2");
        $sheet->mergeCells("A3:G3");
        $sheet->mergeCells("A4:G4");
        $sheet->mergeCells("A5:G5");
        $sheet->mergeCells("A6:G6");


        $sheet->writeRow(['DATE', 'INVOICE/TICKET', 'INVOICE/TICKET DESCRIPTION', 'JOB', 'EXPENSE CODE', '#', 'TOTAL'], [
            'font' => [
                'style' => 'bold'
            ],
            'fill_color' => '#ebebeb',
            'text-align' => 'center',
            'vertical-align' => 'center',
            'border' => 'thin',
            'height' => 24,
        ]);

        $sheet->setColOptions('A', ['width' => 'auto']);
        $sheet->setColOptions('B', ['width' => 'auto']);
        $sheet->setColOptions('C', ['format' => '@string', 'width' => 'auto']);
        $sheet->setColOptions('D', ['width' => 'auto']);
        $sheet->setColOptions('E', ['width' => 'auto']);
        $sheet->setColOptions('F', ['format' => '@integer', 'width' => 'auto']);
        $sheet->setColOptions('G', ['format' => '0.00', 'width' => 'auto']);

        $i = 7;

        //iterate from 0 to 28:
        for ($j = 0; $j < 28; $j++){
            if (!isset($invoices[$j])){
                $sheet->writeRow([
                    '',
                    '',
                    '',
                    '',
                    '',
                    ($j + 1),
                    ''
                ], [
                    'text-align' => 'center',
                    'vertical-align' => 'center',
                    'border' => 'thin',
                    'height' => 24,
                ]);
                $i++;
                continue;
            }
            $invoice = $invoices[$j];

            $dateFormatedLocal = date('d/m/Y', strtotime($invoice->date));

            $sheet->writeRow([
                $dateFormatedLocal,
                $invoice->ticket_number,
                $invoice->description,
                $invoice->job_code,
                $invoice->expense_code,
                ($j + 1),
                $invoice->amount,
            ], [
                'text-align' => 'center',
                'vertical-align' => 'center',
                'border' => 'thin',
                'height' => 24,
            ]);
            $i++;
        }


        $totalsCellIndex = $i + 1;
        $sheet->writeRow([
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            "=SUM(G2:G$i)"
        ], [
            'font' => [
                'style' => 'bold',
                'size' => 12,
            ],
            'text-align' => 'center',
            'vertical-align' => 'center',
            'border' => 'thin',
            'height' => 24,
        ]);
        $sheet->mergeCells("A$totalsCellIndex:F$totalsCellIndex");

        $sheet->setColWidths(['A' => 15, 'B' => 16, 'C' => 40, 'D' => 10, 'E' => 15, 'F' => 10, 'G' => 20]);

        return $excel;
    }
}