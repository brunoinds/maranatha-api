<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceWithWorkersRequest extends FormRequest
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
            'user_id' => ['required', 'integer'],
            'description' => ['string', 'nullable', 'max:400'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
            'job_code' => ['required', 'string', 'max:100'],
            'expense_code' => ['required', 'string', 'max:100'],
            'workers_dni' => ['required', 'array'],
            'workers_dni.*' => ['required', 'string', 'max:10']
        ];
    }
}
