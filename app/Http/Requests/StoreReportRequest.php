<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
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
            'user_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:100'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
            'status' => ['required', Rule::in(['Draft', 'Submitted'])],
            'type' => ['required', Rule::in(['Bill', 'Facture'])],
            'money_type' => ['required', Rule::in(['PEN', 'USD'])]
        ];
    }
}