<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRequest;
use App\Http\Requests\UpdateJobRequest;
use App\Models\Job;
use App\Models\Invoice;
use App\Models\Attendance;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Models\InventoryWarehouseOutcome;
use App\Models\InventoryWarehouseProductItemLoan;
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
        $previousJobCode = $job->code;
        $newJobCode = $request->validated()['code'];
        $job->update($request->validated());


        Invoice::where('job_code', $previousJobCode)->update(['job_code' => $newJobCode]);
        Attendance::where('job_code', $previousJobCode)->update(['job_code' => $newJobCode]);
        InventoryWarehouseIncome::where('job_code', $previousJobCode)->update(['job_code' => $newJobCode]);
        InventoryWarehouseOutcomeRequest::where('job_code', $previousJobCode)->update(['job_code' => $newJobCode]);
        InventoryWarehouseOutcome::where('job_code', $previousJobCode)->update(['job_code' => $newJobCode]);
        InventoryWarehouseProductItemLoan::where('job_code', $previousJobCode)->update(['job_code' => $newJobCode]);

        return response()->json(['message' => 'Job updated', 'job' => $job->toArray()]);
    }

    public function destroy(Job $job)
    {
        //Check if there is any invoice with this job:
        $count = Invoice::where('job_code', $job->code)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Job has invoices, cannot be deleted'], 400);
        }

        $count = Attendance::where('job_code', $job->code)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Job has attendances, cannot be deleted'], 400);
        }

        $count = InventoryWarehouseIncome::where('job_code', $job->code)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Job has warehouse incomes, cannot be deleted'], 400);
        }

        $count = InventoryWarehouseOutcomeRequest::where('job_code', $job->code)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Job has warehouse outcome requests, cannot be deleted'], 400);
        }

        $count = InventoryWarehouseOutcome::where('job_code', $job->code)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Job has warehouse outcomes, cannot be deleted'], 400);
        }

        $count = InventoryWarehouseProductItemLoan::where('job_code', $job->code)->count();
        if ($count > 0) {
            return response()->json(['message' => 'Job has warehouse product item loans, cannot be deleted'], 400);
        }

        $job->delete();
        return response()->json(['message' => 'Job deleted']);
    }
}
