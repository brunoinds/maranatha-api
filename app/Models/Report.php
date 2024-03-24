<?php

namespace App\Models;

use App\Helpers\Enums\MoneyType;
use App\Helpers\Toolbox;
use Brunoinds\SunatDolarLaravel\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\Enums\ReportStatus;
use App\Support\Exchange\Exchanger;
use DateTime;


class Report extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'type', 'money_type', 'from_date', 'to_date', 'status', 'exported_pdf', 'rejection_reason', 'approved_at', 'rejected_at', 'submitted_at', 'metadata', 'country'];

    protected $casts = [
        'money_type' => MoneyType::class,
        'status' => ReportStatus::class,
        'metadata' => 'array',
    ];
    public function amount(){
        return $this->invoices()->sum('amount');
    }

    public function amountIn(MoneyType $currency){
        $moneyType = MoneyType::from($this->money_type->value);
        if ($this->money_type === $currency){
            return $this->amount();
        }else {
            $total = 0;
            $this->invoices()->each(function($invoice) use (&$total, $currency, $moneyType){
                $date = new DateTime($invoice->date);
                $amountInCurrency = Exchanger::on($date)->convert($invoice->amount,$moneyType, $currency);
                $total += $amountInCurrency;
            });
            return $total;
        }
    }

    //! This method should be removed soon
    public function amountInSoles(){
        return $this->amountIn(MoneyType::PEN);
    }

    //! This method should be removed soon
    public function amountInDollars(){
        return $this->amountIn(MoneyType::USD);
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
        $firstInvoice = $this->invoices()->orderBy('date', 'asc')->first();
        if ($firstInvoice === null){
            return null;
        }
        return $firstInvoice->date;
    }
    public function lastInvoiceDate(){
        $lastInvoice = $this->invoices()->orderBy('date', 'desc')->first();

        if ($lastInvoice === null){
            return null;
        }
        return $lastInvoice->date;
    }
}