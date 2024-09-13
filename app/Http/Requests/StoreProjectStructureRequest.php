<?php

namespace App\Http\Requests;

use App\Helpers\Enums\ProjectStructureBuildingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectStructureRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'structure_type' => ['required', 'string', 'max:255'],
            'building_type' => ['required', 'string', Rule::in(ProjectStructureBuildingType::toArray())],
            'axes_count' => ['nullable', 'integer'],
            'beams_count' => ['nullable', 'integer'],
            'columns_count' => ['nullable', 'integer'],
            'stringers_count' => ['nullable', 'integer'],
            'facades_count' => ['nullable', 'integer'],
            'default_phases' => ['present', 'array'],
            'default_phases.construction' => ['present', 'array'],
            'default_phases.construction.*.name' => ['required', 'string', 'max:255'],
            'default_phases.construction.*.description' => ['nullable', 'string', 'max:255'],
            'default_phases.construction.*.expense_code' => ['required', 'string', 'max:255'],
            'default_phases.construction.*.color' => ['required', 'string', 'max:255'],
            'default_phases.construction.*.tasks' => ['required', 'array'],
            'default_phases.construction.*.tasks.*.name' => ['required', 'string', 'max:255'],
            'default_phases.construction.*.tasks.*.description' => ['nullable', 'string', 'max:255'],
            'default_phases.construction.*.tasks.*.average_days' => ['required', 'integer'],
            'default_phases.studio' => ['present', 'array'],
        ];
    }
}
