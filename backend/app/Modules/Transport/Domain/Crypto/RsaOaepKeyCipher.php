<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

interface RsaOaepKeyCipher
{
    public function wrap(string $publicKeyPem, string $aesKey): string;

    public function unwrap(string $privateKeyPem, string $wrappedKey): string;
}
