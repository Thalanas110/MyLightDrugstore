<?php

declare(strict_types=1);

namespace App\Modules\Transport\Application;

final readonly class PublicTransportKey
{
    public function __construct(
        public string $keyId,
        public string $publicKeyPem,
    ) {}
}
