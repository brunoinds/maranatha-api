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
            'description' => ['required', 'string', 'max:1000'],
            'date' => ['required', 'date'],
            'user_id' => ['nullable', 'integer'],
            'job_code' => ['nullable', 'string'],
            'expense_code' => ['nullable', 'string'],
            'inventory_warehouse_id' => ['required', 'integer', 'exists:inventory_warehouses,id']
        ];
    }
}
