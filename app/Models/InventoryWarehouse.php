<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcome;



class InventoryWarehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'zone',
        'country'
    ];

    public function products()
    {
        return $this->hasMany(InventoryProductItem::class);
    }

    public function incomes()
    {
        return $this->hasMany(InventoryWarehouseIncome::class);
    }

    public function outcomes()
    {
        return $this->hasMany(InventoryWarehouseOutcome::class);
    }

    public function delete()
    {
        $this->products()->delete();
        $this->outcomes()->delete();
        $this->incomes()->delete();
        return parent::delete();
    }
}
