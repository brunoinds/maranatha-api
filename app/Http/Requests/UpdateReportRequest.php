<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportRequest extends FormRequest
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
            'user_id' => ['integer'],
            'title' => ['string', 'max:100'],
            'from_date' => ['date'],
            'to_date' => ['date'],
            'status' => [Rule::in(['Draft', 'Submitted', 'Approved', 'Rejected'])],
            'rejection_reason' => ['string', 'max:100', 'nullable'],
            'type' => [Rule::in(['Bill', 'Facture'])],
        ];
    }
}
