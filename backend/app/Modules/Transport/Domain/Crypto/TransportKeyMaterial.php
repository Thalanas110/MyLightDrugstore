<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

use InvalidArgumentException;

final readonly class TransportKeyMaterial
{
    public function __construct(
        public string $keyId,
        public string $publicKeyPem,
        public string $privateKeyPem,
    ) {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $keyId) !== 1
            || $publicKeyPem === '' || $privateKeyPem === '') {
            throw new InvalidArgumentException('Transport key material is invalid.');
        }
    }
}
