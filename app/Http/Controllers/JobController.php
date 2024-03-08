<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Models\Job;
use App\Models\Invoice;

class JobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Job::all();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJobRequest $request)
    {
        $job = Job::create($request->validated());
        return response()->json(['message' => 'Job created', 'job' => $job->toArray()]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Job $job)
    {
        return response()->json($job->toArray());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJobRequest $request, Job $job)
    {
        $job->update($request->validated());
        $job->save();
        return response()->json(['message' => 'Job updated', 'job' => $job->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
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
