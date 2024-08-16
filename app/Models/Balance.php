<?php

namespace App\Models;

use App\Helpers\Enums\BalanceModel;
use App\Helpers\Enums\BalanceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

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

    public function setReceiptImageFromBase64(string $base64Image):bool{
        $imageResource = Image::make($base64Image);
        $imageEncoded = $imageResource->encode('png')->getEncoded();

        $imageId = $this->id;
        $path = 'balances/' . $imageId;

        $wasSuccessfull = Storage::disk('public')->put($path, $imageEncoded);
        return $wasSuccessfull;
    }
    public function setReceiptPdfFromBase64(string $base64Pdf): bool{
        $pdfEncoded = base64_decode($base64Pdf);
        $pdfId = $this->id;
        $path = 'balances/' . $pdfId;

        $wasSuccessfull = Storage::disk('public')->put($path, $pdfEncoded);
        return $wasSuccessfull;
    }
    public function deleteReceiptImage():void{
        $path = 'balances/' . $this->id;
        Storage::disk('public')->delete($path);
    }
    public function getReceiptUrl():string|null{
        if (!$this->hasReceipt()) {
            return null;
        }
        $path = 'balances/' . $this->id;


        $image = Storage::disk('public')->url($path);
        return $image;
    }

    public function hasReceipt():bool{
        $path = 'balances/' . $this->id;
        $imageExists = Storage::disk('public')->exists($path);
        return $imageExists;
    }
    public function getReceiptType()
    {
        if (!$this->hasReceipt()) {
            return null;
        }
        $path = 'balances/' . $this->id;
        $data = Storage::disk('public')->get($path);
        $isPdf = strpos($data, '%PDF-') === 0;
        return $isPdf ? 'Pdf' : 'Image';
    }

    public function getReceiptInBase64():string|null{
        if (!$this->hasReceipt()) {
            return null;
        }
        $path = 'balances/' . $this->id;
        $data = Storage::disk('public')->get($path);
        $data = base64_encode($data);
        return $data;
    }
}
