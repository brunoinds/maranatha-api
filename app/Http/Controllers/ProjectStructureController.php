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
        return response()->json(ProjectStructure::all());
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProjectStructureRequest $request)
    {
        $validated = $request->validated();

        $structure = ProjectStructure::create($validated);
        return response()->json(['message' => 'Project structure created successfully', 'structure' => $structure]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ProjectStructure $structure)
    {
        return response()->json($structure);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProjectStructureRequest $request, ProjectStructure $structure)
    {
        $validated = $request->validated();

        $structure->update($validated);
        return response()->json(['message' => 'Project structure updated successfully', 'structure' => $structure]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProjectStructure $structure)
    {
        $structure->delete();
        return response()->json(['message' => 'Project structure deleted successfully']);
    }
}
