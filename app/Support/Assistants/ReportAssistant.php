<?php

namespace App\Support\Assistants;

use App\Models\Report;
use \avadim\FastExcelWriter\Excel;

class ReportAssistant{
    public static function generateExcelDocument(Report $report): Excel{
        $invoices = $report->invoices()->get();


        $excel = Excel::create([$report->title]);
        $sheet = $excel->getSheet();
        $sheet->writeHeader(['DATE', 'INVOICE/TICKET', 'INVOICE/TICKET DESCRIPTION', 'JOB', 'EXPENSE CODE', '#', 'TOTAL'], [
            'font' => [
                'style' => 'bold'
            ],
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

        $i = 1;
        foreach($invoices as $invoice){
            $dateFormatedLocal = date('d/M/y', strtotime($invoice->date));

            $sheet->writeRow([
                $dateFormatedLocal,
                $invoice->ticket_number,
                $invoice->description,
                $invoice->job_code,
                $invoice->expense_code,
                $i,
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
                'style' => 'bold'
            ],
            'text-align' => 'center',
            'vertical-align' => 'center',
            'border' => 'thin',
            'height' => 24,
        ]);
        $sheet->mergeCells("A$totalsCellIndex:F$totalsCellIndex");


        return $excel;
    }
}