<?php

namespace App\Http\Requests;

use App\Helpers\Enums\InventoryWarehouseProductItemLoanStatus;
use App\Models\InventoryWarehouseProductItemLoan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryWarehouseProductItemLoanRequest extends FormRequest
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
            'received_at' => ['nullable', 'date'],
            'returned_at' => ['nullable', 'date'],
            'confirm_returned_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(InventoryWarehouseProductItemLoanStatus::toArray())],
            'movements' => ['present', 'array'],
            'movement.*.id' => ['required', 'string'],
            'movement.*.user_id' => ['required', 'exists:users,id'],
            'movement.*.job_code' => ['required', 'string'],
            'movement.*.expense_code' => ['required', 'string'],
            'movement.*.date' => ['required', 'date'],
            'movement.*.description' => ['required', 'string'],
            'intercurrences' => ['present', 'array'],
            'intercurrences.*.id' => ['required', 'string'],
            'intercurrences.*.user_id' => ['required', 'exists:users,id'],
            'intercurrences.*.date' => ['required', 'date'],
            'intercurrences.*.description' => ['required', 'string'],
            'inventory_warehouse_id' => ['required', 'integer'],
            'inventory_warehouse_outcome_request_id' => ['nullable', 'integer', 'exists:inventory_warehouse_outcome_requests,id'],
        ];
    }
}
