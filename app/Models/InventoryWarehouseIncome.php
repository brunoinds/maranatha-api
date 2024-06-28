<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Models\InventoryWarehouse;
use App\Models\Job;
use App\Models\Expense;


class InventoryWarehouseIncome extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'date',
        'ticket_number',
        'commerce_number',
        'qrcode_data',
        'image',
        'amount',
        'currency',
        'job_code',
        'expense_code',
        'inventory_warehouse_id'
    ];

    protected $casts = [
    ];

    public function warehouse()
    {
        return $this->belongsTo(InventoryWarehouse::class);
    }

    public function items()
    {
        return $this->hasMany(InventoryProductItem::class, 'inventory_warehouse_income_id');
    }

    public function job()
    {
        return $this->belongsTo(Job::class. 'job_code', 'code');
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class, 'expense_code', 'code');
    }

    public function delete()
    {
        $this->items()->delete();
        return parent::delete();
    }
}
