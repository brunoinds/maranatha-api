<?php

namespace App\Models;

use App\Helpers\Enums\InventoryProductItemStatus;
use App\Helpers\Enums\InventoryWarehouseProductItemLoanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Models\InventoryWarehouse;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Models\User;
use App\Support\Cache\DataCache;



class InventoryWarehouseProductItemLoan extends Model
{
    use HasFactory;


    protected $fillable = [
        'loaned_to_user_id',
        'loaned_by_user_id',
        'job_code',
        'expense_code',
        'loaned_at',
        'received_at',
        'returned_at',
        'confirm_returned_at',
        'status',
        'movements',
        'intercurrences',
        'inventory_product_item_id',
        'inventory_warehouse_id',
        'inventory_warehouse_outcome_request_id',
    ];

    protected $casts = [
        'movements' => 'array',
        'intercurrences' => 'array',
    ];

    public function productItem()
    {
        return $this->belongsTo(InventoryProductItem::class, 'inventory_product_item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(InventoryWarehouse::class, 'inventory_warehouse_id');
    }

    public function loanedBy()
    {
        return $this->belongsTo(User::class, 'loaned_by_user_id');
    }

    public function loanedTo()
    {
        return $this->belongsTo(User::class, 'loaned_to_user_id');
    }

    public function outcomeRequest()
    {
        return $this->belongsTo(InventoryWarehouseOutcomeRequest::class, 'inventory_warehouse_outcome_request_id');
    }

    public function doSendToLoan()
    {
        $this->productItem->status = InventoryProductItemStatus::Loaned;
        $this->productItem->save();

        if (!$this->loaned_at){
            $this->loaned_at = now();
        }
        $this->status = InventoryWarehouseProductItemLoanStatus::SendingToLoan;
        $this->save();
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }

    public function doReceivedFromWarehouse()
    {
        $this->status = InventoryWarehouseProductItemLoanStatus::OnLoan;
        $this->received_at = now();
        $this->returned_at = null;
        $this->confirm_returned_at = null;
        $this->save();
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }

    public function undoReceivedFromWarehouse()
    {
        $this->status = InventoryWarehouseProductItemLoanStatus::SendingToLoan;
        $this->received_at = null;
        $this->save();
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }

    public function doReturnToWarehouse()
    {
        $this->status = InventoryWarehouseProductItemLoanStatus::ReturningToWarehouse;
        $this->returned_at = now();
        $this->confirm_returned_at = null;
        $this->save();
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }

    public function undoReturnToWarehouse()
    {
        $this->status = InventoryWarehouseProductItemLoanStatus::OnLoan;
        $this->returned_at = null;
        $this->save();
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }

    public function doConfirmReturnedToWarehouse()
    {
        if ($this->productItem->status != InventoryProductItemStatus::InRepair && $this->productItem->status != InventoryProductItemStatus::WriteOff){
            $this->productItem->status = InventoryProductItemStatus::InStock;
            $this->productItem->save();
        }

        $this->status = InventoryWarehouseProductItemLoanStatus::Returned;
        $this->confirm_returned_at = now();
        $this->save();
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }

    public function undoConfirmReturnedToWarehouse()
    {
        $this->productItem->status = InventoryProductItemStatus::Loaned;
        $this->productItem->save();

        $this->status = InventoryWarehouseProductItemLoanStatus::OnLoan;
        $this->confirm_returned_at = null;
        $this->save();
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);
    }

    public function delete()
    {
        if ($this->productItem->status != InventoryProductItemStatus::InRepair && $this->productItem->status != InventoryProductItemStatus::WriteOff){
            $this->productItem->status = InventoryProductItemStatus::InStock;
            $this->productItem->save();
        }
        DataCache::clearRecord('warehouseStockList', [$this->inventory_warehouse_id]);

        parent::delete();
    }
}
