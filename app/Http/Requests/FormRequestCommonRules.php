<?php

namespace App\Http\Requests;

trait FormRequestCommonRules
{
    protected function requiredString(int $max = 255): string
    {
        return 'required|string|max:' . $max;
    }

    protected function nullableString(int $max = 255): string
    {
        return 'nullable|string|max:' . $max;
    }

    protected function requiredEmail(): string
    {
        return 'required|string|email:filter|max:255';
    }

    protected function timeRule(bool $required = true): string
    {
        return ($required ? 'required' : 'nullable') . '|date_format:H:i';
    }

    protected function dateRule(bool $required = true): string
    {
        return ($required ? 'required' : 'nullable') . '|date_format:Y-m-d';
    }

    protected function booleanRule(bool $required = false): string
    {
        return ($required ? 'required' : 'nullable') . '|boolean';
    }

    protected function foreignId(string $table, bool $required = true, string $column = 'id'): array
    {
        return [$required ? 'required' : 'nullable', 'integer', 'exists:' . $table . ',' . $column];
    }
}
