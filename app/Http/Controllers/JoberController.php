<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJoberRequest;
use App\Http\Requests\UpdateJoberRequest;
use App\Models\Jober;

class JoberController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Jober::all();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreJoberRequest $request)
    {
        $jober = Jober::create($request->validated());
        return response()->json(['message' => 'Jober created', 'jober' => $jober->toArray()]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Jober $jober)
    {
        return response()->json($jober->toArray());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateJoberRequest $request, Jober $jober)
    {
        $jober->update($request->validated());
        $jober->save();
        return response()->json(['message' => 'Jober updated', 'jober' => $jober->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Jober $jober)
    {
        $jober->delete();
        return response()->json(['message' => 'Jober deleted']);
    }
}
