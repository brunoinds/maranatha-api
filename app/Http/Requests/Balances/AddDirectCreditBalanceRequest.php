<?php

namespace App\Http\Requests\Balances;

use Illuminate\Foundation\Http\FormRequest;

class AddDirectCreditBalanceRequest extends FormRequest
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
            'description' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'max:999999.99'],
            'date' => ['required', 'date'],
            'ticket_number' => ['nullable', 'string', 'max:100'],
            'receipt_base64' => ['nullable', 'string'],
            'receipt_type' => ['nullable', 'string', 'in:Image,Pdf'],
        ];
    }
}
