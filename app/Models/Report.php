<?php

namespace App\Models;

use App\Helpers\Enums\MoneyType;
use App\Helpers\Toolbox;
use Brunoinds\SunatDolarLaravel\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Brunoinds\SunatDolarLaravel\Exchange;
use App\Helpers\Enums\ReportStatus;
use DateTime;


class Report extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'type', 'money_type', 'from_date', 'to_date', 'status', 'exported_pdf', 'rejection_reason', 'approved_at', 'rejected_at', 'submitted_at'];

    protected $casts = [
        'money_type' => MoneyType::class,
        'status' => ReportStatus::class
    ];
    public function amount(){
        return $this->invoices()->sum('amount');
    }

    public function amountInSoles(){
        if ($this->money_type === MoneyType::PEN){
            return $this->amount();
        }elseif ($this->money_type === MoneyType::USD){
            $totalInSoles = 0;
            $this->invoices()->each(function($invoice) use (&$totalInSoles){
                $date = new DateTime($invoice->date);
                $amountInSoles = Exchange::on($date)->convert(\Brunoinds\SunatDolarLaravel\Enums\Currency::USD, $invoice->amount)->to(\Brunoinds\SunatDolarLaravel\Enums\Currency::PEN);
                $totalInSoles += $amountInSoles;
            });
            return $totalInSoles;
        }
    }
    
    public function invoices(){
        return $this->hasMany(Invoice::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
    public function balances(){
        return $this->hasMany(Balance::class);
    }
    public function delete(){
        $this->invoices()->delete();
        $this->balances()->delete();
        return parent::delete();
    }
    public function firstInvoiceDate(){
        return $this->invoices()->orderBy('date', 'asc')->first()->date;
    }
    public function lastInvoiceDate(){
        return $this->invoices()->orderBy('date', 'desc')->first()->date;
    }
}