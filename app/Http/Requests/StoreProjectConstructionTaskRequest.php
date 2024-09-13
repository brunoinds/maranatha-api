<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectConstructionTaskRequest extends FormRequest
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
            'project_job_id' => ['required', 'integer', 'exists:project_jobs,id'],
            'project_construction_phase_id' => ['required', 'integer', 'exists:project_construction_phases,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'status' => ['string', 'max:255'],
            'scheduled_start_date' => ['date'],
            'scheduled_end_date' => ['date'],
            'started_at' => ['nullable', 'date'],
            'ended_at' => ['nullable', 'date'],
            'count_workers' => ['integer'],
            'progress' => ['integer'],
            'daily_reports' => ['array'],
            'daily_reports.*.date' => ['required', 'date'],
            'daily_reports.*.progress' => ['required', 'integer'],
            'daily_reports.*.count_workers' => ['required', 'integer'],
            'daily_reports.*.notes' => ['string'],
            'daily_reports.*.attachments_ids' => ['array'],
        ];
    }
}
