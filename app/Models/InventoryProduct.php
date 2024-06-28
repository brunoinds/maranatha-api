<?php

namespace App\Models;

use App\Helpers\Enums\InventoryProductStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\InventoryProductItem;
use App\Helpers\Enums\InventoryProductItemStatus;
use App\Helpers\Enums\InventoryProductUnit;


class InventoryProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'category',
        'brand',
        'presentation',
        'unit',
        'code',
        'status',
        'image'
    ];

    protected $casts = [
        'unit' => InventoryProductUnit::class,
        'status' => InventoryProductStatus::class
    ];

    public function items()
    {
        return $this->hasMany(InventoryProductItem::class);
    }

    public function stockCount()
    {
        return $this->items()->where('status', InventoryProductItemStatus::InStock)->count();
    }

    public function averageAmount()
    {
        return $this->items()->avg('amount');
    }


    public function delete()
    {
        $this->items()->delete();

        //Delete product on ProductPacks that contains this product:
        $productPacks = InventoryProductsPack::whereJsonContains('inventory_products_ids', $this->id)->get();
        foreach ($productPacks as $productPack) {
            $productPack->inventory_products_ids = array_diff($productPack->inventory_products_ids, [$this->id]);
            $productPack->save();
        }

        return parent::delete();
    }
}
