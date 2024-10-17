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
use App\Models\User;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcomeRequest;

use App\Support\Toolbox\TString;
use App\Support\Cache\DataCache;

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

    public function products()
    {
        return $this->hasManyThrough(InventoryProduct::class, InventoryProductItem::class, 'inventory_warehouse_outcome_id', 'id', 'id', 'inventory_product_id');
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

        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }


    public function transferItemsAsIncomesToWarehouse(InventoryWarehouse $warehouse)
    {
        $incomes = $this->items->groupBy(function($item){
            return $item->inventory_warehouse_income_id;
        });

        foreach ($incomes as $incomeId => $items){
            $income = InventoryWarehouseIncome::find($incomeId);

            $newIncome = InventoryWarehouseIncome::create([
                'description' => $income->description,
                'date' => $income->date,
                'ticket_type' => $income->ticket_type,
                'ticket_number' => $income->ticket_number,
                'commerce_number' => $income->commerce_number,
                'qrcode_data' => $income->qrcode_data,
                'image' => $income->image,
                'currency' => $income->currency,
                'job_code' => $income->job_code,
                'expense_code' => $income->expense_code,
                'inventory_warehouse_id' => $warehouse->id,
                'origin_inventory_warehouse_income_id' => $income->id,
            ]);

            $lastOrder = InventoryProductItem::orderBy('order', 'desc')->first();
            $lastOrder = $lastOrder ? $lastOrder->order : -1;

            $i = 0;
            $items->each(function($item) use ($income, $newIncome, $lastOrder, &$i, $warehouse){
                $newItem = InventoryProductItem::create([
                    'batch' => TString::generateRandomBatch(),
                    'order' => $lastOrder + $i + 1,
                    'buy_amount' => $item->buy_amount,
                    'buy_currency' =>  $item->buy_currency,
                    'sell_amount' => $item->sell_amount,
                    'sell_currency' => $item->sell_currency,
                    'inventory_product_id' => $item->inventory_product_id,
                    'inventory_warehouse_id' => $warehouse->id,
                    'inventory_warehouse_income_id' => $newIncome->id,
                    'origin_inventory_product_item_id' => $item->id,
                ]);
                $i++;
            });
        }

        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }

    public function delete()
    {
        $this->markProductsItemsAsInStock();
        return parent::delete();
    }
}
