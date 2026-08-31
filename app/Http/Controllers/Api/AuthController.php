<?php

namespace App\Http\Controllers\Api;

use App\Helpers\QueryResultHelperV2;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessCreate(
            $this->authService->register($request->validated()),
            'Account created. Welcome to QRS Scheduling.'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString()
        );

        if ($result === null) {
            return QueryResultHelperV2::onBadRequest([
                'email' => ['These credentials do not match our records or the account is inactive.'],
            ], 'Login failed.');
        }

        return QueryResultHelperV2::onSuccessGet($result, 'Logged in successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return QueryResultHelperV2::onSuccessGet($this->authService->me($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return QueryResultHelperV2::onSuccessDelete('Logged out successfully.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $changed = $this->authService->changePassword(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString()
        );

        if (!$changed) {
            return QueryResultHelperV2::onBadRequest([
                'current_password' => ['The current password is incorrect.'],
            ], 'Password change failed.');
        }

        return QueryResultHelperV2::onSuccessUpdate(true, 'Password changed successfully.');
    }
}
