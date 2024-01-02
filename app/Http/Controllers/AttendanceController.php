<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Http\Requests\StoreAttendanceWithWorkersRequest;
use App\Http\Requests\StoreWorkersAttendancesRequest;
use App\Models\AttendanceDayWorker;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function myAttendances()
    {
        $myAttendances = collect(Attendance::all()->where('user_id', auth()->user()->id)->values());
        return response()->json($myAttendances->toArray());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAttendanceRequest $request)
    {
        $attendance = Attendance::create($request->validated());
        return response()->json(['message' => 'Attendance created', 'attendance' => $attendance->toArray()]);
    }


    public function storeWithWorkers(StoreAttendanceWithWorkersRequest $request)
    {
        $requestValidated = $request->validated();
        $attendance = Attendance::create($requestValidated);
        
        foreach ($requestValidated['workers_dni'] as $worker_dni) {
            $attendance->attachWorkerDni($worker_dni);
        }
        $workersCount = count($requestValidated['workers_dni']);
        return response()->json(['message' => 'Attendance created with ' . $workersCount . ' workers.', 'attendance' => $attendance->toArray()]);
    }
    public function storeWorkersAttendances(StoreWorkersAttendancesRequest $request)
    {
        $requestValidated = $request->validated();
        $workers = $requestValidated['workers'];
        foreach ($workers as $worker) {
            foreach ($worker['days'] as $day) {
                $id = $day['id'];
                $newStatus = $day['status'];
                AttendanceDayWorker::where('id', $id)->update(['status' => $newStatus]);
            }
        }
        return response()->json(['message' => 'Workers attendances updated']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Attendance $attendance)
    {
        return response()->json($attendance->toArray());
    }

    public function showWithWorkersAttendances(Attendance $attendance)
    {
        return response()->json([
            'attendance' => $attendance->toArray(),
            'workersAttendances' => collect($attendance->dayWorkers())->map(function ($item) {
                return [
                    'id' => $item['id'],
                    'worker' => [
                        'dni' => $item['worker_dni'],
                    ],
                    'date' => $item['date'],
                    'status' => $item['status'],
                    'observations' => $item['observations']
                ];
            })->toArray()
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAttendanceRequest $request, Attendance $attendance)
    {
        $attendance->update($request->validated());
        return response()->json(['message' => 'Attendance updated', 'attendance' => $attendance->toArray()]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance)
    {
        $attendance->delete();
        return response()->json(['message' => 'Attendance deleted']);
    }
}
