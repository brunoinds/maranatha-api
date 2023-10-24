<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'type', 'from_date', 'to_date', 'status', 'exported_pdf', 'rejection_reason', 'approved_at', 'rejected_at', 'submitted_at'];

    public function amount(){
        return $this->invoices()->sum('amount');
    }
    
    public function invoices(){
        return $this->hasMany(Invoice::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
}