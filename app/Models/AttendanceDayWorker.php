<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Attendance;
use App\Helpers\Enums\AttendanceStatus;

class AttendanceDayWorker extends Model
{
    use HasFactory;


    protected $fillable = [
        'worker_dni',
        'attendance_id',
        'date',
        'status',
        'observations'
    ];

    protected $casts = [
        'status' => AttendanceStatus::class
    ];

    public function attendance(){
        return $this->belongsTo(Attendance::class);
    }
}
