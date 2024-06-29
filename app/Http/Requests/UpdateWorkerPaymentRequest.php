<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\Enums\MoneyType;

class UpdateWorkerPaymentRequest extends FormRequest
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
            'worker_id' => 'required|exists:workers,id',
            'amount' => 'required|numeric',
            'month' => 'required|date_format:m',
            'year' => 'required|date_format:Y',
            'currency' => ['required', Rule::in(MoneyType::toArray())],
            'description' => 'string|nullable',
            'divisions' => 'array',
            'divisions.*.id' => 'required|string',
            'divisions.*.name' => 'required|string',
            'divisions.*.amount' => 'required|numeric',
        ];
    }
}
