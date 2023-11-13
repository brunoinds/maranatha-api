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
use App\Support\Generators\ReportGenerator;
use App\Support\GoogleSheets\Excel;
use OneSignal;
use App\Models\User;

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

        if ($report->status === 'Approved'){
            $report->approved_at = now();
        } else {
            $report->approved_at = null;
        }

        if ($report->status === 'Rejected'){
            $report->rejected_at = now();
        } else {
            $report->rejected_at = null;
        }

        if ($report->status === 'Submitted'){
            $report->submitted_at = now();
        }

        if ($report->status === 'Draft'){
            $report->submitted_at = null;
        }

        $report->save();


        if ($previousStatus !== $report->status){
            $excelOutput = ReportGenerator::generateExcelOutput();
            Excel::updateDBSheet($excelOutput);
        }


        if ($previousStatus === 'Draft' && $report->status === 'Submitted'){
            //Send notification
            $user = $report->user()->get()->first();
            $adminUser = User::where('username', 'admin')->first();

            OneSignal::sendNotificationToExternalUser(
                headings: "Nuevo reporte enviado 📤",
                message: $user->name . " ha enviado un nuevo reporte de S/. " . number_format($report->amount(), 2) . " y está esperando su aprobación.", 
                userId: (string) 'user-id-'.$adminUser->id
            );
        }

        if ($previousStatus === 'Submitted' && $report->status === 'Approved'){
            //Send notification
            $user = $report->user()->get()->first();

            OneSignal::sendNotificationToExternalUser(
                headings: "Reporte aprobado ✅",
                message: "El administrador ha aprobado su reporte de  S/. " . number_format($report->amount(), 2) . "", 
                userId: (string) 'user-id-'.$user->id
            );
        }

        if ($previousStatus === 'Submitted' && $report->status === 'Rejected'){
            //Send notification
            $user = $report->user()->get()->first();

            OneSignal::sendNotificationToExternalUser(
                headings: "Reporte rechazado ❌",
                message: "El administrador ha rechazado su reporte de  S/. " . number_format($report->amount(), 2) . ". Ingrese a la aplicación para ver el motivo de rechazo.", 
                userId: (string) 'user-id-'.$user->id
            );
        }

        return response()->json(['message' => 'Report updated', 'report' => $report->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Report $report)
    {
        $report->delete();
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
