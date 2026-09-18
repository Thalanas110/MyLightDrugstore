<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

use InvalidArgumentException;

final readonly class EncryptionContext
{
    public string $additionalData;

    public function __construct(string $value)
    {
        if (! preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._:-]{0,127}\z/', $value)) {
            throw new InvalidArgumentException('Sensitive data encryption context is invalid.');
        }

        $this->additionalData = 'my-light-drugstore|at-rest|v1|'.$value;
    }
}
