<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

final readonly class TransportEnvelope
{
    public function __construct(
        public int $version,
        public string $algorithm,
        public string $keyId,
        public AesGcmCiphertext $encrypted,
    ) {}
}
