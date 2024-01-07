<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Report;
use DateTime;
use Brunoinds\SunatDolarLaravel\Exchange;
use Brunoinds\SunatDolarLaravel\Enums\Currency;
use App\Helpers\Enums\MoneyType;


class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'description', 'report_id', 'ticket_number', 'commerce_number', 'date', 'job_code', 'expense_code', 'amount', 'qrcode_data', 'image'];


    public function amountInSoles(){
        $moneyType = $this->report()?->money_type;

        if ($moneyType === MoneyType::PEN){
            return $this->amount;
        }elseif ($moneyType === MoneyType::USD){
            $date = new DateTime($this->date);
            return Exchange::on($date)->convert(\Brunoinds\SunatDolarLaravel\Enums\Currency::USD, $this->amount)->to(\Brunoinds\SunatDolarLaravel\Enums\Currency::PEN);
        }
    }

    public function amountInDollars(){
        $moneyType = $this->report()?->money_type;

        if ($moneyType === MoneyType::USD){
            return $this->amount;
        }elseif ($moneyType === MoneyType::PEN){
            $date = new DateTime($this->date);
            return Exchange::on($date)->convert(\Brunoinds\SunatDolarLaravel\Enums\Currency::PEN, $this->amount)->to(\Brunoinds\SunatDolarLaravel\Enums\Currency::USD);
        }
    }

    public function report(){
        return $this->belongsTo(Report::class)->first();
    }
}
