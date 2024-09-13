<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\Enums\ProjectConstructionPhaseStatus;

class UpdateProjectConstructionPhaseRequest extends FormRequest
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
            'expense_code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
            'status' => ['string', Rule::in(ProjectConstructionPhaseStatus::toArray())],
            'scheduled_start_date' => ['date'],
            'scheduled_end_date' => ['date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'progress' => ['integer'],
            'final_report' => ['nullable', 'array'],
            'final_report.attachments_ids' => ['present', 'array'],
            'final_report.attachments_ids.*' => ['string'],
            'final_report.notes' => ['string'],
        ];
    }
}
