<?php

namespace Tests\Concerns;

use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\StoreRepositoryInterface;

trait CreatesAuthenticatedStore
{
    /**
     * @return array{0: User, 1: Store, 2: string}
     */
    protected function makeManagerWithStore(string $email, string $storeName): array
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();

        $register = $this->postJson('/api/auth/register', [
            'name' => $storeName . ' Owner',
            'email' => $email,
            'password' => 'password12',
        ]);

        $register->assertCreated();
        $token = $register->json('data.token');

        $this->withToken($token)
            ->postJson('/api/store', [
                'store_name' => $storeName,
                'timezone' => 'Asia/Manila',
                'week_starts_on' => 1,
                'max_consecutive_duty_days' => 6,
            ])
            ->assertCreated();

        $user = User::where('email', $email)->firstOrFail();
        $store = app(StoreRepositoryInterface::class)->findByOwner($user);

        return [$user->refresh(), $store, $token];
    }
}
