<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\Worker;
use App\Support\Cache\RecordsCache;

class WorkerController extends Controller
{
    public function index()
    {
        return Worker::all();
    }

    public function store(StoreWorkerRequest $request)
    {
        $validated = $request->validated();
        RecordsCache::clearAll();
        return Worker::create($validated);
    }

    public function show(Worker $worker)
    {
        return $worker;
    }

    public function update(UpdateWorkerRequest $request, Worker $worker)
    {
        $validated = $request->validated();
        $worker->createHistorySnapshot();
        $worker->update($validated);
        RecordsCache::clearAll();
        return $worker;
    }

    public function destroy(Worker $worker)
    {
        $worker->delete();
        RecordsCache::clearAll();
        return response()->noContent();
    }
}
