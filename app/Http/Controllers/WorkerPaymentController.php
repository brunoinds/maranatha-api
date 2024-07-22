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
    public function index()
    {
        return WorkerPayment::all();
    }

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

    public function show(WorkerPayment $workerPayment)
    {
        return $workerPayment;
    }

    public function update(UpdateWorkerPaymentRequest $request, WorkerPayment $workerPayment)
    {
        $validated = $request->validated();
        $workerPayment->update($validated);
        RecordsCache::clearAll();
        return response()->json(['message' => 'Worker payment updated successfully']);
    }

    public function destroy(WorkerPayment $workerPayment)
    {
        $workerPayment->delete();
        RecordsCache::clearAll();
        return response()->noContent();
    }
}
