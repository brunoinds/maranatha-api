<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|max:255',
            'email' => 'required|unique:customers|max:255',
            'phone' => 'required|unique:customers|max:255',
            'address' => 'required|max:255',
            'address_details' => 'required|max:255',
            'allow_notifications' => 'required|boolean'
        ];
    }
}
