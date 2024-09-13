<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectJobRequest;
use App\Http\Requests\UpdateProjectJobRequest;
use App\Models\ProjectJob;

class ProjectJobController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectJobRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectJob $job)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectJobRequest $request, ProjectJob $job)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectJob $job)
    {
        //
    }
}
