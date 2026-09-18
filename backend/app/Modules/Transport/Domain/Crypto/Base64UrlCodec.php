<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

use InvalidArgumentException;

final class Base64UrlCodec
{
    public function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function decode(string $value): string
    {
        if (preg_match('/\A[A-Za-z0-9_-]*\z/', $value) !== 1 || strlen($value) % 4 === 1) {
            throw new InvalidArgumentException('The value is not canonical base64url.');
        }

        $base64 = strtr($value, '-_', '+/');
        $paddingLength = (4 - strlen($base64) % 4) % 4;
        $decoded = base64_decode($base64.str_repeat('=', $paddingLength), true);

        if ($decoded === false || $this->encode($decoded) !== $value) {
            throw new InvalidArgumentException('The value is not canonical base64url.');
        }

        return $decoded;
    }
}
