<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

interface AuthenticatedDataCipher
{
    public function encrypt(string $key, string $plaintext, string $additionalData): AuthenticatedData;

    public function decrypt(string $key, AuthenticatedData $encrypted, string $additionalData): string;
}
