<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class ProjectStructure extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'structure_type',
        'building_type',
        'axes_count',
        'beams_count',
        'columns_count',
        'stringers_count',
        'facades_count',
        'default_phases',
    ];

    protected $casts = [
        'default_phases' => 'array',
    ];
}
