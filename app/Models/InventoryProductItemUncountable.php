<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\Enums\InventoryProductItemUncountableStatus;
use App\Models\InventoryProduct;
use App\Models\InventoryWarehouse;
use App\Models\InventoryWarehouseProductItemLoan;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcome;
use App\Support\Cache\DataCache;
use App\Helpers\Enums\MoneyType;



class InventoryProductItemUncountable extends Model
{
    use HasFactory;
    use \Staudenmeir\EloquentJsonRelations\HasJsonRelationships;



    protected $fillable = [
        'order',
        'batch',

        'quantity_inserted',
        'quantity_used',
        'quantity_remaining',

        'buy_amount',
        'buy_currency',

        'status',

        'inventory_product_id',
        'inventory_warehouse_id',
        'inventory_warehouse_income_id',


        'inventory_warehouse_outcome_ids',
        'origin_inventory_product_item_uncountable_id',
        'outcomes_details'
    ];

    protected $casts = [
        'buy_currency' => MoneyType::class,
        'status' => InventoryProductItemUncountableStatus::class,
        'quantity_inserted' => 'float',
        'quantity_used' => 'float',
        'quantity_remaining' => 'float',
        'inventory_warehouse_outcome_ids' => 'array',
        'outcomes_details' => 'array'
    ];



    public function origin()
    {
        return $this->belongsTo(InventoryProductItemUncountable::class, 'origin_inventory_product_item_uncountable_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'inventory_product_id', 'id');
    }

    public function warehouse()
    {
        return $this->belongsTo(InventoryWarehouse::class, 'inventory_warehouse_id', 'id');
    }

    public function income()
    {
        return $this->belongsTo(InventoryWarehouseIncome::class, 'inventory_warehouse_income_id', 'id');
    }

    public function outcomes()
    {
        return $this->belongsToJson(InventoryWarehouseOutcome::class, 'inventory_warehouse_outcome_ids');
    }

    public function calculateSellPriceFromBuyPrice(float $quantity): float
    {
        $buyAmount = $this->buy_amount;
        $buyQuantity = $this->quantity_inserted;

        if ($buyQuantity == 0) {
            return 0;
        }

        return ($buyAmount / $buyQuantity) * $quantity;
    }

    public function addOutcome(InventoryWarehouseOutcome $outcome, float $quantity)
    {
        //1. Check if there is enough quantity:
        if ($quantity > $this->quantity_remaining) {
            throw new \Exception('There is no enough quantity to add this outcome');
            return;
        }

        //2. Append on array of outcomesIds:
        $outcomesIds = $this->inventory_warehouse_outcome_ids;
        if (!in_array($outcome->id, $outcomesIds)) {
            $outcomesIds[] = $outcome->id;
        }
        $this->inventory_warehouse_outcome_ids = $outcomesIds;

        //3. Append on array of outcomesDetails:
        $outcomesDetails = $this->outcomes_details;
        $outcomesDetails[$outcome->id] = [
            'quantity' => $quantity,
            'sell_amount' => $this->calculateSellPriceFromBuyPrice($quantity),
            'sell_currency' => $this->buy_currency
        ];
        $this->outcomes_details = $outcomesDetails;

        //4. Update quantity_remaining and quantity_used:
        $this->quantity_remaining -= $quantity;
        $this->quantity_used += $quantity;

        //5. Check if needs to change status:
        if ($this->quantity_remaining == 0) {
            $this->status = InventoryProductItemUncountableStatus::Sold;
        }

        //6. Save on DB:
        $this->save();
    }

    public function removeOutcome(InventoryWarehouseOutcome $outcome)
    {
        //1. Remove from array of outcomesIds:
        $outcomesIds = $this->inventory_warehouse_outcome_ids ?? [];
        $outcomesIds = array_values(array_diff($outcomesIds, [$outcome->id]));
        $this->inventory_warehouse_outcome_ids = $outcomesIds;

        //2. Remove from array of outcomesDetails (defensive: missing key can happen with inconsistent state):
        $outcomesDetails = $this->outcomes_details ?? [];

        if (isset($outcomesDetails[$outcome->id])) {
            //3. Update quantity_remaining and quantity_used:
            $this->quantity_remaining += $outcomesDetails[$outcome->id]['quantity'] ?? 0;
            $this->quantity_used -= $outcomesDetails[$outcome->id]['quantity'] ?? 0;
        }

        unset($outcomesDetails[$outcome->id]);
        $this->outcomes_details = $outcomesDetails;

        //4. Check if needs to change status:
        if ($this->quantity_remaining > 0) {
            $this->status = InventoryProductItemUncountableStatus::InStock;
        }

        //5. Save on DB:
        $this->save();
    }

    public function editOutcome(InventoryWarehouseOutcome $outcome, float $quantity)
    {
        $this->removeOutcome($outcome);
        $this->addOutcome($outcome, $quantity);
    }

    public function delete()
    {
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
        parent::delete();
    }
}
