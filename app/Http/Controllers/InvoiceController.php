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

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $content = request()->server('HTTP_ACCESS_CONTROL_REQUEST_HEADERS');
        return response()->json(['message' => 'Invoice index', 'content' => $content]);
        return Invoice::all();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
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
        $invoice = Invoice::create($request->validated());
        return response()->json(['message' => 'Invoice created', 'invoice' => $invoice->toArray()]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $validateStatus = $request->validated();

        $invoice->image = $request->input('image');
        $invoice->save();

        return response()->json(['message' => 'Invoice updated', 'invoice' => $invoice->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return response()->json(['message' => 'Invoice deleted']);
    }

    /**
     * Upload image
     */
    public function uploadImage(Request $request, Invoice $invoice)
    {
        $maxSizeInBytes = 2048 * 1024; // 2MB
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
}
