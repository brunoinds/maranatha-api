<?php

namespace App\Http\Requests;

use App\Helpers\Enums\InventoryProductItemStatus;
use App\Helpers\Enums\MoneyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryProductItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'integer'],
            'batch' => ['string', 'max:255'],
            'amount' => ['required', 'numeric'],
            'currency' => ['required', 'string', Rule::in(MoneyType::toArray())],
            'status' => ['string', Rule::in(InventoryProductItemStatus::toArray())],
            'inventory_product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'inventory_warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'inventory_warehouse_income_id' => ['required', 'integer', 'exists:inventory_warehouse_incomes,id'],
            'inventory_warehouse_outcome_id' => ['integer', 'exists:inventory_warehouse_outcomes,id'],
        ];
    }
}
