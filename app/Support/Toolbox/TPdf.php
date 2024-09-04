<?php

namespace App\Support\Toolbox;

use \setasign\Fpdi\Fpdi;
use Ilovepdf\Ilovepdf;
use Spatie\TemporaryDirectory\TemporaryDirectory;
use Illuminate\Support\Str;


class TPdf{
    public static function checkIfPdfNeedsRepair(string $pdfPath){
        $pdf = new Fpdi();
        $pdf->SetDisplayMode(100);
        try {
            $pages = @$pdf->setSourceFile($pdfPath);
            return false;
        } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $ex){
            return true;
        }
    }
    public static function repairPdf(string $pdfPath){
        $temporaryDirectory = (new TemporaryDirectory())->create();
        $fileReparedFolderPath = $temporaryDirectory->path();
        $fileName = Str::uuid() . '.pdf';

        $fileReparedPath = $fileReparedFolderPath . '/' . $fileName;


        $iLovePdf = new Ilovepdf(env('ILOVEPDF_PUBLIC_KEY'), env('ILOVEPDF_SECRET_KEY'));
        $task = $iLovePdf->newTask('repair');
        $task->addFile($pdfPath);
        $task->setOutputFilename($fileName);
        $task->execute();

        $task->download($fileReparedFolderPath);
        return $fileReparedPath;
    }

    public static function transformPdfBase64IntoTemporarilyFile(string $base64Pdf){
        $temporaryDirectory = (new TemporaryDirectory())->create();
        $pdfPath = $temporaryDirectory->path('file.pdf');
        file_put_contents($pdfPath, base64_decode($base64Pdf));
        return $pdfPath;
    }

    public static function transformPdfFileIntoBase64(string $pdfPath){
        return base64_encode(file_get_contents($pdfPath));
    }
}
