<?php

namespace App\Models;

use App\Helpers\Enums\ProjectConstructionPhaseStatus;
use App\Helpers\Enums\ProjectConstructionTaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectStructure;
use App\Models\User;
use Carbon\Carbon;
use App\Models\ProjectConstructionPhase;
use App\Models\ProjectConstructionTask;
use App\Models\Job;



class ProjectJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_code',
        'project_structure_id',
        'width',
        'length',
        'area',
        'admins_ids',
        'supervisor_id',
        'event_type',
        'scheduled_start_date',
        'scheduled_end_date',
        'started_at',
        'ended_at',
        'status',
        'final_report',
        'marketing_report',
        'messages',
    ];

    protected $casts = [
        'admins_ids' => 'array',
        'final_report' => 'array',
        'messages' => 'array',
        'width' => 'float',
        'length' => 'float',
        'area' => 'float',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_code', 'code');
    }

    public function projectStructure()
    {
        return $this->belongsTo(ProjectStructure::class);
    }

    public function admins()
    {
        return $this->hasMany(User::class, 'id', 'admins_ids');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'id', 'supervisor_id');
    }

    public function constructionPhases()
    {
        return $this->hasMany(ProjectConstructionPhase::class, 'project_job_id', 'id');
    }


    public function importPhasesAndTasksFromStructure()
    {
        $inst = $this;

        //Construction Phase Importing:
        $runningPhaseDays = 0;
        foreach ($this->projectStructure->default_phases->construction as $phase){
            $phaseAverageDays = collect($phase->tasks)->map(function($task){
                return $task->average_days;
            })->sum();

            $constructionPhase = ProjectConstructionPhase::create([
                'project_job_id' => $inst->id,
                'expense_code' => $phase->expense_code,
                'name' => $phase->name,
                'description' => $phase->description,
                'color' => $phase->color,
                'status' => ProjectConstructionPhaseStatus::WaitingToStart,
                'scheduled_start_date' => Carbon::parse($inst->scheduled_start_date)->addDays($runningPhaseDays)->toISOString(),
                'scheduled_end_date' => Carbon::parse($inst->scheduled_start_date)->addDays($runningPhaseDays + $phaseAverageDays)->toISOString(),
                'started_at' => null,
                'ended_at' => null,
                'progress' => 0,
                'final_report' => null
            ]);
            $runningPhaseDays += $phaseAverageDays;


            //Construction Task Importing:
            $runningTaskDays = 0;
            foreach ($phase->tasks as $task){
                $constructionTask = ProjectConstructionTask::create([
                    'project_job_id' => $inst->id,
                    'project_construction_phase_id' => $constructionPhase->id,
                    'name' => $task->name,
                    'description' => $task->description,
                    'status' => ProjectConstructionTaskStatus::WaitingToStart,
                    'scheduled_start_date' => Carbon::parse($constructionPhase->scheduled_start_date)->addDays($runningTaskDays)->toISOString(),
                    'scheduled_end_date' => Carbon::parse($constructionPhase->scheduled_start_date)->addDays($runningTaskDays + $task->average_days)->toISOString(),
                    'started_at' => null,
                    'ended_at' => null,
                    'count_workers' => 0,
                    'progress' => 0,
                    'daily_reports' => []
                ]);

                $runningTaskDays += $task->average_days;
            }
        }
    }
}
