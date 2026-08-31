<?php

namespace App\Http\Requests\CrewStations;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\FormRequestCommonRules;

class SaveCrewStationRequest extends BaseFormRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        return [
            'station_name' => $this->requiredString(100),
            'station_description' => $this->nullableString(1000),
            'min_crew_required' => ['nullable', 'integer', 'min:0', 'max:99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => $this->booleanRule(),
        ];
    }

    public function messages(): array
    {
        return [
            'station_name.required' => 'Station name is required.',
            'min_crew_required.integer' => 'Minimum crew must be a whole number.',
            'min_crew_required.max' => 'Minimum crew cannot exceed 99.',
        ];
    }
}
