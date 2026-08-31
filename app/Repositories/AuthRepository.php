<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    public function createManager(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'user_type' => 'manager',
            'is_active' => true,
        ]);
    }

    public function createCrew(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'user_type' => 'crew',
            'store_id' => $data['store_id'],
            'is_active' => true,
        ]);
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();
    }

    public function deactivate(User $user): void
    {
        $user->forceFill(['is_active' => false])->save();
        $user->tokens()->delete();
    }
}
