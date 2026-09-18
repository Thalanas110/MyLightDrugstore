<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

use InvalidArgumentException;

final readonly class LookupDigestKey
{
    public function __construct(public string $key)
    {
        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('Username lookup digest keys must contain exactly 32 bytes.');
        }
    }
}
