<?php

namespace App\Repositories\Interfaces;

use App\Models\User;

interface AuthRepositoryInterface
{
    public function createManager(array $data): User;

    public function createCrew(array $data): User;

    public function findByEmail(string $email): ?User;

    public function updatePassword(User $user, string $password): void;

    public function deactivate(User $user): void;
}
