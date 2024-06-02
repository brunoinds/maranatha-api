<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkerPaymentRequest;
use App\Http\Requests\UpdateWorkerPaymentRequest;
use App\Models\WorkerPayment;
use App\Support\Cache\RecordsCache;

class WorkerPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return WorkerPayment::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkerPaymentRequest $request)
    {
        $validated = $request->validated();
        RecordsCache::clearAll();
        return WorkerPayment::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(WorkerPayment $workerPayment)
    {
        return $workerPayment;
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkerPaymentRequest $request, WorkerPayment $workerPayment)
    {
        $validated = $request->validated();
        $workerPayment->update($validated);
        RecordsCache::clearAll();
        return response()->json(['message' => 'Worker payment updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WorkerPayment $workerPayment)
    {
        $workerPayment->delete();
        RecordsCache::clearAll();
        return response()->noContent();
    }
}
