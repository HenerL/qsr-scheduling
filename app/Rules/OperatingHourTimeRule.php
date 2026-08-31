<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class OperatingHourTimeRule implements Rule
{
    private ?string $failureMessage = null;

    public function passes($attribute, $value): bool
    {
        $isEmpty = $value === null || $value === '';

        if ($isEmpty && !$this->dayRequiresTimes($attribute)) {
            return true;
        }

        if ($isEmpty) {
            $field = str_contains($attribute, 'open_time') ? 'Open' : 'Close';

            $this->failureMessage = "{$field} time is required for an open day.";

            return false;
        }

        if (!is_string($value) || preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) !== 1) {
            $this->failureMessage = 'Time must be in HH:MM 24-hour format.';

            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->failureMessage ?? 'Invalid operating hour time.';
    }

    private function dayRequiresTimes(string $attribute): bool
    {
        $index = (int) (explode('.', $attribute)[1] ?? '-1');
        $day = request()->input("days.{$index}");

        return is_array($day)
            && filter_var($day['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && !filter_var($day['is_24_hours'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}
