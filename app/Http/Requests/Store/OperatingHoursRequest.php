<?php

namespace App\Http\Requests\Store;

use App\Http\Requests\BaseFormRequest;
use App\Http\Requests\FormRequestCommonRules;
use App\Rules\OperatingHourTimeRule;

class OperatingHoursRequest extends BaseFormRequest
{
    use FormRequestCommonRules;

    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'size:7'],
            'days.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.is_open' => $this->booleanRule(true),
            'days.*.is_24_hours' => $this->booleanRule(),
            'days.*.open_time' => [new OperatingHourTimeRule()],
            'days.*.close_time' => [new OperatingHourTimeRule()],
        ];
    }

    public function messages(): array
    {
        return [
            'days.required' => 'Operating hours are required.',
            'days.size' => 'Exactly 7 days must be provided.',
            'days.*.day_of_week.between' => 'Day of week must be between 0 (Sunday) and 6 (Saturday).',
            'days.*.day_of_week.distinct' => 'Each day of the week may appear only once.',
        ];
    }

    private function dayRequiresTimes(string $attribute): bool
    {
        $index = (int) (explode('.', $attribute)[1] ?? '-1');
        $day = $this->input("days.{$index}");

        return is_array($day)
            && ($day['is_open'] ?? false) === true
            && ($day['is_24_hours'] ?? false) === false;
    }

    protected function prepareForValidation(): void
    {
        $days = $this->input('days');

        if (!is_array($days)) {
            return;
        }

        $normalized = array_map(static function (array $day): array {
            $isOpen = filter_var($day['is_open'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $is24Hours = filter_var($day['is_24_hours'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $requiresTimes = $isOpen && !$is24Hours;

            return [
                'day_of_week' => $day['day_of_week'] ?? null,
                'is_open' => $isOpen,
                'is_24_hours' => $is24Hours,
                'open_time' => $requiresTimes ? ($day['open_time'] ?? null) : null,
                'close_time' => $requiresTimes ? ($day['close_time'] ?? null) : null,
            ];
        }, $days);

        $this->merge(['days' => array_values($normalized)]);
    }
}
