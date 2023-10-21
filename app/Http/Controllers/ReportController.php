<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Mail\NewReportSent;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\Assistants\ReportAssistant;
use Illuminate\Support\Facades\Mail;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        if (!auth()->user()->hasRole("admin", "sanctum")){
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $allReports = Report::all();

        $allReports->each(function ($report) {
            $report->user = $report->user()->get()->first()->toArray();
        });

        $allReports->each(function ($report) {
            $report->invoices = [
                'count' => $report->invoices()->count(),
                'total_amount' => $report->invoices()->sum('amount'),
            ];
        });

        return response()->json($allReports->toArray());
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReportRequest $request)
    {
        $report = Report::create($request->validated());
        return response()->json(['message' => 'Report created', 'report' => $report->toArray()]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Report $report)
    {
        return response()->json($report->toArray());
    }

    public function myReports()
    {
        $myReports = collect(Report::all()->where('user_id', auth()->user()->id)->values());
        $myReports->each(function ($report) {
            $report->invoices = [
                'count' => $report->invoices()->count(),
                'total_amount' => $report->invoices()->sum('amount'),
            ];
        });
        return response()->json($myReports->toArray());
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReportRequest $request, Report $report)
    {

        $previousStatus = $report->status;
        
        $report->update($request->validated());
        $report->save();


        /*if ($previousStatus === 'Draft' && $report->status === 'Submitted'){
            Mail::to('noreply@imedicineapp.com')->send(new NewReportSent($report));
        }*/
        return response()->json(['message' => 'Report updated', 'report' => $report->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Report $report)
    {
        $report->delete();
        $report->invoices()->delete();
        return response()->json(['message' => 'Report deleted']);
    }

    public function invoices(Report $report)
    {
        $invoices = $report->invoices()->get();
        return response()->json(collect($invoices)->toArray());
    }


    /**
     * Upload report PDF
     */
    public function uploadReportPDF(Request $request, Report $report)
    {
        $base64PDF = $request->input('pdf');
        $documentId = Str::random(40);
        $path = 'reports/' . $documentId;

        $pdfDecoded = base64_decode($base64PDF);

        $wasSuccessfull = Storage::disk('public')->put($path, $pdfDecoded);

        if (!$wasSuccessfull) {
            return response()->json([
                'error' => [
                    'message' => 'PDF upload failed',
                ]
            ], 500);
        }

        $report->exported_pdf = $documentId;
        $report->save();

        return response()->json([
            'message' => 'PDF uploaded',
            'pdf' => [
                'id' => $documentId,
                'url' => Storage::disk('public')->url($path),
            ]
        ]);
    }

    public function downloadPDF(Report $report){
        $assetId = $report->exported_pdf;
        if ($assetId == null) {
            return response()->json(['message' => 'Report not generated yet'], 400);
        }

        return Storage::disk('public')->download('reports/' . $assetId, $report->title . '.pdf');
    }
    public function downloadExcel(Report $report){
        $excel = ReportAssistant::generateExcelDocument($report);
        $documentName = $report->title . '.xlsx';
        $excel->download($documentName);
    }
}
