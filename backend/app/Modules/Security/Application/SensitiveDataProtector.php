<?php

declare(strict_types=1);

namespace App\Modules\Security\Application;

interface SensitiveDataProtector
{
    public function encrypt(string $plaintext, string $context): string;

    public function decrypt(string $encoded, string $context): string;
}
