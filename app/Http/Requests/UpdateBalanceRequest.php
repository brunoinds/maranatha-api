<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBalanceRequest extends FormRequest
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
            'description' => ['string', 'max:100'],
            'amount' => ['numeric', 'max:999999.99'],
            'date' => ['date'],
            'ticket_number' => ['nullable', 'string', 'max:100'],
            'receipt_base64' => ['nullable', 'string'],
            'receipt_type' => ['nullable', 'string', 'in:Image,Pdf'],
        ];
    }
}
