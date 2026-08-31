<?php

namespace App\Http\Requests;

class PaginationRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'search' => $this->nullableString(100),
            'sort_by' => $this->nullableString(50),
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ];
    }
}
