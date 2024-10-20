<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['string', 'max:255'],
            'username' => ['string', 'max:255', Rule::unique('users')],
            'email' => ['email', 'max:255', Rule::unique('users')],
            'password' => [Password::defaults()],
            'roles' => ['array'],
            'permissions' => ['array'],
            'metadata' => ['array'],
        ];
    }
}
