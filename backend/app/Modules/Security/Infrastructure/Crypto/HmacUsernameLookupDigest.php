<?php

declare(strict_types=1);

namespace App\Modules\Security\Infrastructure\Crypto;

use App\Modules\Security\Application\UsernameLookupDigest;
use App\Modules\Security\Domain\Crypto\LookupDigestKey;
use InvalidArgumentException;

final class HmacUsernameLookupDigest implements UsernameLookupDigest
{
    public function __construct(private readonly LookupDigestKey $key) {}

    public function digest(string $normalizedUsername): string
    {
        if ($normalizedUsername === '') {
            throw new InvalidArgumentException('Normalized usernames cannot be empty.');
        }

        return hash_hmac('sha256', 'my-light-drugstore|username-lookup|v1|'.$normalizedUsername, $this->key->key);
    }
}
