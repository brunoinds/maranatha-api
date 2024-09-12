<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invoice;
use Illuminate\Support\Facades\Storage;
use App\Support\Toolbox\TPdf;
use Spatie\TemporaryDirectory\TemporaryDirectory;



class FixInvoicesPdfsVersions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:fix-pdfs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix corrupted PDFs from invoices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $pdfsCorrupteds = Invoice::whereNotNull('pdf')->get()->map(function($invoice){
            $exists = Storage::disk('public')->exists('invoices/' . $invoice->pdf);

            if (!$exists){
                return null;
            }

            $path = Storage::disk('public')->path('invoices/' . $invoice->pdf);

            if (TPdf::checkIfPdfNeedsRepair($path)){
                return (object) [
                    'path' => $path,
                    'invoice' => $invoice
                ];
            }else{
                return null;
            }
        })->filter(function($path){
            return !is_null($path);
        });

        $this->info('Corrupted PDFs found: ' . $pdfsCorrupteds->count());

        $pdfsCorrupteds->each(function($item, $index) use ($pdfsCorrupteds){
            $this->info('Repairing Invoice ID: ' . $item->invoice->id . ' Pdf ID: ' . $item->invoice->pdf);
            $temporaryDirectory = (new TemporaryDirectory())->create();
            $pdfTempFile = $temporaryDirectory->path() . '/' . $item->invoice->pdf . '.pdf';
            copy($item->path, $pdfTempFile);
            $base64Pdf = TPdf::transformPdfFileIntoBase64(TPdf::repairPdf($pdfTempFile));
            $pdfEncoded = base64_decode($base64Pdf);
            $path = 'invoices/' . $item->invoice->pdf;
            $wasSuccessfull = Storage::disk('public')->put($path, $pdfEncoded);
            if ($wasSuccessfull){
                $this->info('PDF repaired successfully ✅');
            }else{
                $this->error('Error repairing PDF');
            }
        });


        $this->info('PDFs repair finished ✅');
    }
}
