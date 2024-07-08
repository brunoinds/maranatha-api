<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryWarehouseOutcomeRequestRequest extends FormRequest
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
            'job_code' => ['nullable', 'string', 'max:255'],
            'expense_code' => ['nullable', 'string', 'max:255'],
            'inventory_warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id'],
            'requested_products' => ['required', 'array'],
            'requested_products.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'requested_products.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
