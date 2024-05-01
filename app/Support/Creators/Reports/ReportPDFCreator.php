<?php


namespace App\Support\Creators\Reports;

use App\Helpers\Toolbox;
use App\Models\Report;
use App\Support\Assistants\ReportAssistant;
use DateTime;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;


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

            $jobCode = ReportAssistant::jobCodeOverride($invoice->job_code);

            $invoicesItemsHtml .= "<tr>
                <td>$date</td>
                <td>$invoice->ticket_number</td>
                <td>$invoiceDescription</td>
                <td>$jobCode</td>
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
            $srcUrl = Storage::disk('public')->path($path);
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




    public function create($options = []) : Dompdf
    {
        $dompdf = new Dompdf();
        $dompdf->getOptions()->setChroot([base_path('public') . '/storage/']);

        $totalFramesToRenderCount = 0;
        $finishedFramesToRenderCount = 0;



        if (isset($options['progressId'])){
            $onProgress = function($percentage, $framesRendered, $framesTotal) use ($options){
                $progressItem = [
                    'percentage' => $percentage,
                    'framesRendered' => $framesRendered,
                    'framesTotal' => $framesTotal
                ];
                Cache::store('file')->put('Maranatha/PDFRender/Progress/' . $options['progressId'], json_encode($progressItem));
            };
            $dompdf->setCallbacks([
                'checksum' => [
                    'event' => 'end_frame',
                    'f' => function ($frame) use (&$finishedFramesToRenderCount, &$totalFramesToRenderCount, &$onProgress) {
                        $finishedFramesToRenderCount++;

                        if ($totalFramesToRenderCount === 0 || $finishedFramesToRenderCount > $totalFramesToRenderCount){
                            return;
                        }
                        $onProgress($finishedFramesToRenderCount / $totalFramesToRenderCount * 100, $finishedFramesToRenderCount, $totalFramesToRenderCount);
                    }
                ],
                'checksum2' => [
                    "event" => "begin_frame",
                    "f" => function ($frame) use (&$dompdf, &$totalFramesToRenderCount){
                        if ($totalFramesToRenderCount !== 0){
                            return;
                        }
                        $reflection = new \ReflectionClass($dompdf);
                        $property = $reflection->getProperty('tree');
                        $property->setAccessible(true);
                        $tree = $property->getValue($dompdf);

                        foreach ($tree as $frame) {
                            $totalFramesToRenderCount++;
                        }
                    }
                ]
            ]);
        }



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
