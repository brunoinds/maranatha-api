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
    public function deleteReceiptImage():void{
        $path = 'balances/' . $this->id;
        Storage::disk('public')->delete($path);
    }
    public function getReceiptImageUrl():string|null{
        if (!$this->hasReceiptImage()) {
            return null;
        }
        $path = 'balances/' . $this->id;


        $image = Storage::disk('public')->url($path);
        return $image;
    }

    public function hasReceiptImage():bool{
        $path = 'balances/' . $this->id;
        $imageExists = Storage::disk('public')->exists($path);
        return $imageExists;
    }

    public function getReceiptImageInBase64():string|null{
        if (!$this->hasReceiptImage()) {
            return null;
        }
        $path = 'balances/' . $this->id;
        $image = Storage::disk('public')->get($path);
        $image = base64_encode($image);
        return $image;
    }
}
