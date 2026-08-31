<?php

namespace App\Services;

use App\Http\Requests\PaginationRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaginationService
{
    /**
     * @param array $filterKeys extra validated query keys a list endpoint filters on,
     *                          merged in so repositories receive them alongside paging.
     */
    public static function params(PaginationRequest $request, array $filterKeys = []): array
    {
        $validated = $request->validated();

        $filters = [];
        foreach ($filterKeys as $key) {
            $value = $validated[$key] ?? null;

            if ($value !== null && $value !== '') {
                $filters[$key] = $value;
            }
        }

        return [
            'page' => max(1, (int) ($validated['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($validated['per_page'] ?? 15))),
            'search' => $validated['search'] ?? null,
            'sort_by' => $validated['sort_by'] ?? null,
            'sort_dir' => strtolower($validated['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
            ...$filters,
        ];
    }

    public static function mapPaginator(LengthAwarePaginator $paginator, ?callable $mapper = null): array
    {
        $rows = collect($paginator->items())
            ->map($mapper ?? static fn ($row) => $row)
            ->values();

        return [
            'rows' => $rows->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }
}
