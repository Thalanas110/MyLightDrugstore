<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

use InvalidArgumentException;

final readonly class EncryptedSensitiveValue
{
    public function __construct(
        public string $keyId,
        public AuthenticatedData $authenticatedData,
    ) {
        if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}\z/', $keyId)) {
            throw new InvalidArgumentException('Data encryption key IDs must be safe identifiers.');
        }
    }
}
