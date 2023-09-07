<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Report;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Report::all();
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


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReportRequest $request, Report $report)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Report $report)
    {
        //
    }

    public function invoices(Report $report)
    {
        $invoices = $report->invoices()->get();
        return response()->json(collect($invoices)->toArray());
    }
}
