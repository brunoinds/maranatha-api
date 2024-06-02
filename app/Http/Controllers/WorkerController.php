<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\Worker;
use App\Support\Cache\RecordsCache;

class WorkerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Worker::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkerRequest $request)
    {
        $validated = $request->validated();
        RecordsCache::clearAll();
        return Worker::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(Worker $worker)
    {
        return $worker;
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkerRequest $request, Worker $worker)
    {
        $validated = $request->validated();
        $worker->createHistorySnapshot();
        $worker->update($validated);
        RecordsCache::clearAll();
        return $worker;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Worker $worker)
    {
        $worker->delete();
        RecordsCache::clearAll();
        return response()->noContent();
    }
}
