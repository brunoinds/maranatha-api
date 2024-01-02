<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkersAttendancesRequest extends FormRequest
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
            'workers' => ['required', 'array'],
            'workers.*.dni' => ['required', 'string'],
            'workers.*.days' => ['required', 'array'],
            'workers.*.days.*.id' => ['required', 'integer'],
            'workers.*.days.*.status' => ['required', Rule::in(['Present', 'Absent', 'Justified'])],
        ];
    }
}
