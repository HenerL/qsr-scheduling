<?php

namespace App\Http\Requests\ShiftTemplates;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\FormRequestCommonRules;

class SaveShiftTemplateRequest extends BaseFormRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        return [
            'template_name' => $this->requiredString(100),
            'template_code' => $this->nullableString(10),
            'start_time' => $this->timeRule(),
            'end_time' => $this->timeRule(),
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'applies_to' => ['required', 'in:manager,crew,both'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => $this->booleanRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'template_name.required' => 'Template name is required.',
            'start_time.required' => 'Start time is required.',
            'start_time.date_format' => 'Start time must use the 24-hour HH:MM format.',
            'end_time.required' => 'End time is required.',
            'end_time.date_format' => 'End time must use the 24-hour HH:MM format.',
            'break_minutes.max' => 'Break minutes cannot exceed 480.',
            'applies_to.required' => 'Select who this template applies to.',
            'applies_to.in' => 'Applies to must be manager, crew or both.',
            'color_hex.regex' => 'Color must be a hex value such as #2563EB.',
        ];
    }
}
