<?php

declare(strict_types=1);

namespace App\Modules\Security\Application;

interface UsernameLookupDigest
{
    public function digest(string $normalizedUsername): string;
}
