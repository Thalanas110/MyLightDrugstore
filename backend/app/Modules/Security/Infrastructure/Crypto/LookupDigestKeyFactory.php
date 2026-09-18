<?php

declare(strict_types=1);

namespace App\Modules\Security\Infrastructure\Crypto;

use App\Modules\Security\Domain\Crypto\LookupDigestKey;
use InvalidArgumentException;

final class LookupDigestKeyFactory
{
    public function fromBase64(string $encodedKey): LookupDigestKey
    {
        $key = base64_decode($encodedKey, true);

        if ($key === false || base64_encode($key) !== $encodedKey) {
            throw new InvalidArgumentException('Username lookup digest keys must use canonical base64 encoding.');
        }

        return new LookupDigestKey($key);
    }
}
