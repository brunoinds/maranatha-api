<?php


namespace App\Support\Creators\Reports;

use App\Helpers\Toolbox;
use App\Models\Report;
use DateTime;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;


class ReportPDFCreator
{
    private $html = '';

    private Report $report;

    private function __construct(Report $report)
    {
        $this->report = $report;
        $this->loadTemplate($report);
        $this->loadPlaceholders($report);
        $this->loadInvoicesItemsInTable();
        $this->loadImagesPages();
    }


    private function loadTemplate()
    {
        $this->html = file_get_contents(base_path('app/Support/Creators/Reports/PDFTemplate.html'));
    }
    private function loadPlaceholders(Report $report)
    {
        $firstInvoiceDate = $report->firstInvoiceDate();
        $lastInvoiceDate = $report->lastInvoiceDate();

        if ($firstInvoiceDate && $lastInvoiceDate){
            $carbonStart = Carbon::create($firstInvoiceDate);
            $carbonEnd = Carbon::create($lastInvoiceDate);
            $dates = "from " . $carbonStart->format('F j, Y') . " to " . $carbonEnd->format('F j, Y');
        }else {
            $carbonStart = Carbon::create($report->from_date);
            $carbonEnd = Carbon::create($report->to_date);
            $dates = "from " . $carbonStart->format('F j, Y') . " to " . $carbonEnd->format('F j, Y');
        }


        $this->html = str_replace('{{$country}}', Toolbox::countryName($report->country), $this->html);
        $this->html = str_replace('{{$reportDates}}', $dates, $this->html);
        $this->html = str_replace('{{$submittedBy}}', $report->user()->get()->first()->name, $this->html);
        $this->html = str_replace('{{$currency}}', $report->money_type->value, $this->html);
        $this->html = str_replace('{{$tableTotals}}',  Toolbox::moneyPrefix($report->money_type->value) . ' ' . number_format($report->amount(), 2), $this->html);
    }
    private function loadInvoicesItemsInTable()
    {
        $invoicesItemsHtml = '';
        $report = $this->report;
        $this->report->invoices()->orderBy('date', 'asc')->each(function($invoice, $i) use (&$invoicesItemsHtml, $report){
            $iteration = ($i + 1);

            $date = Carbon::create($invoice->date)->format('d/m/y');

            $amount = Toolbox::moneyPrefix($report->money_type->value) . ' ' . number_format($invoice->amount, 2);

            $invoiceDescription = $invoice->description;

            //Check if is invoice with multiples Jobs, by brackets:
            $hasKeysWithTextInside = preg_match('/\[(.*?)\]/', $invoiceDescription, $matches);
            if ($hasKeysWithTextInside){
                $invoiceDescription = str_replace($matches[0], "($amount)", $invoiceDescription);
            }


            $invoicesItemsHtml .= "<tr>
                <td>$date</td>
                <td>$invoice->ticket_number</td>
                <td>$invoiceDescription</td>
                <td>$invoice->job_code</td>
                <td>$invoice->expense_code</td>
                <td>$iteration</td>
                <td>$amount</td>
            </tr>";
        });

        $invoicesCount = $this->report->invoices()->count();
        $remainingRows = 28 - $invoicesCount;
        for($i = 0; $i < $remainingRows; $i++){
            $iteration = ($i + 1) + $invoicesCount;
            $invoicesItemsHtml .= "<tr>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td>$iteration</td>
                <td></td>
            </tr>";
        }

        $this->html = str_replace('{{$invoicesItems}}', $invoicesItemsHtml, $this->html);
    }
    private function loadImagesPages()
    {
        $listSrcs = [];
        $instance = $this;
        $this->report->invoices()->orderBy('date', 'asc')->each(function($invoice, $i) use (&$listSrcs){
            $imageId = $invoice->image;
            if (!$imageId){
                return;
            }
            $path = 'invoices/' . $imageId;
            $imageExists = Storage::disk('public')->exists($path);
            if (!$imageExists){
                return;
            }
            $image = Storage::disk('public')->get($path);
            $srcUrl = 'data:image/png;base64,' . base64_encode($image);
            $listSrcs[] = [
                'src' => $srcUrl,
                'invoice' => $invoice
            ];
        });

        $imagesItemsHtml = '';
        collect($listSrcs)->each(function($item, $i) use (&$imagesItemsHtml, $instance){
            $invoice = $item['invoice'];
            $imageSrc = $item['src'];

            $jobName = $invoice->job?->name;

            $amount = Toolbox::moneyPrefix($instance->report->money_type->value) . ' ' . number_format($invoice->amount, 2);


            $invoiceDescription = $invoice->description;

            //Check if is invoice with multiples Jobs, by brackets:
            $hasKeysWithTextInside = preg_match('/\[(.*?)\]/', $invoiceDescription, $matches);
            if ($hasKeysWithTextInside){
                $invoiceDescription = str_replace($matches[0], "($amount)", $invoiceDescription);
            }

            $imagesItemsHtml .= '
                <article>
                    <h1>'.$jobName.' '.$invoice->job_code . ' - '.$invoice->expense_code . '<br> '.$invoiceDescription.'</h1>
                    <img src="'.$imageSrc.'">
                </article>
            ';
        });

        $this->html = str_replace('{{$imagesPages}}', $imagesItemsHtml, $this->html);
    }




    public function create() : Dompdf
    {
        $dompdf = new Dompdf();
        $dompdf->loadHtml($this->html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf;
    }

    public static function new(Report $report)
    {
        return new self($report);
    }
}
