<?php

namespace App\Http\Requests\ShiftTemplates;

use App\Http\Requests\FormRequestCommonRules;
use App\Http\Requests\PaginationRequest;

class ListShiftTemplatesRequest extends PaginationRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_active' => ['nullable', 'in:0,1'],
            'applies_to' => ['nullable', 'in:manager,crew,both'],
        ];
    }
}
