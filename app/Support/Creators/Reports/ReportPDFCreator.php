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
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Illuminate\Support\Str;
use App\Models\Job;

class CustomPDF extends \setasign\Fpdi\Fpdi {
    function RoundedRect($x, $y, $w, $h, $r, $corners = '1234', $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));

        $xc = $x+$w-$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));
        if (strpos($corners, '2')===false)
            $this->_out(sprintf('%.2F %.2F l', ($x+$w)*$k,($hp-$y)*$k ));
        else
            $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);

        $xc = $x+$w-$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        if (strpos($corners, '3')===false)
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-($y+$h))*$k));
        else
            $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);

        $xc = $x+$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        if (strpos($corners, '4')===false)
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-($y+$h))*$k));
        else
            $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);

        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        if (strpos($corners, '1')===false)
        {
            $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$y)*$k ));
            $this->_out(sprintf('%.2F %.2F l',($x+$r)*$k,($hp-$y)*$k ));
        }
        else
            $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }
    protected $extgstates = array();

    // alpha: real value from 0 (transparent) to 1 (opaque)
    // bm:    blend mode, one of the following:
    //          Normal, Multiply, Screen, Overlay, Darken, Lighten, ColorDodge, ColorBurn,
    //          HardLight, SoftLight, Difference, Exclusion, Hue, Saturation, Color, Luminosity
    function SetAlpha($alpha, $bm='Normal')
    {
        // set alpha for stroking (CA) and non-stroking (ca) operations
        $gs = $this->AddExtGState(array('ca'=>$alpha, 'CA'=>$alpha, 'BM'=>'/'.$bm));
        $this->SetExtGState($gs);
    }

    function AddExtGState($parms)
    {
        $n = count($this->extgstates)+1;
        $this->extgstates[$n]['parms'] = $parms;
        return $n;
    }

    function SetExtGState($gs)
    {
        $this->_out(sprintf('/GS%d gs', $gs));
    }

    function _enddoc()
    {
        if(!empty($this->extgstates) && $this->PDFVersion<'1.4')
            $this->PDFVersion='1.4';
        parent::_enddoc();
    }

    function _putextgstates()
    {
        for ($i = 1; $i <= count($this->extgstates); $i++)
        {
            $this->_newobj();
            $this->extgstates[$i]['n'] = $this->n;
            $this->_put('<</Type /ExtGState');
            $parms = $this->extgstates[$i]['parms'];
            $this->_put(sprintf('/ca %.3F', $parms['ca']));
            $this->_put(sprintf('/CA %.3F', $parms['CA']));
            $this->_put('/BM '.$parms['BM']);
            $this->_put('>>');
            $this->_put('endobj');
        }
    }

    function _putresourcedict()
    {
        parent::_putresourcedict();
        $this->_put('/ExtGState <<');
        foreach($this->extgstates as $k=>$extgstate)
            $this->_put('/GS'.$k.' '.$extgstate['n'].' 0 R');
        $this->_put('>>');
    }

    function _putresources()
    {
        $this->_putextgstates();
        parent::_putresources();
    }
}



class ReportPDFCreator
{
    private $html = '';

    private Report $report;

    private array $pdfsToMerge = [];

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

            //Check if invoice has provider and append in parenthesis:
            if ($invoice->provider){
                $invoiceDescription .= ' - ' . $invoice->provider;
            }


            $jobCode = ReportAssistant::jobCodeOverride(Job::sanitizeCode($invoice->job_code));

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
            $pdfId = $invoice->pdf;
            if (!$imageId && !$pdfId){
                return;
            }

            if ($imageId){
                $path = 'invoices/' . $imageId;
                $imageExists = Storage::disk('public')->exists($path);
                if (!$imageExists){
                    return;
                }
                $srcUrl = Storage::disk('public')->path($path);
                $listSrcs[] = [
                    'src' =>  'data:image/png;base64,' . base64_encode(file_get_contents($srcUrl)),
                    'invoice' => $invoice,
                    'type' => 'image'
                ];
            }

