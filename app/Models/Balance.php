<?php

namespace App\Models;

use App\Helpers\Enums\BalanceModel;
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
        'amount' => 'float',
        'type' => BalanceType::class,
        'model' => BalanceModel::class
    ];

    public function report():Report|null{
        return $this->belongsTo(Report::class)->first();
    }

    public function hasReceiptImage():bool{
        $path = 'balances/' . $this->id;
        $imageExists = Storage::disk('public')->exists($path);
        return $imageExists;
    }

    public function getReceiptImageInBase64():string{
        $path = 'balances/' . $this->id;
        $image = Storage::disk('public')->get($path);
        $image = base64_encode($image);
        return $image;
    }
}
