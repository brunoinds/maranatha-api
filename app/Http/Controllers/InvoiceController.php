<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use App\Support\Toolbox\TString;
use App\Support\Cache\RecordsCache;
use App\Support\Toolbox\TPdf;

class InvoiceController extends Controller
{
    public function index()
    {
        return Invoice::all();
    }

    public function show(Invoice $invoice)
    {
        return response()->json($invoice->toArray());
    }

    public function store(StoreInvoiceRequest $request)
    {
        $validatedData = $request->validated();
        if (!is_null($validatedData['image']) && mb_strlen($validatedData['image']) > 40){
            //Has image to upload
            $maxSizeInBytes = $maxSizeInBytes ?? env('APP_MAXIMUM_UPLOAD_SIZE') ?? 2048 * 1024;
            $base64Image = $validatedData['image'];

            $imageSize = (fn() => strlen(base64_decode($base64Image)))();
            if ($imageSize > $maxSizeInBytes) {
                return response()->json([
                    'error' => [
                        'message' => "Image exceeds max size (maximum $maxSizeInBytes bytes)",
                    ]
                ], 400);
            }


            try{
                $imageResource = Image::make($base64Image);
                $imageEncoded = $imageResource->encode('png')->getEncoded();
            } catch(\Exception $e){
                return response()->json([
                    'error' => [
                        'message' => 'Invalid image data',
                        'details' => $e->getMessage()
                    ]
                ], 400);
            }

            $imageId = Str::random(40);
            $validatedData['image'] = $imageId;
            $validatedData['image_size'] = $imageSize;

            $validatedData['pdf'] = null;
            $validatedData['pdf_size'] = null;


            $path = 'invoices/' . $imageId;

            $wasSuccessfull = Storage::disk('public')->put($path, $imageEncoded);

            if (!$wasSuccessfull) {
                return response()->json([
                    'error' => [
                        'message' => 'Image upload failed',
                    ]
                ], 500);
            }
        }
        if (!is_null($validatedData['pdf']) && mb_strlen($validatedData['pdf']) > 40){
            //Has pdf to upload
            $maxSizeInBytes = $maxSizeInBytes ?? env('APP_MAXIMUM_UPLOAD_SIZE') ?? 2048 * 1024;
            $base64Pdf = $validatedData['pdf'];

            $pdfSize = (fn() => strlen(base64_decode($base64Pdf)))();
            if ($pdfSize > $maxSizeInBytes) {
                return response()->json([
                    'error' => [
                        'message' => "PDF exceeds max size (maximum $maxSizeInBytes bytes)",
                    ]
                ], 400);
            }

            //Avoid corrupted PDF or PDF version 1.5 or higher, transform it into a PDF version 1.4:
            $pdfTempFilePath = TPdf::transformPdfBase64IntoTemporarilyFile($base64Pdf);
            if (TPdf::checkIfPdfNeedsRepair($pdfTempFilePath)){
                $base64Pdf = TPdf::transformPdfFileIntoBase64(TPdf::repairPdf($pdfTempFilePath));
            }

            $pdfEncoded = base64_decode($base64Pdf);
            $pdfId = Str::random(40);
            $validatedData['pdf'] = $pdfId;
            $validatedData['pdf_size'] = $pdfSize;

            $validatedData['image'] = null;
            $validatedData['image_size'] = null;

            $path = 'invoices/' . $pdfId;

            $wasSuccessfull = Storage::disk('public')->put($path, $pdfEncoded);

            if (!$wasSuccessfull) {
                return response()->json([
                    'error' => [
                        'message' => 'PDF upload failed',
                    ]
                ], 500);
            }
        }

        $invoice = Invoice::create($validatedData);
        $invoice->report?->updateFromToDates();

        RecordsCache::clearAll();

        return response()->json(['message' => 'Invoice created', 'invoice' => $invoice->toArray()]);
    }

