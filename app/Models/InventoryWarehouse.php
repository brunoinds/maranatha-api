<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Models\InventoryWarehouseIncome;
use App\Models\InventoryWarehouseOutcome;
use App\Support\Assistants\InventoryAssistant;
use App\Models\InventoryWarehouseOutcomeRequest;
use App\Models\InventoryWarehouseProductItemLoan;
use App\Models\User;



class InventoryWarehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'zone',
        'country',
        'owners'
    ];

    protected $casts = [
        'owners' => 'array'
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

    public function loans()
    {
        return $this->hasMany(InventoryWarehouseProductItemLoan::class);
    }

    public function items()
    {
        return $this->hasMany(InventoryProductItem::class);
    }


    public function outcomeRequests()
    {
        return $this->hasMany(InventoryWarehouseOutcomeRequest::class);
    }

    public function stock(): array
    {
        return InventoryAssistant::getWarehouseStock($this)->toArray();
    }

    public function isOwner(User $user): bool
    {
        if ($user->isAdmin()) return true;
        return in_array($user->id, $this->owners);
    }

    public function delete()
    {
        $this->products()->delete();
        $this->outcomes()->delete();
        $this->incomes()->delete();
        $this->outcomeRequests()->delete();
        return parent::delete();
    }
}
