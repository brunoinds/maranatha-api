<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectStructureRequest;
use App\Http\Requests\UpdateProjectStructureRequest;
use App\Models\ProjectStructure;

class ProjectStructureController extends Controller
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
    public function store(StoreProjectStructureRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectStructure $structure)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectStructureRequest $request, ProjectStructure $structure)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectStructure $structure)
    {
        //
    }
}
