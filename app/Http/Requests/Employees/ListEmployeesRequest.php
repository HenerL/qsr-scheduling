<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\PaginationRequest;

class ListEmployeesRequest extends PaginationRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'employee_type' => ['nullable', 'in:manager,crew'],
            'employment_status' => ['nullable', 'in:full_time,part_time,trainee'],
            'station_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'in:0,1'],
        ];
    }
}
