<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

interface AuthenticationSession
{
    public function authenticate(string $username, string $password): ?AuthenticatedUserProfile;

    public function currentUser(): ?AuthenticatedUserProfile;

    public function logout(): void;
}
