<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

use InvalidArgumentException;

final readonly class AuthenticatedData
{
    public function __construct(
        public string $nonce,
        public string $ciphertext,
        public string $tag,
    ) {
        if (strlen($nonce) !== 12) {
            throw new InvalidArgumentException('AES-GCM nonces must contain exactly 12 bytes.');
        }

        if (strlen($tag) !== 16) {
            throw new InvalidArgumentException('AES-GCM tags must contain exactly 16 bytes.');
        }
    }
}
