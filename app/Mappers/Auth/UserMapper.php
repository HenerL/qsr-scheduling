<?php

namespace App\Mappers\Auth;

use App\Models\User;

class UserMapper
{
    public static function map(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'user_type' => $user->user_type,
            'store_id' => $user->store_id,
            'is_active' => $user->is_active,
            'is_team_leader' => $user->isCrewTeamLeader(),
        ];
    }
}
