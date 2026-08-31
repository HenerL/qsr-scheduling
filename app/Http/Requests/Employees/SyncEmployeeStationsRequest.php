<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\BaseFormRequest;

class SyncEmployeeStationsRequest extends BaseFormRequest
{
    use EmployeeStationRules;

    public function rules(): array
    {
        return $this->stationRules();
    }

    public function messages(): array
    {
        return [
            ...$this->commonMessages(),
            ...$this->stationMessages(),
        ];
    }
}
