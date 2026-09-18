<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

use InvalidArgumentException;

final readonly class DataEncryptionKey
{
    public function __construct(
        public string $keyId,
        public string $key,
    ) {
        if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/', $keyId)) {
            throw new InvalidArgumentException('Data encryption key IDs must be safe identifiers.');
        }

        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('Data encryption keys must contain exactly 32 bytes.');
        }
    }
}
