<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_register_and_fetch_me(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Mira Pearl',
            'email' => 'mira@qrs.test',
            'password' => 'password12',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.user_type', 'manager')
            ->assertJsonPath('data.user.is_team_leader', false)
            ->assertJsonPath('data.onboarding_step', 1);

        $token = $response->json('data.token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'mira@qrs.test');
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@qrs.test',
            'password' => 'wrong-pass',
        ])->assertStatus(400);
    }

    public function test_user_can_change_password_then_login_with_new_password(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Mira Pearl',
            'email' => 'mira@qrs.test',
            'password' => 'password12',
        ])->json('data.token');

        $this->withToken($token)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'password12',
                'password' => 'newpass99',
                'password_confirmation' => 'newpass99',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/auth/login', [
            'email' => 'mira@qrs.test',
            'password' => 'password12',
        ])->assertStatus(400);

        $this->postJson('/api/auth/login', [
            'email' => 'mira@qrs.test',
            'password' => 'newpass99',
        ])->assertOk();
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Mira Pearl',
            'email' => 'mira@qrs.test',
            'password' => 'password12',
        ])->json('data.token');

        $this->withToken($token)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'wrong-pass',
                'password' => 'newpass99',
                'password_confirmation' => 'newpass99',
            ])
            ->assertStatus(400)
            ->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');
    }

    public function test_change_password_rejects_same_as_current(): void
    {
        $token = $this->postJson('/api/auth/register', [
            'name' => 'Mira Pearl',
            'email' => 'mira@qrs.test',
            'password' => 'password12',
        ])->json('data.token');

        $this->withToken($token)
            ->postJson('/api/auth/change-password', [
                'current_password' => 'password12',
                'password' => 'password12',
                'password_confirmation' => 'password12',
            ])
            ->assertStatus(400);
    }

    public function test_change_password_requires_auth(): void
    {
        $this->postJson('/api/auth/change-password', [
            'current_password' => 'password12',
            'password' => 'newpass99',
            'password_confirmation' => 'newpass99',
        ])->assertUnauthorized();
    }
}
