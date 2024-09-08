<?php

namespace App\Http\Requests;

use App\Helpers\Enums\InventoryWarehouseProductItemLoanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryWarehouseProductItemLoanBulkRequest extends FormRequest
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
            'loaned_to_user_id' => ['required', 'integer'],
            'loaned_at' => ['nullable', 'date'],
            'job_code' => ['required', 'string'],
            'expense_code' => ['required', 'string'],
            'products_items_ids' => ['required', 'array'],
            'products_items.*.id' => ['required', 'integer', 'exists:inventory_product_items,id'],
            'inventory_warehouse_id' => ['required', 'integer'],
            'inventory_warehouse_outcome_request_id' => ['nullable', 'integer', 'exists:inventory_warehouse_outcome_requests,id'],
        ];
    }
}
