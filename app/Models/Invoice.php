<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Report;
use DateTime;
use App\Helpers\Enums\MoneyType;
use App\Support\Exchange\Exchanger;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;



class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'description', 'report_id', 'ticket_number', 'commerce_number', 'date', 'job_code', 'expense_code', 'amount', 'qrcode_data', 'image'];



    public function amountIn(MoneyType $currency)
    {
        $moneyType = $this->report()?->money_type;
        if ($moneyType === $currency){
            return $this->amount;
        }else {
            $date = new DateTime($this->date);
            return Exchanger::on($date)->convert($this->amount, $moneyType, $currency);
        }
    }
    public function amountInAll()
    {
        $instance = $this;
        $results = MoneyType::toAssociativeArray(0);
        collect(MoneyType::toAssociativeArray(0))->each(function($value, $key) use ($instance, &$results){
            $results[$key] = $instance->amountIn($key);
        });

        return $results;
    }

    //! This method should be removed soon
    public function amountInSoles(){
        return $this->amountIn(MoneyType::PEN);
    }

    //! This method should be removed soon
    public function amountInDollars(){
        return $this->amountIn(MoneyType::USD);
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
