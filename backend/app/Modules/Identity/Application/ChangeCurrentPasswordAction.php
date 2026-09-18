<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final class ChangeCurrentPasswordAction
{
    public function __construct(private readonly AuthenticationSession $authenticationSession) {}

    public function execute(string $currentPassword, string $newPassword): bool
    {
        return $this->authenticationSession->changeCurrentPassword($currentPassword, $newPassword);
    }
}
