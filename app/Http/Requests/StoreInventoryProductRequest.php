<?php

namespace App\Http\Requests;

use App\Helpers\Enums\InventoryProductStatus;
use App\Helpers\Enums\InventoryProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:1000'],
            'description' => ['string', 'nullable', 'max:1000'],
            'category' => ['string','nullable', 'max:255'],
            'brand' => ['string','nullable', 'max:255'],
            'presentation' => ['string','nullable', 'max:255'],
            'unit' => ['string', Rule::in(InventoryProductUnit::toArray())],
            'code' => ['string','nullable', 'max:500'],
            'status' => ['string', Rule::in(InventoryProductStatus::toArray())],
            'image' => ['nullable', 'url:http,https'],
            'is_loanable' => ['boolean'],
        ];
    }
}
