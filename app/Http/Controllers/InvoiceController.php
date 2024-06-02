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


class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Invoice::all();
    }


    public function show(Invoice $invoice)
    {
        return response()->json($invoice->toArray());
    }

    /**
     * Store a newly created resource in storage.
     */
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

    /**
     * Update the specified resource in storage.
     */
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

        $invoice->update($validatedData);
        $invoice->save();

        $invoice->report->updateFromToDates();
        RecordsCache::clearAll();
        return response()->json(['message' => 'Invoice updated', 'invoice' => $invoice->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        $report = $invoice->report;
        $invoice->delete();
        $report->updateFromToDates();
        RecordsCache::clearAll();
        return response()->json(['message' => 'Invoice deleted']);
    }

    /**
     * Upload image
     */
    public function uploadImage(Request $request, Invoice $invoice)
    {
        $maxSizeInBytes = $maxSizeInBytes ?? env('APP_MAXIMUM_UPLOAD_SIZE') ?? 2048 * 1024;
        $base64Image = $request->input('image');

        if (!$base64Image) {
            return response()->json([
                'error' => [
                    'message' => 'Image missing',
                ]
            ], 400);
        }

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
        $path = 'invoices/' . $imageId;

        $wasSuccessfull = Storage::disk('public')->put($path, $imageEncoded);

        if (!$wasSuccessfull) {
            return response()->json([
                'error' => [
                    'message' => 'Image upload failed',
                ]
            ], 500);
        }

        $invoice->image = $imageId;
        $invoice->save();
        return response()->json([
            'message' => 'Image uploaded',
            'image' => [
                'id' => $imageId,
                'url' => Storage::disk('public')->url($path),
                //'path' => Storage::disk('public')->path($path)
            ]
        ]);
    }


    /**
     * Show image
     */
    public function showImage(Request $request, Invoice $invoice){
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
}
