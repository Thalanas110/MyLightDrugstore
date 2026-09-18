<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

final readonly class AuthenticatedUserProfile
{
    public function __construct(
        public int $id,
        public string $username,
        public string $fullName,
        public string $role,
        public bool $active,
    ) {}
}
