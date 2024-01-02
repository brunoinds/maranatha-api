<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceDayWorkerRequest extends FormRequest
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
            'worker_dni' => ['required', 'string', 'max:10'],
            'attendance_id' => ['required', 'integer'],
            'date' => ['required', 'date'],            
            'status' => ['required', Rule::in(['Present', 'Abscent', 'Justified'])], 
            'observation' => ['string', 'nullable', 'max:400'],
        ];
    }
}
