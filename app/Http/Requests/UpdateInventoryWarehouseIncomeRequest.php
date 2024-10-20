<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\Enums\MoneyType;

class UpdateInventoryWarehouseIncomeRequest extends FormRequest
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
            'ticket_type' => ['nullable', 'string', 'in:Facture,Bill'],
            'ticket_number' => ['nullable', 'string', 'max:255'],
            'commerce_number' => ['nullable', 'string', 'max:255'],
            'qrcode_data' => ['nullable', 'string', 'max:1000'],
            'currency' => ['required', Rule::in(MoneyType::toArray())],
            'image' => ['nullable', 'string'],
            'job_code' => ['nullable', 'string'],
            'expense_code' => ['nullable', 'string'],
            'products_changes' => ['nullable', 'array'],
            'products_changes.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'products_changes.*.quantity' => ['nullable', 'numeric'],
            'products_changes.*.amount' => ['nullable', 'numeric'],
            'products_changes.*.replaces_by_product_id' => ['nullable', 'integer', 'exists:inventory_products,id'],
        ];
    }
}
