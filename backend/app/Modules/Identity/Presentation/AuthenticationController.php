<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation;

use App\Modules\Identity\Application\AuthenticateUserAction;
use App\Modules\Identity\Application\ChangeCurrentPasswordAction;
use App\Modules\Identity\Application\GetAuthenticatedUserAction;
use App\Modules\Identity\Application\LogoutUserAction;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationController
{
    public function csrf(Request $request): JsonResponse
    {
        return response()->json(['data' => ['csrfToken' => $request->session()->token()]]);
    }

    public function login(LoginRequest $request, AuthenticateUserAction $authenticateUser): JsonResponse
    {
        $profile = $authenticateUser->execute($request->username(), $request->password());

        if ($profile === null) {
            throw new AuthenticationException;
        }

        $request->session()->regenerate();

        return response()->json(['data' => $profile]);
    }

    public function me(GetAuthenticatedUserAction $getAuthenticatedUser): JsonResponse
    {
        $profile = $getAuthenticatedUser->execute();

        if ($profile === null) {
            throw new AuthenticationException;
        }

        return response()->json(['data' => $profile]);
    }

    public function changePassword(ChangePasswordRequest $request, ChangeCurrentPasswordAction $changePassword): Response
    {
        if (! $changePassword->execute($request->currentPassword(), $request->newPassword())) {
            throw ValidationException::withMessages([
                'currentPassword' => ['The current password is incorrect.'],
            ]);
        }

        return response()->noContent();
    }

    public function logout(Request $request, LogoutUserAction $logoutUser): Response
    {
        $logoutUser->execute();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
