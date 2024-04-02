<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
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
            'description' => ['string', 'nullable', 'max:400'],
            'job_code' => ['required', 'string', 'max:100'],
            'from_date' => ['date'],
            'to_date' => ['date'],
            'expense_code' => ['required', 'string', 'max:100'],
        ];
    }
}
