<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final class LogoutUserAction
{
    public function __construct(private readonly AuthenticationSession $authenticationSession) {}

    public function execute(): void
    {
        $this->authenticationSession->logout();
    }
}
