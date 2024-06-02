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
        'description'
    ];


    protected $casts = [
        'currency' => MoneyType::class
    ];

    public function worker(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

}
