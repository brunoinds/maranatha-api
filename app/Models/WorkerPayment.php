<?php

namespace App\Models;

use App\Helpers\Enums\MoneyType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkerPayment extends Model
{
    use HasFactory;


    protected $fillable = [
        'worker_id',
        'month',
        'year',
        'amount',
        'currency',
        'description',
        'divisions'
    ];


    protected $casts = [
        'currency' => MoneyType::class,
        'divisions' => 'array'
    ];

    public function worker(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

}
