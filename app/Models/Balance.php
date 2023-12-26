<?php

namespace App\Models;

use App\Helpers\Enums\BalanceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Report;

class Balance extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'user_id',
        'ticket_number',
        'report_id',
        'date',
        'type',
        'model',
        'amount'
    ];
    protected $casts = [
        'amount' => 'float'
    ];

    public function report():Report|null{
        return $this->belongsTo(Report::class)->first();
    }
}
