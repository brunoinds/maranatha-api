<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\AttendanceDayWorker;
use App\Models\WorkerPayment;

class Worker extends Model
{
    use HasFactory;


    protected $fillable = [
        'dni',
        'name',
        'is_active',
        'supervisor',
        'team',
        'country',
        'role',
        'history'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'history' => 'array'
    ];


    public function attendanceDays(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceDayWorker::class, 'worker_dni', 'dni');
    }


    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WorkerPayment::class, 'worker_id', 'id');
    }

    public function createHistorySnapshot(): void
    {
        $currentHistory = $this->toArray();
        unset($currentHistory['history']);
        unset($currentHistory['created_at']);
        unset($currentHistory['updated_at']);
        unset($currentHistory['id']);
        $currentHistory = [
            'datetime' => now()->toDateTimeString(),
            'data' => $currentHistory
        ];

        $this->history = array_merge($this->history, [$currentHistory]);
        $this->save();
    }


    public function delete(): ?bool
    {
        //$this->attendanceDays()->delete();
        $this->payments()->delete();
        return parent::delete();
    }
}
