<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Models\InventoryProductItemUncountable;
use App\Models\InventoryWarehouse;
use App\Models\Job;
use App\Models\Expense;
use App\Helpers\Enums\MoneyType;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use App\Models\InventoryProduct;
use App\Support\Cache\DataCache;


class InventoryWarehouseIncome extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'date',
        'ticket_type',
        'ticket_number',
        'commerce_number',
        'qrcode_data',
        'image',
        'currency',
        'job_code',
        'expense_code',
        'inventory_warehouse_id',
        'origin_inventory_warehouse_income_id'
    ];

    protected $casts = [
        'currency' => MoneyType::class,
    ];


    public function origin()
    {
        return $this->belongsTo(InventoryWarehouseIncome::class, 'origin_inventory_warehouse_income_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(InventoryWarehouse::class, 'inventory_warehouse_id', 'id');
    }

    public function items()
    {
        return $this->hasMany(InventoryProductItem::class, 'inventory_warehouse_income_id', 'id');
    }

    public function uncountableItems()
    {
        return $this->hasMany(InventoryProductItemUncountable::class);
    }

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_code', 'code');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_code', 'code');
    }

    public function amount()
    {
        return $this->items()->sum('buy_amount')  + $this->uncountableItems()->sum('buy_amount');
    }

    public function quantities()
    {
        return $this->items()->count() + $this->uncountableItems()->sum('quantity_inserted');
    }

    public function setImageFromBase64(string $base64Image):bool
    {
        $imageResource = Image::make($base64Image);
        $imageEncoded = $imageResource->encode('png')->getEncoded();

        $imageId = $this->id;
        $path = 'warehouse-incomes/' . $imageId;

        $wasSuccessfull = Storage::disk('public')->put($path, $imageEncoded);

        $this->image = $imageId;
        $this->save();
        return $wasSuccessfull;
    }
    public function deleteImage(): void
    {
        $path = 'warehouse-incomes/' . $this->id;
        Storage::disk('public')->delete($path);

        $this->image = null;
        $this->save();
    }



    public function delete()
    {
        $this->items()->each(function($item){
            $item->delete();
        });
        $this->uncountableItems()->each(function($item){
            $item->delete();
        });
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
        return parent::delete();
    }
}
