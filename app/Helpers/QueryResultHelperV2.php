<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class QueryResultHelperV2
{
    public static function onSuccessGet(mixed $data, string $message = 'Fetched successfully.', ?array $meta = null, ?array $warnings = null): JsonResponse
    {
        return response()->json(self::envelope(true, $message, $data, $meta, null, $warnings));
    }

    public static function onSuccessCreate(mixed $data, string $message = 'Created successfully.', ?array $warnings = null): JsonResponse
    {
        return response()->json(self::envelope(true, $message, $data, null, null, $warnings), 201);
    }

    public static function onSuccessUpdate(mixed $data, string $message = 'Updated successfully.', ?array $warnings = null): JsonResponse
    {
        return response()->json(self::envelope(true, $message, $data, null, null, $warnings));
    }

    public static function onSuccessDelete(string $message = 'Deleted successfully.'): JsonResponse
    {
        return response()->json(self::envelope(true, $message, true));
    }

    public static function onBadRequest(array $errors, string $message = 'Validation failed.'): JsonResponse
    {
        return response()->json(self::envelope(false, $message, null, null, $errors), 400);
    }

    public static function onNotFound(string $message = 'Record not found.'): JsonResponse
    {
        return response()->json(self::envelope(false, $message), 404);
    }

    public static function onUnauthorized(string $message = 'Unauthorized.'): JsonResponse
    {
        return response()->json(self::envelope(false, $message), 401);
    }

    /**
     * Warnings are advisory rule breaches that did not block the write, so an empty set
     * is omitted rather than sent as an empty object.
     */
    private static function envelope(bool $success, string $message, mixed $data = null, ?array $meta = null, ?array $errors = null, ?array $warnings = null): array
    {
        return array_filter([
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => $errors,
            'warnings' => $warnings === [] ? null : $warnings,
        ], static fn ($value) => $value !== null);
    }
}
