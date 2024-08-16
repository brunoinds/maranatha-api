<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Codedge\Fpdf\Fpdf\Fpdf;
/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('check:environment', function () {
    $appEnvirontment = env('APP_ENV');
    $this->info('App environment: ' . $appEnvirontment);
})->purpose('Check the current environment');

Artisan::command('merge', function(){

    $files = [app_path('pdfs/pdf1.pdf'), app_path('pdfs/pdf2.pdf')];
    $pdf = new \setasign\Fpdi\Fpdi();

    foreach ($files as $file) {
        var_dump($file);
        $pageCount = $pdf->setSourceFile($file);
        // iterate through all pages
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            // import a page
            $templateId = $pdf->importPage($pageNo);
            // get the size of the imported page
            $size = $pdf->getTemplateSize($templateId);

            // create a page (landscape or portrait depending on the imported page size)
            if ($size['width'] > $size['height']) {
                $pdf->AddPage('L', array($size['width'], $size['height']));
            } else {
                $pdf->AddPage('P', array($size['width'], $size['height']));
            }

            // use the imported page
            $pdf->useTemplate($templateId);

                // Add rounded rectangle
            $pdf->SetDrawColor(255, 255, 255); // White border

            $pdf->SetAlpha(0.8); // Set transparency (0.8 opacity)
            $pdf->SetFillColor(0, 0, 0); // Black fill
            $pdf->Rect(10, 10, 50, 10, 'F'); // Normal rectangle
            //$pdf->SetFillColor(255, 255, 255); // White fill
            //$pdf->Rect(10, 10, 50, 10, 3, '1111', 'DF');

            // Add text
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(255, 0, 0); // Red text
            $pdf->SetXY(12, 12);
            $pdf->Write(0, 'Made with love');
        }
    }

    //save the pdf file:
    $pdf->Output(app_path('pdfs/merged.pdf'), 'F');
});
