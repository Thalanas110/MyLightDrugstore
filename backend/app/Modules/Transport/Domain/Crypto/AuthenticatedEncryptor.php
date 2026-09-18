<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

interface AuthenticatedEncryptor
{
    public function encrypt(string $key, string $plaintext, string $additionalData): AesGcmCiphertext;

    public function decrypt(string $key, AesGcmCiphertext $encrypted, string $additionalData): string;
}
