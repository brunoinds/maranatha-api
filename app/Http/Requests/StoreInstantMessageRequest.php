<?php

namespace App\Http\Requests;

use App\Helpers\Enums\InstantMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstantMessageRequest extends FormRequest
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
            'body' => 'required|string',
            'from_user_id' => 'required|integer|exists:users,id',
            'to_user_id' => 'required|integer|exists:users,id',
            'replies_to' => 'nullable|integer|exists:instant_messages,id',
            'sent_at' => 'required|date',
            'received_at' => 'nullable|date',
            'read_at' => 'nullable|date',
            'played_at' => 'nullable|date',
            'type' => [
                'required',
                Rule::in(InstantMessageType::toArray())
            ],
            'attachment' => 'nullable|array',
            'metadata' => 'nullable|array',
        ];
    }
}
