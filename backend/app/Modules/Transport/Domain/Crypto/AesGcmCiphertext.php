<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

final readonly class AesGcmCiphertext
{
    public function __construct(
        public string $nonce,
        public string $ciphertext,
        public string $tag,
    ) {}
}
