<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectConstructionPhaseRequest;
use App\Http\Requests\UpdateProjectConstructionPhaseRequest;
use App\Models\ProjectConstructionPhase;

class ProjectConstructionPhaseController extends Controller
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
    public function store(StoreProjectConstructionPhaseRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectConstructionPhase $constructionPhase)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectConstructionPhaseRequest $request, ProjectConstructionPhase $constructionPhase)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectConstructionPhase $constructionPhase)
    {
        //
    }
}
