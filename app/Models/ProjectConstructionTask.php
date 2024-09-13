<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectJob;
use App\Models\ProjectConstructionPhase;

class ProjectConstructionTask extends Model
{
    use HasFactory;


    protected $fillable = [
        'project_job_id',
        'project_construction_phase_id',
        'name',
        'description',
        'status',
        'scheduled_start_date',
        'scheduled_end_date',
        'started_at',
        'ended_at',
        'count_workers',
        'progress',
        'daily_reports',
    ];

    protected $casts = [
        'daily_reports' => 'array',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function projectConstructionPhase()
    {
        return $this->belongsTo(ProjectConstructionPhase::class);
    }


}
