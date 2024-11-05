<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class StoreInventoryWarehouseOutcomeRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'job_code' => ['nullable', 'string', 'max:255'],
            'expense_code' => ['nullable', 'string', 'max:255'],
            'inventory_warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'products_items' => ['present', 'array'],
            'products_items.*.id' => ['required', 'integer', 'exists:inventory_product_items,id'],
            'products_items_uncountable' => ['present', 'array'],
            'products_items_uncountable.*.id' => ['required', 'integer', 'exists:inventory_product_item_uncountables,id'],
            'products_items_uncountable.*.quantity' => ['required', 'numeric', 'min:0'],
            'outcome_request_id' => ['nullable', 'integer', 'exists:inventory_warehouse_outcome_requests,id'],
        ];
    }
}
