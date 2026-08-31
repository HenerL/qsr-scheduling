<?php

namespace App\Services;

use App\Helpers\UserActivityHelper;
use App\Mappers\Auth\UserMapper;
use App\Mappers\Store\StoreMapper;
use App\Models\Store;
use App\Models\User;
use App\Repositories\Interfaces\AuthRepositoryInterface;

class AuthService
{
    public function __construct(private readonly AuthRepositoryInterface $authRepository)
    {
    }

    public function register(array $data): array
    {
        $user = $this->authRepository->createManager($data);
        $token = $user->createToken('manager-session')->plainTextToken;

        UserActivityHelper::log('auth', 'register', 'Manager registered.', $user->id);

        return $this->sessionPayload($user, $token);
    }

    public function login(string $email, string $password): ?array
    {
        $user = $this->authRepository->findByEmail($email);

        if ($user === null || !password_verify($password, $user->password) || !$user->is_active) {
            return null;
        }

        $user->tokens()->delete();
        $token = $user->createToken($user->user_type . '-session')->plainTextToken;

        UserActivityHelper::log('auth', 'login', 'User logged in.', $user->id);

        return $this->sessionPayload($user->load(['store', 'employee']), $token);
    }

    public function me(User $user): array
    {
        return $this->sessionPayload($user->load(['store', 'employee']));
    }

    public function logout(User $user): void
    {
        UserActivityHelper::log('auth', 'logout', 'User logged out.', $user->id);
        $user->currentAccessToken()?->delete();
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!password_verify($currentPassword, $user->password)) {
            return false;
        }

        $this->authRepository->updatePassword($user, $newPassword);

        UserActivityHelper::log('auth', 'change_password', 'User changed password.', $user->id);

        return true;
    }

    /**
     * @return array{user: array, store: array|null, onboarding_step: int, token?: string}
     */
    private function sessionPayload(User $user, ?string $token = null): array
    {
        $payload = [
            'user' => UserMapper::map($user),
            'store' => $user->store !== null ? StoreMapper::map($user->store) : null,
            'onboarding_step' => $this->resolveOnboardingStep($user->store),
        ];

        if ($token !== null) {
            $payload['token'] = $token;
        }

        return $payload;
    }

    private function resolveOnboardingStep(?Store $store): int
    {
        if ($store === null) {
            return 1;
        }

        return $store->onboarding_completed_at !== null
            ? Store::ONBOARDING_FINAL_STEP
            : max($store->onboarding_step, 1);
    }
}
