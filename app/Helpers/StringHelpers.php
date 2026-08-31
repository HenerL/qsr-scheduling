<?php

namespace App\Helpers;

class StringHelpers
{
    public static function toSlug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    public static function initials(string $firstName, string $lastName): string
    {
        return mb_strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));
    }

    public static function fullName(string $firstName, string $lastName, ?string $middleName = null): string
    {
        $middleInitial = $middleName === null || trim($middleName) === ''
            ? ''
            : mb_strtoupper(mb_substr(trim($middleName), 0, 1)) . '.';

        return trim(implode(' ', array_filter([trim($firstName), $middleInitial, trim($lastName)])));
    }

    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
