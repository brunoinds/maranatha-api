<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryProductsPack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'inventory_products_ids'
    ];

    protected $casts = [
        'inventory_products_ids' => 'array'
    ];

    public function products()
    {
        return $this->hasMany(InventoryProduct::class, 'id', 'inventory_products_ids');
    }
}
