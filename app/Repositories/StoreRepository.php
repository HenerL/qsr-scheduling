<?php

namespace App\Repositories;

use App\Helpers\StringHelpers;
use App\Models\Store;
use App\Models\StoreOperatingHour;
use App\Models\User;
use App\Repositories\Interfaces\StoreRepositoryInterface;
use Illuminate\Support\Facades\DB;

class StoreRepository implements StoreRepositoryInterface
{
    public function createForOwner(User $owner, array $data): Store
    {
        return DB::transaction(function () use ($owner, $data): Store {
            $store = Store::create([
                'owner_user_id' => $owner->id,
                'store_name' => $data['store_name'],
                'branch_name' => $data['branch_name'] ?? null,
                'store_code' => $this->generateStoreCode($data['store_name'], $owner->id),
                'address' => $data['address'] ?? null,
                'contact_number' => $data['contact_number'] ?? null,
                'timezone' => $data['timezone'] ?? 'Asia/Manila',
                'week_starts_on' => $data['week_starts_on'] ?? 0,
                'max_consecutive_duty_days' => $data['max_consecutive_duty_days'] ?? 6,
                'onboarding_step' => 2,
            ]);

            $this->seedHours($store);

            $owner->forceFill(['store_id' => $store->id])->save();

            return $store->fresh() ?? $store;
        });
    }

    public function findByOwner(User $owner): ?Store
    {
        return $owner->store_id !== null
            ? Store::find($owner->store_id)
            : Store::where('owner_user_id', $owner->id)->first();
    }

    public function update(Store $store, array $data): void
    {
        $store->fill($data)->save();
    }

    public function advanceOnboardingStep(Store $store, int $minStep): void
    {
        $step = max($store->onboarding_step, $minStep);
        $data = [];

        if ($step > $store->onboarding_step) {
            $data['onboarding_step'] = $step;
        }

        if ($step >= Store::ONBOARDING_FINAL_STEP && $store->onboarding_completed_at === null) {
            $data['onboarding_completed_at'] = now();
        }

        if ($data === []) {
            return;
        }

        $this->update($store, $data);
    }

    public function getHours(Store $store): array
    {
        return $store->operatingHours()
            ->orderBy('day_of_week')
            ->get()
            ->all();
    }

    public function replaceHours(Store $store, array $days): void
    {
        DB::transaction(function () use ($store, $days): void {
            foreach ($days as $day) {
                StoreOperatingHour::updateOrCreate(
                    ['store_id' => $store->id, 'day_of_week' => $day['day_of_week']],
                    [
                        'is_open' => $day['is_open'],
                        'is_24_hours' => $day['is_24_hours'],
                        'open_time' => $day['open_time'] ?? null,
                        'close_time' => $day['close_time'] ?? null,
                    ],
                );
            }
        });
    }

    private function seedHours(Store $store): void
    {
        $rows = [];
        for ($day = 0; $day <= 6; $day++) {
            $rows[] = [
                'store_id' => $store->id,
                'day_of_week' => $day,
                'is_open' => !in_array($day, [0], true),
                'is_24_hours' => false,
                'open_time' => $day === 0 ? null : '08:00:00',
                'close_time' => $day === 0 ? null : '22:00:00',
            ];
        }
        StoreOperatingHour::insert($rows);
    }

    private function generateStoreCode(string $storeName, int $ownerId): string
    {
        $base = StringHelpers::toSlug($storeName) ?: 'store';

        if (!Store::where('store_code', $base)->exists()) {
            return $base;
        }

        $code = "{$base}-{$ownerId}";
        $suffix = 2;

        while (Store::where('store_code', $code)->exists()) {
            $code = "{$base}-{$ownerId}-{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
