<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkerPaymentRequest;
use App\Http\Requests\StoreMultipleWorkerPaymentRequest;
use App\Http\Requests\UpdateWorkerPaymentRequest;
use App\Models\WorkerPayment;
use App\Support\Cache\RecordsCache;
use App\Models\Worker;

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


    public function storeMultiple(StoreMultipleWorkerPaymentRequest $request)
    {
        $validated = $request->validated();

        foreach ($validated['workers_dni'] as $dni) {
            $worker = Worker::where('dni', $dni)->first();

            if (!$worker) {
                return response()->json(['message' => 'Worker not found'], 404);
            }


            $workerPayment = WorkerPayment::where('worker_id', $worker->id)
                ->where('month', $validated['month'])
                ->where('year', $validated['year'])
                ->first();

            if ($workerPayment) {
                $workerPayment->update($validated);
            } else {
                $validated['worker_id'] = $worker->id;
                WorkerPayment::create($validated);
            }
        }

        RecordsCache::clearAll();
        return  response()->json(['message' => 'Worker payments created successfully']);
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
