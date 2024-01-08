<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Report;
use DateTime;
use Brunoinds\SunatDolarLaravel\Exchange;
use Brunoinds\SunatDolarLaravel\Enums\Currency;
use App\Helpers\Enums\MoneyType;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;



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


    public function hasImage(){
        return $this->image !== null;
    }

    public function deleteImage(){
        if (!$this->hasImage()){
            return;
        }
        $path = 'invoices/' . $this->image;

        $imageExists = Storage::disk('public')->exists($path);
        if (!$imageExists){
            $this->image = null;
            $this->save();
            return;
        }
        Storage::disk('public')->delete($path);
        $this->image = null;
        $this->save();
    }
    public function setImageFromBase64(string $base64Image):bool{
        $this->deleteImage();

        $imageResource = Image::make($base64Image);
        $imageEncoded = $imageResource->encode('png')->getEncoded();

        $imageId = Str::random(40);
        $path = 'invoices/' . $imageId;

        $wasSuccessfull = Storage::disk('public')->put($path, $imageEncoded);

        $this->image = $imageId;
        $this->save();

        return $wasSuccessfull;
    }
}
