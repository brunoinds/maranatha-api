<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'description', 'report_id', 'ticket_number', 'commerce_number', 'date', 'job_code', 'expense_code', 'amount', 'qrcode_data', 'image'];

    public function report(){
        return $this->belongsTo(Report::class);
    }
}
