<?php

namespace App\Repositories\Interfaces;

use App\Models\ShiftTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ShiftTemplateRepositoryInterface
{
    public function getPaginated(int $storeId, array $params): LengthAwarePaginator;

    /** Board chip palette: active templates in display order. */
    public function getActiveForStore(int $storeId): Collection;

    public function findInStore(int $storeId, int $templateId): ?ShiftTemplate;

    public function nameTaken(int $storeId, string $name, ?int $exceptId = null): bool;

    public function create(int $storeId, array $data): ShiftTemplate;

    public function update(ShiftTemplate $template, array $data): void;

    public function deactivate(ShiftTemplate $template): void;

    public function seedDefaults(int $storeId, array $defaults): int;
}
