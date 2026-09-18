<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final class GetAuthenticatedUserAction
{
    public function __construct(private readonly AuthenticationSession $authenticationSession) {}

    public function execute(): ?AuthenticatedUserProfile
    {
        return $this->authenticationSession->currentUser();
    }
}
