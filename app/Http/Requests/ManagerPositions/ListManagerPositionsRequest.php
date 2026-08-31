<?php

namespace App\Http\Requests\ManagerPositions;

use App\Http\Requests\PaginationRequest;
use App\Http\Requests\FormRequestCommonRules;

class ListManagerPositionsRequest extends PaginationRequest
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
