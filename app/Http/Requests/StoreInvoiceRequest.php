<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreInvoiceRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'report_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['Facture', 'Bill'])],
            'description' => ['required', 'string', 'max:100'],
            'ticket_number' => ['required', 'string', 'max:100'],
            'commerce_number' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'job_code' => ['required', 'string', 'max:100'],
            'expense_code' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'max:999999999999.99'],
            'qrcode_data' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'string'],
        ];
    }
}
