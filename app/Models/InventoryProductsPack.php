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

    // MySQL nao aceita DEFAULT em coluna TEXT: os defaults do schema vivem aqui.
    protected $attributes = [
        'products' => '[]',
    ];

    public function products()
    {
        return $this->hasMany(InventoryProduct::class, 'id', 'products.product_id');
    }

}
