<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Users;

use InvalidArgumentException;

final readonly class Username
{
    public string $value;

    public function __construct(string $value)
    {
        $normalizedValue = strtolower(trim($value));

        if (! preg_match('/\A[a-z0-9][a-z0-9._-]{2,63}\z/', $normalizedValue)) {
            throw new InvalidArgumentException('Username must contain 3 to 64 ASCII letters, digits, dots, underscores, or hyphens.');
        }

        $this->value = $normalizedValue;
    }
}
