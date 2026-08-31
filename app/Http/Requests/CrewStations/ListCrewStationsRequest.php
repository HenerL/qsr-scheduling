<?php

namespace App\Http\Requests\CrewStations;

use App\Http\Requests\PaginationRequest;
use App\Http\Requests\FormRequestCommonRules;

class ListCrewStationsRequest extends PaginationRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_active' => ['nullable', 'in:0,1'],
        ];
    }
}
