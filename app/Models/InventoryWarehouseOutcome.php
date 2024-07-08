<?php

namespace App\Models;

use App\Helpers\Enums\InventoryProductItemStatus;
use App\Helpers\Enums\MoneyType;
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
        return $this->belongsTo(InventoryWarehouse::class, 'inventory_warehouse_id', 'id');
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function request()
    {
        return $this->hasOne(InventoryWarehouseOutcomeRequest::class);
    }

    public function amount()
    {
        $currencies = MoneyType::toAssociativeArray(0);
        $this->items->each(function ($item) use (&$currencies) {
            if (!isset($currencies[$item->sell_currency])) {
                $currencies[$item->sell_currency] = 0;
            }
            $currencies[$item->sell_currency] += $item->sell_amount;
        });

        foreach ($currencies as $currency => $amount) {
            if ($amount == 0) {
                unset($currencies[$currency]);
            }
        }

        //Convert into array of objects:
        $currencies = array_map(function ($amount, $currency) {
            return (object) [
                'currency' => $currency,
                'amount' => $amount
            ];
        }, $currencies, array_keys($currencies));

        return $currencies;
    }

    public function markProductsItemsAsInStock()
    {
        $this->items()->each(function ($item) {
            $item->inventory_warehouse_outcome_id = null;
            $item->status = InventoryProductItemStatus::InStock;
            $item->sell_amount = $item->buy_amount;
            $item->sell_currency = $item->buy_currency;
            $item->save();
        });
    }

    public function delete()
    {
        $this->markProductsItemsAsInStock();
        return parent::delete();
    }
}
