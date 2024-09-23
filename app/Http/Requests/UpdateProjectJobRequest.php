<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Helpers\Enums\ProjectJobEventType;
use App\Helpers\Enums\ProjectJobStatus;

class UpdateProjectJobRequest extends FormRequest
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
            'job_code' => ['required', 'string', 'max:255'],
            'project_structure_id' => ['required', 'integer', 'exists:project_structures,id'],
            'width' => ['required', 'numeric'],
            'length' => ['required', 'numeric'],
            'area' => ['required', 'numeric'],
            'admins_ids' => ['array'],
            'admins_ids.*' => ['integer', 'exists:users,id'],
            'supervisor_id' => ['required', 'integer', 'exists:users,id'],
            'event_type' => ['string', Rule::in(ProjectJobEventType::toArray())],
            'scheduled_start_date' => ['date'],
            'scheduled_end_date' => ['date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'status' => ['string', Rule::in(ProjectJobStatus::toArray())],
            'final_report' => ['nullable', 'array'],
            'marketing_report' => ['nullable', 'array']
        ];
    }
}
