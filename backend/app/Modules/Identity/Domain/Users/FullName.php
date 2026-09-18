<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\Users;

use InvalidArgumentException;

final readonly class FullName
{
    public string $value;

    public function __construct(string $value)
    {
        $normalizedValue = preg_replace('/\s+/u', ' ', trim($value));

        if (
            ! is_string($normalizedValue)
            || $normalizedValue === ''
            || mb_strlen($normalizedValue, 'UTF-8') > 160
            || preg_match('/\p{Cc}/u', $normalizedValue) === 1
        ) {
            throw new InvalidArgumentException('Full name must contain 1 to 160 characters without control characters.');
        }

        $this->value = $normalizedValue;
    }
}
