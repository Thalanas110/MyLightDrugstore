<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final class AuthenticateUserAction
{
    public function __construct(private readonly AuthenticationSession $authenticationSession) {}

    public function execute(string $username, string $password): ?AuthenticatedUserProfile
    {
        return $this->authenticationSession->authenticate($username, $password);
    }
}