    public function checkTicketNumber(Request $request)
    {
        $request->validate([
            'ticket_number' => 'required|string',
            'commerce_number' => 'required|string'
        ]);

        $ticket = $request->input('ticket_number');
        $commerceNumber = $request->input('commerce_number');
        $invoice = Invoice::where('ticket_number', '=', $ticket)->where('commerce_number', '=', $commerceNumber)->first();
        if ($invoice){
            //Send response with error code:
            return response()->json(['exists' => true, 'message' => 'Ticket number already exists', 'invoice' => $invoice->toArray()]);
        }else{
            return response()->json(['exists' => false, 'message' => 'Ticket number is available']);
        }
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $validatedData = $request->validated();


        if (!is_null($validatedData['image_base64']) && mb_strlen($validatedData['image_base64']) > 40){
            //Has image to upload
            $maxSizeInBytes = $maxSizeInBytes ?? env('APP_MAXIMUM_UPLOAD_SIZE') ?? 2048 * 1024;
            $base64Image = $validatedData['image_base64'];

            $imageSize = (fn() => strlen(base64_decode($base64Image)))();
            if ($imageSize > $maxSizeInBytes) {
                return response()->json([
                    'error' => [
                        'message' => "Image exceeds max size (maximum $maxSizeInBytes bytes)",
                    ]
                ], 400);
            }


            try{
                $wasSuccessfull = $invoice->setImageFromBase64($base64Image);
                if (!$wasSuccessfull) {
                    return response()->json([
                        'error' => [
                            'message' => 'Image upload failed',
                        ]
                    ], 500);
                }
            } catch(\Exception $e){
                return response()->json([
                    'error' => [
                        'message' => 'Invalid image data',
                        'details' => $e->getMessage()
                    ]
                ], 400);
            }
        }

        if (!is_null($validatedData['pdf_base64']) && mb_strlen($validatedData['pdf_base64']) > 40){
            //Has pdf to upload
            $maxSizeInBytes = $maxSizeInBytes ?? env('APP_MAXIMUM_UPLOAD_SIZE') ?? 2048 * 1024;
            $base64Pdf = $validatedData['pdf_base64'];

            $pdfSize = (fn() => strlen(base64_decode($base64Pdf)))();
            if ($pdfSize > $maxSizeInBytes) {
                return response()->json([
                    'error' => [
                        'message' => "PDF exceeds max size (maximum $maxSizeInBytes bytes)",
                    ]
                ], 400);
            }

            //Avoid corrupted PDF or PDF version 1.5 or higher, transform it into a PDF version 1.4:
            $pdfTempFilePath = TPdf::transformPdfBase64IntoTemporarilyFile($base64Pdf);
            if (TPdf::checkIfPdfNeedsRepair($pdfTempFilePath)){
                $base64Pdf = TPdf::transformPdfFileIntoBase64(TPdf::repairPdf($pdfTempFilePath));
            }

            try{
                $wasSuccessfull = $invoice->setPdfFromBase64($base64Pdf);
                if (!$wasSuccessfull) {
                    return response()->json([
                        'error' => [
                            'message' => 'PDF upload failed',
                        ]
                    ], 500);
                }
            } catch(\Exception $e){
                return response()->json([
                    'error' => [
                        'message' => 'Invalid PDF data',
                        'details' => $e->getMessage()
                    ]
                ], 400);
            }
        }

        $invoice->update($validatedData);
        $invoice->save();

        $invoice->report->updateFromToDates();
        RecordsCache::clearAll();
        return response()->json(['message' => 'Invoice updated', 'invoice' => $invoice->toArray()]);
    }

    public function destroy(Invoice $invoice)
    {
        $report = $invoice->report;
        $invoice->delete();
        $report->updateFromToDates();
        RecordsCache::clearAll();
        return response()->json(['message' => 'Invoice deleted']);
    }

    public function showImage(Request $request, Invoice $invoice)
    {
        $imageId = $invoice->image;
        if (!$imageId){
            return response()->json([
                'error' => [
                    'message' => 'Image not uploaded yet',
                ]
            ], 400);
        }

        $path = 'invoices/' . $imageId;
        $imageExists = Storage::disk('public')->exists($path);
        if (!$imageExists){
            return response()->json([
                'error' => [
                    'message' => 'Image missing',
                ]
            ], 400);
        }

        $image = Storage::disk('public')->get($path);

        //Send back as base64 encoded image:
        return response()->json(['image' => base64_encode($image)]);
    }

    public function showPdf(Request $request, Invoice $invoice)
    {
        $pdfId = $invoice->pdf;
        if (!$pdfId){
            return response()->json([
                'error' => [
                    'message' => 'PDF not uploaded yet',
                ]
            ], 400);
        }

        $path = 'invoices/' . $pdfId;
        $pdfExists = Storage::disk('public')->exists($path);
        if (!$pdfExists){
            return response()->json([
                'error' => [
                    'message' => 'PDF missing',
                ]
            ], 400);
        }

        $pdf = Storage::disk('public')->get($path);

        //Send back as base64 encoded pdf:
        return response()->json(['pdf' => base64_encode($pdf)]);
    }
}
