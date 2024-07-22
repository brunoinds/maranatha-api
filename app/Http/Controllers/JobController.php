<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Models\Job;
use App\Models\Invoice;

class JobController extends Controller
{
    public function index()
    {
        return Job::all();
    }

    public function store(StoreJobRequest $request)
    {
        $job = Job::create($request->validated());
        return response()->json(['message' => 'Job created', 'job' => $job->toArray()]);
    }

    public function show(Job $job)
    {
        return response()->json($job->toArray());
    }

    public function update(UpdateJobRequest $request, Job $job)
    {
        $job->update($request->validated());
        $job->save();
        return response()->json(['message' => 'Job updated', 'job' => $job->toArray()]);
    }

    public function destroy(Job $job)
    {
        //Check if there is any invoice with this job:
        $count = Invoice::where('job_code', $job->code)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Job has invoices, cannot be deleted'], 400);
        }

        $job->delete();
        return response()->json(['message' => 'Job deleted']);
    }
}
