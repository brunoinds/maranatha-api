<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryProductsPack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'products'
    ];

    protected $casts = [
        'products' => 'array'
    ];

    public function products()
    {
        return $this->hasMany(InventoryProduct::class, 'id', 'products.product_id');
    }

}
