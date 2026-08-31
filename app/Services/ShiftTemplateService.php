<?php

namespace App\Services;

use App\Helpers\TimeHelper;
use App\Helpers\UserActivityHelper;
use App\Mappers\ShiftTemplates\ShiftTemplateMapper;
use App\Models\ShiftTemplate;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\ShiftTemplateRepositoryInterface;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use App\Services\Shared\StoreContextService;

class ShiftTemplateService
{
    private const DEFAULT_BREAK_MINUTES = 60;

    private const DEFAULT_COLOR = '#2563EB';

    private const DEFAULTS = [
        ['template_name' => 'Opening', 'template_code' => 'OP', 'start_time' => '06:00', 'end_time' => '14:00', 'break_minutes' => 60, 'applies_to' => 'both', 'color_hex' => '#2563EB'],
        ['template_name' => 'Mid', 'template_code' => 'MID', 'start_time' => '10:00', 'end_time' => '18:00', 'break_minutes' => 60, 'applies_to' => 'both', 'color_hex' => '#16A34A'],
        ['template_name' => 'Closing', 'template_code' => 'CL', 'start_time' => '14:00', 'end_time' => '22:00', 'break_minutes' => 60, 'applies_to' => 'both', 'color_hex' => '#9333EA'],
        ['template_name' => 'Split', 'template_code' => 'SP', 'start_time' => '11:00', 'end_time' => '15:00', 'break_minutes' => 0, 'applies_to' => 'crew', 'color_hex' => '#EA580C'],
    ];

    public function __construct(
        private readonly ShiftTemplateRepositoryInterface $repository,
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly StoreContextService $storeContext,
    ) {
    }

    public function list(User $user, array $params): array
    {
        $store = $this->requireStore($user);
        $paginator = $this->repository->getPaginated($store->id, $params);

        return PaginationService::mapPaginator(
            $paginator,
            static fn ($row) => ShiftTemplateMapper::map($row),
        );
    }

    public function create(User $user, array $data): array
    {
        $store = $this->requireStore($user);
        $this->assertNameAvailable($store, $data['template_name']);

        $payload = $this->buildPayload($data);
        $this->assertValidShift($payload);

        $template = $this->repository->create($store->id, $payload);

        UserActivityHelper::log('shift_templates', 'create', "Shift template '{$template->template_name}' created.", $template->id);

        $this->storeRepository->advanceOnboardingStep($store, 7);

        return ShiftTemplateMapper::map($template);
    }

    public function update(User $user, int $templateId, array $data): array
    {
        $store = $this->requireStore($user);
        $template = $this->requireTemplate($store, $templateId);

        if (isset($data['template_name'])) {
            $this->assertNameAvailable($store, $data['template_name'], $templateId);
        }

        $payload = $this->buildPayload($data, $template);
        $this->assertValidShift($payload);

        $this->repository->update($template, $payload);

        UserActivityHelper::log('shift_templates', 'update', "Shift template '{$template->template_name}' updated.", $templateId);

        return ShiftTemplateMapper::map($template->fresh() ?? $template);
    }

    public function deactivate(User $user, int $templateId): void
    {
        $store = $this->requireStore($user);
        $template = $this->requireTemplate($store, $templateId);

        $this->repository->deactivate($template);

        UserActivityHelper::log('shift_templates', 'deactivate', "Shift template '{$template->template_name}' deactivated.", $templateId);
    }

    public function seedDefaults(User $user): int
    {
        $store = $this->requireStore($user);
        $created = $this->repository->seedDefaults($store->id, self::DEFAULTS);
        $this->storeRepository->advanceOnboardingStep($store, 7);

        UserActivityHelper::log('shift_templates', 'seed_defaults', "Seeded {$created} default shift templates.", null);

        return $created;
    }

    private function buildPayload(array $data, ?ShiftTemplate $existing = null): array
    {
        return [
            'template_name' => trim((string) ($data['template_name'] ?? $existing?->template_name)),
            'template_code' => $this->resolveCode($data, $existing),
            'start_time' => $this->resolveTime($data, 'start_time', $existing?->start_time),
            'end_time' => $this->resolveTime($data, 'end_time', $existing?->end_time),
            'break_minutes' => $this->resolveBreakMinutes($data, $existing),
            'applies_to' => $data['applies_to'] ?? $existing?->applies_to ?? 'both',
            'color_hex' => strtoupper((string) ($data['color_hex'] ?? $existing?->color_hex ?? self::DEFAULT_COLOR)),
            'sort_order' => (int) ($data['sort_order'] ?? $existing?->sort_order ?? 0),
            'is_active' => filter_var($data['is_active'] ?? $existing?->is_active ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * A shift with equal start and end reads as a 24-hour shift, and a break that
     * eats the whole shift leaves zero paid hours — both are rejected up front.
     */
    private function assertValidShift(array $payload): void
    {
        if (TimeHelper::toMinutes($payload['start_time']) === TimeHelper::toMinutes($payload['end_time'])) {
            abort(400, 'Start time and end time cannot be the same.');
        }

        $duration = TimeHelper::durationMinutes($payload['start_time'], $payload['end_time']);

        if ($payload['break_minutes'] >= $duration) {
            abort(400, 'Break minutes must be shorter than the shift length.');
        }
    }

    private function resolveCode(array $data, ?ShiftTemplate $existing): ?string
    {
        if (!array_key_exists('template_code', $data)) {
            return $existing?->template_code;
        }

        $code = trim((string) $data['template_code']);

        return $code === '' ? null : strtoupper($code);
    }

    private function resolveTime(array $data, string $key, ?string $fallback): string
    {
        return substr((string) ($data[$key] ?? $fallback ?? ''), 0, 5);
    }

    private function resolveBreakMinutes(array $data, ?ShiftTemplate $existing): int
    {
        if (($data['break_minutes'] ?? null) === null) {
            return $existing !== null ? (int) $existing->break_minutes : self::DEFAULT_BREAK_MINUTES;
        }

        return (int) $data['break_minutes'];
    }

    private function assertNameAvailable(Store $store, string $name, ?int $exceptId = null): void
    {
        if ($this->repository->nameTaken($store->id, trim($name), $exceptId)) {
            abort(400, "A shift template named '{$name}' already exists in your store.");
        }
    }

    private function requireStore(User $user): Store
    {
        return $this->storeContext->requireForUser($user);
    }

    private function requireTemplate(Store $store, int $templateId): ShiftTemplate
    {
        $template = $this->repository->findInStore($store->id, $templateId);

        if ($template === null) {
            abort(404, 'Shift template not found.');
        }

        return $template;
    }
}
