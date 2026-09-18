<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

use InvalidArgumentException;

final class EncryptedSensitiveValueCodec
{
    public function encode(EncryptedSensitiveValue $value): string
    {
        return implode(':', [
            'v1',
            $value->keyId,
            base64_encode($value->authenticatedData->nonce),
            base64_encode($value->authenticatedData->ciphertext),
            base64_encode($value->authenticatedData->tag),
        ]);
    }

    public function decode(string $encoded): EncryptedSensitiveValue
    {
        $parts = explode(':', $encoded);

        if (count($parts) !== 5 || $parts[0] !== 'v1') {
            throw new InvalidArgumentException('The stored sensitive data format is invalid.');
        }

        $keyId = $parts[1];
        $nonce = self::decodeCanonicalBase64($parts[2]);
        $ciphertext = self::decodeCanonicalBase64($parts[3]);
        $tag = self::decodeCanonicalBase64($parts[4]);

        if (strlen($nonce) !== 12 || strlen($tag) !== 16) {
            throw new InvalidArgumentException('The stored sensitive data format is invalid.');
        }

        return new EncryptedSensitiveValue($keyId, new AuthenticatedData($nonce, $ciphertext, $tag));
    }

    private static function decodeCanonicalBase64(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);

        if ($decoded === false || base64_encode($decoded) !== $encoded) {
            throw new InvalidArgumentException('The stored sensitive data format is invalid.');
        }

        return $decoded;
    }
}
