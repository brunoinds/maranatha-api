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
use App\Models\Job;
use App\Models\Expense;


class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'description', 'report_id', 'ticket_number', 'commerce_number', 'date', 'job_code', 'expense_code', 'amount', 'qrcode_data', 'image', 'image_size', 'pdf', 'pdf_size'];

    public function amountIn(MoneyType $currency)
    {
        $moneyType = $this->report->money_type;
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
    public function amountInSoles()
    {
        return $this->amountIn(MoneyType::PEN);
    }

    //! This method should be removed soon
    public function amountInDollars()
    {
        return $this->amountIn(MoneyType::USD);
    }

    public function pdfSize(): ?int
    {
        if ($this->pdf === null){
            return null;
        }

        if ($this->pdf_size !== null){
            return $this->pdf_size;
        }

        $path = 'invoices/' . $this->pdf;
        $pdfExists = Storage::disk('public')->exists($path);
        if (!$pdfExists){
            $this->pdf_size = null;
            $this->save();
            return null;
        }

        $pdfSize = Storage::disk('public')->size($path);
        $this->pdf_size = $pdfSize;
        $this->save();
        return $pdfSize;
    }

    public function imageSize() : ?int
    {
        if ($this->image === null){
            return null;
        }

        if ($this->image_size !== null){
            return $this->image_size;
        }

        $path = 'invoices/' . $this->image;
        $imageExists = Storage::disk('public')->exists($path);
        if (!$imageExists){
            $this->image_size = null;
            $this->save();
            return null;
        }

        $imageSize = Storage::disk('public')->size($path);
        $this->image_size = $imageSize;
        $this->save();
        return $imageSize;
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_code', 'code');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_code', 'code');
    }


    public function hasImage()
    {
        return $this->image !== null;
    }
    public function hasPdf()
    {
        return $this->pdf !== null;
    }
    public function attachmentType()
    {
        if ($this->hasPdf()){
            return 'Pdf';
        }
        if ($this->hasImage()){
            return 'Image';
        }
        return null;
    }

    public function deleteImage()
    {
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
    public function deletePdf()
    {
        if (!$this->hasPdf()){
            return;
        }
        $path = 'invoices/' . $this->pdf;

        $pdfExists = Storage::disk('public')->exists($path);
        if (!$pdfExists){
            $this->pdf = null;
            $this->save();
            return;
        }
        Storage::disk('public')->delete($path);
        $this->pdf = null;
        $this->save();
    }

    public function setImageFromBase64(string $base64Image):bool
    {
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

    public function setPdfFromBase64(string $base64Pdf): bool
    {
        $this->deletePdf();

        $pdfEncoded = base64_decode($base64Pdf);
        $pdfId = Str::random(40);
        $path = 'invoices/' . $pdfId;

        $wasSuccessfull = Storage::disk('public')->put($path, $pdfEncoded);

        $this->pdf = $pdfId;
        $this->save();

        return $wasSuccessfull;
    }
}
