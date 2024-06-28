<?php

namespace App\Models;

use App\Helpers\Enums\InventoryProductItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Models\InventoryWarehouse;
use App\Models\Job;
use App\Models\Expense;

class InventoryWarehouseOutcome extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'date',
        'user_id',
        'job_code',
        'expense_code',
        'inventory_warehouse_id'
    ];

    public function warehouse()
    {
        return $this->belongsTo(InventoryWarehouse::class);
    }

    public function items()
    {
        return $this->hasMany(InventoryProductItem::class, 'inventory_warehouse_outcome_id');
    }

    public function job()
    {
        return $this->belongsTo(Job::class, 'job_code', 'code');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_code', 'code');
    }

    public function markProductsItemsAsSold()
    {
        $this->items()->each(function ($item) {
            $item->inventory_warehouse_outcome_id = $this->id;
            $item->status = InventoryProductItemStatus::Sold;
            $item->save();
        });
    }

    public function markProductsItemsAsInStock()
    {
        $this->items()->each(function ($item) {
            $item->inventory_warehouse_outcome_id = null;
            $item->status = InventoryProductItemStatus::InStock;
            $item->save();
        });
    }

    public function delete()
    {
        $this->markProductsItemsAsInStock();
        return parent::delete();
    }


}
