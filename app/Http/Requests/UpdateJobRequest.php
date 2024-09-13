<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobRequest extends FormRequest
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
            'id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', Rule::unique('jobs')->ignore($this->id)],
            'zone' => ['required', 'string', 'max:255'],
            'details' => ['string', 'max:1000'],
            'state' => ['required', 'string', Rule::in(['Active', 'Inactive'])],
            'country' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'array'],
            'location.latitude' => ['required_with:location', 'numeric'],
            'location.longitude' => ['required_with:location', 'numeric'],
        ];
    }
}
