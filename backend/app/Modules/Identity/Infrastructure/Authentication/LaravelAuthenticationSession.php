<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Authentication;

use App\Modules\Identity\Application\AuthenticatedUserProfile;
use App\Modules\Identity\Application\AuthenticationSession;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Support\Facades\Auth;

final class LaravelAuthenticationSession implements AuthenticationSession
{
    public function authenticate(string $username, string $password): ?AuthenticatedUserProfile
    {
        if (! Auth::guard('web')->attempt(['username' => $username, 'password' => $password])) {
            return null;
        }

        return $this->profile(Auth::guard('web')->user());
    }

    public function currentUser(): ?AuthenticatedUserProfile
    {
        return $this->profile(Auth::guard('web')->user());
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
    }

    private function profile(mixed $user): ?AuthenticatedUserProfile
    {
        if (! $user instanceof User || ! is_int($user->getKey())) {
            return null;
        }

        return new AuthenticatedUserProfile(
            $user->getKey(),
            $user->username()->value,
            $user->fullName()->value,
            $user->staffRole()->value,
            $user->isActive(),
        );
    }
}
