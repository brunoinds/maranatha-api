<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\Enums\MoneyType;

class UpdateWorkerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'dni' => ['required', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
            'supervisor' => ['required', 'string', 'max:100'],
            'team' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:2'],
            'role' => ['required', 'string', 'max:100']
        ];
    }
}
