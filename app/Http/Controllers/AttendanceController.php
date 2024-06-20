<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Http\Requests\StoreAttendanceWithWorkersRequest;
use App\Http\Requests\StoreWorkersAttendancesRequest;
use App\Http\Requests\TransferAttendanceRequest;
use App\Models\AttendanceDayWorker;
use App\Support\Cache\RecordsCache;


class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!auth()->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $allAttendances = Attendance::all();
        $allAttendances->each(function ($attendance) {
            $attendance->user = $attendance->user()->get()->first()->toArray();
        });

        return response()->json($allAttendances->toArray());
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
        RecordsCache::clearAll();
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
        RecordsCache::clearAll();
        return response()->json(['message' => 'Attendance created with ' . $workersCount . ' workers.', 'attendance' => $attendance->toArray()]);
    }
    public function storeWorkersAttendances(StoreWorkersAttendancesRequest $request)
    {
        $requestValidated = $request->validated();
        $workers = $requestValidated['workers'];

        $updates = [];

        foreach ($workers as $worker) {
            foreach ($worker['days'] as $day) {
                $id = $day['id'];
                $newStatus = $day['status'];
                if (!isset($updates[$newStatus])) {
                    $updates[$newStatus] = [];
                }
                $updates[$newStatus][] = $id;
            }
        }

        foreach ($updates as $status => $ids) {
            AttendanceDayWorker::whereIn('id', $ids)->update(['status' => $status]);
        }
        RecordsCache::clearAll();
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
        $attendance->updateFromToDatesInAttendanceDayWorker();

        if (isset($request->validated()['workers_dnis'])) {
            $attendance->updateWorkersDnis($request->validated()['workers_dnis']);
        }
        RecordsCache::clearAll();
        return response()->json(['message' => 'Attendance updated', 'attendance' => $attendance->toArray()]);
    }

    public function transferOwnership(TransferAttendanceRequest $request, Attendance $attendance)
    {
        $attendance->update($request->validated());
        return response()->json(['message' => 'Attendance ownership transfered', 'attendance' => $attendance->toArray()]);
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
