<?php

namespace App\Support\Generators\Records\Inventory;

use Illuminate\Support\Collection;

use App\Models\InventoryProduct;

class RecordInventoryProducts
{
    private array|null $categories = null;
    private array|null $subCategories = null;

    /**
        * RecordInventoryProducts constructor.

        * @param array $options
        * @param string|null $options['categories']
        * @param string|null $options['subCategories']
     */

    public function __construct(array $options){

        $this->categories = $options['categories'] ?? null;
        $this->subCategories = $options['subCategories'] ?? null;
    }

    private function getProducts():Collection
    {
        $options = [
            'categories' => $this->categories,
            'subCategories' => $this->subCategories
        ];

        $query = InventoryProduct::query();

        if ($options['categories'] !== null){
            $query = $query->whereIn('category', $options['categories']);
        }
        if ($options['subCategories'] !== null){
            $query = $query->whereIn('sub_category', $options['subCategories']);
        }
        return collect($query->get());
    }

    private function createTable():array{
        $items = $this->getProducts();

        $body = collect($items)->map(function($item){
            return [
                'id' => $item->id,
                'name' => $item->name,
                'description' => $item->description,
                'category' => $item->category,
                'sub_category' => $item->sub_category,
                'brand' => $item->brand,
                'presentation' => $item->presentation,
                'unit' => $item->unit,
                'code' => $item->code,
                'status' => $item->status,
                'image' => $item->image,
                'is_loanable' => $item->is_loanable ? 'Sí' : 'No'
            ];
        });

        $body = array_column($body->toArray(), null);

        return [
            'headers' => [
                [
                    'title' => 'ID',
                    'key' => 'id'
                ],
                [
                    'title' => 'Nombre',
                    'key' => 'name'
                ],
                [
                    'title' => 'Descripción',
                    'key' => 'description'
                ],
                [
                    'title' => 'Categoría',
                    'key' => 'category'
                ],
                [
                    'title' => 'Sub Categoría',
                    'key' => 'sub_category'
                ],
                [
                    'title' => 'Marca',
                    'key' => 'brand'
                ],
                [
                    'title' => 'Presentación',
                    'key' => 'presentation'
                ],
                [
                    'title' => 'Unidad',
                    'key' => 'unit'
                ],
                [
                    'title' => 'Código',
                    'key' => 'code'
                ],
                [
                    'title' => 'Estado',
                    'key' => 'status'
                ],
                [
                    'title' => 'Imagen',
                    'key' => 'image'
                ],
                [
                    'title' => 'Es Prestable',
                    'key' => 'is_loanable'
                ]
            ],
            'body' => $body,
        ];
    }


    public function generate():array{
        return [
            'data' => $this->createTable(),
            'query' => [
                'categories' => $this->categories,
                'subCategories' => $this->subCategories
            ],
        ];
    }
}
