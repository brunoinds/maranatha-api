<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectJob;
use App\Models\ProjectConstructionTask;

class ProjectConstructionPhase extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_job_id',
        'expense_code',
        'name',
        'description',
        'color',
        'status',
        'scheduled_start_date',
        'scheduled_end_date',
        'started_at',
        'ended_at',
        'progress',
        'final_report',
    ];

    protected $casts = [
        'final_report' => 'array',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function tasks()
    {
        return $this->hasMany(ProjectConstructionTask::class);
    }

    public function refreshProgress()
    {
        $tasks = $this->tasks;
        $totalProgress = 0;
        $totalTasks = 0;
        foreach ($tasks as $task) {
            $totalProgress += $task->progress;
            $totalTasks++;
        }
        $this->progress = $totalTasks > 0 ? $totalProgress / $totalTasks : 0;
        $this->save();
    }

    public function delete()
    {
        $this->tasks()->delete();
        return parent::delete();
    }
}
