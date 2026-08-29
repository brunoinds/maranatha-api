<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'uses'
    ];


    protected $casts = [
        'uses' => 'array'
    ];

    // MySQL nao aceita DEFAULT em coluna TEXT: os defaults do schema vivem aqui.
    protected $attributes = [
        'uses' => '["Reports", "Attendances"]',
    ];
}
