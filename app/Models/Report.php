<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'from_date', 'to_date', 'status', 'project_code', 'exported_pdf'];


    public function invoices(){
        return $this->hasMany(Invoice::class);
    }
}