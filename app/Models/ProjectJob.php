<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProjectStructure;
use App\Models\User;



class ProjectJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_code',
        'project_structure_id',
        'width',
        'height',
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
        'messages',
    ];

    protected $casts = [
        'admins_ids' => 'array',
        'final_report' => 'array',
        'messages' => 'array',
    ];

    public function projectStructure()
    {
        return $this->belongsTo(ProjectStructure::class);
    }

    public function admins()
    {
        return $this->hasMany(User::class, 'id', 'admins_ids');
    }
}