            if ($pdfId){
                $path = 'invoices/' . $pdfId;
                $pdfExists = Storage::disk('public')->exists($path);
                if (!$pdfExists){
                    return;
                }
                $srcUrl = Storage::disk('public')->path($path);
                $listSrcs[] = [
                    'src' => $srcUrl,
                    'invoice' => $invoice,
                    'type' => 'pdf'
                ];
            }

        });

        $imagesItemsHtml = '';
        $realImagesAdded = 0;
        collect($listSrcs)->each(function($item, $i) use (&$imagesItemsHtml, $instance, &$realImagesAdded){
            $invoice = $item['invoice'];
            $fileSrc = $item['src'];
            $jobName = $invoice->job?->name;
            $amount = Toolbox::moneyPrefix($instance->report->money_type->value) . ' ' . number_format($invoice->amount, 2);
            $invoiceDescription = $invoice->description;

            //Check if is invoice with multiples Jobs, by brackets:
            $hasKeysWithTextInside = preg_match('/\[(.*?)\]/', $invoiceDescription, $matches);
            if ($hasKeysWithTextInside){
                $invoiceDescription = str_replace($matches[0], "($amount)", $invoiceDescription);
            }

            if ($item['type'] === 'pdf'){
                $instance->pdfsToMerge[] = [
                    'type' => 'pdf',
                    'src' => $fileSrc,
                    'invoice' => $invoice,
                    'text' => $jobName.' '.Job::sanitizeCode($invoice->job_code) . ' - '.$invoice->expense_code . "\n" .$invoiceDescription
                ];
            }else{
                $imagesItemsHtml .= '
                    <article>
                        <h1>'.$jobName.' '.Job::sanitizeCode($invoice->job_code) . ' - '.$invoice->expense_code . '<br> '.$invoiceDescription.'</h1>
                        <img src="'.$fileSrc.'">
                    </article>
                ';

                $instance->pdfsToMerge[] = [
                    'type' => 'img',
                    'index' => $realImagesAdded + (2),
                ];
                $realImagesAdded++;
            }
        });

        $this->html = str_replace('{{$imagesPages}}', $imagesItemsHtml, $this->html);
    }
    private function loadPdfPages(string $temporaryPdfPath)
    {
        if (collect($this->pdfsToMerge)->filter(fn($item) => $item['type'] === 'pdf')->count() === 0){
            return $temporaryPdfPath;
        }

        $basePdfPath = $temporaryPdfPath;
        $toMergeData = $this->pdfsToMerge;

        $newPdf = new CustomPDF();
        $currentPage = 1;




        //Adding first page
        $newPdf->AddPage();
        $newPdf->setSourceFile($basePdfPath);
        $tplIdx = $newPdf->importPage(1);
        $newPdf->useTemplate($tplIdx);
        $currentPage++;


        $toMergeDatIndex = 0;
        foreach ($toMergeData as $mergeData){
            if ($mergeData['type'] == 'img'){
                $newPdf->AddPage();
                $newPdf->setSourceFile($basePdfPath);
                $tplIdx = $newPdf->importPage($mergeData['index']);
                $newPdf->useTemplate($tplIdx);
                $currentPage++;
            }else{
                $otherPdf = $mergeData['src'];
                $otherPageCount = $newPdf->setSourceFile($otherPdf);
                for ($otherPageNo = 1; $otherPageNo <= $otherPageCount; $otherPageNo++) {
                    $newPdf->AddPage();
                    $tplIdx = $newPdf->importPage($otherPageNo);
                    $templateSize = $newPdf->getTemplateSize($tplIdx);


                    if ($templateSize['orientation'] === 'L'){
                        $newPdf->useTemplate(tpl: $tplIdx, adjustPageSize: true);
                    }else{
                        //Add into page without adjust, but make it centered, so we need to calculate the position:
                        $x = 0;
                        $y = 0;
                        $w = $templateSize['width'];
                        $h = $templateSize['height'];



                        $x = ($newPdf->GetPageWidth() - $w) / 2;
                        $y = ($newPdf->GetPageHeight() - $h) / 2;

                        if ($newPdf->GetPageHeight() < $h){
                            //If the old page height is bigger than the default new pdf page, we should adjust the height of the new pdf page to maximum height of the old page:
                            $y = 0;
                            $newPdf->setPageFormat([
                                'width' => $newPdf->GetPageWidth(),
                                'height' => $h,
                                0 => $newPdf->GetPageWidth(),
                                1 => $h,
                                'orientation' => 'P'
                            ], 'P');
                        }

                        $newPdf->useTemplate(tpl: $tplIdx, x: $x, y: $y, width: $w, height: $h);
                    }





                    $newPdf->SetFont('Helvetica');
                    $newPdf->SetFont('Helvetica', 'B', 9);
                    $newPdf->SetTextColor(255, 0, 0);

                    collect(explode("\n", $mergeData['text']))->each(function($line, $i) use ($newPdf){
                        $newPdf->SetXY(10, 10 + ($i * 3.4));

                        $line = iconv('UTF-8', 'cp1250', $line);

                        $newPdf->Write(0, $line);
                    });

                    $textSize = $newPdf->GetStringWidth($mergeData['text']);
                    $textSizeHeight = 3.4 * count(explode("\n", $mergeData['text']));
                    $newPdf->SetAlpha(0.8);
                    $newPdf->SetFillColor(255,255,255);
                    $newPdf->SetDrawColor(255,255,255);
                    $newPdf->RoundedRect(10, 7.6, $textSize, $textSizeHeight + 2, 2, '1234', 'DF');
                    $newPdf->SetAlpha(1);

                    collect(explode("\n", $mergeData['text']))->each(function($line, $i) use ($newPdf){
                        $newPdf->SetXY(10, 10 + ($i * 3.4));
                        $line = iconv('UTF-8', 'cp1250', $line);
                        $newPdf->Write(0, $line);
                    });
                    $currentPage++;
                }
            }

            $toMergeDatIndex++;
        }

        $newPdf->Output($temporaryPdfPath, 'F');

        return $temporaryPdfPath;
    }


    public function create($options = []) : string
    {
        $opts = new \Dompdf\Options();
        $opts->set('isRemoteEnabled', true);
        $opts->set('isHtml5ParserEnabled', true);
        $opts->set('chroot', base_path('public') . '/storage/');

        $dompdf = new Dompdf($opts);

        $totalFramesToRenderCount = 0;
        $finishedFramesToRenderCount = 0;

        if (isset($options['progressId'])){
            $onProgress = function($percentage, $framesRendered, $framesTotal) use ($options){
                $progressItem = [
                    'percentage' => $percentage,
                    'framesRendered' => $framesRendered,
                    'framesTotal' => $framesTotal
                ];
                Cache::store('redis')->put('Maranatha/PDFRender/Progress/' . $options['progressId'], json_encode($progressItem));
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


        $content = $dompdf->output();
        $temporaryDirectory = (new TemporaryDirectory())->create();
        $documentName = Str::uuid() . '.pdf';
        $tempPath = $temporaryDirectory->path($documentName);
        file_put_contents($tempPath, $content);

        $tempPath = $this->loadPdfPages($tempPath);

        return file_get_contents($tempPath);
    }

    public static function new(Report $report)
    {
        return new self($report);
    }
}
