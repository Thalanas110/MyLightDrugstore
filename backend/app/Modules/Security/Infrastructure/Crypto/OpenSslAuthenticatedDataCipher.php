<?php

declare(strict_types=1);

namespace App\Modules\Security\Infrastructure\Crypto;

use App\Modules\Security\Domain\Crypto\AuthenticatedData;
use App\Modules\Security\Domain\Crypto\AuthenticatedDataCipher;
use App\Modules\Security\Domain\Crypto\SensitiveDataDecryptionFailed;
use InvalidArgumentException;
use RuntimeException;

final class OpenSslAuthenticatedDataCipher implements AuthenticatedDataCipher
{
    private const string ALGORITHM = 'aes-256-gcm';

    private const int NONCE_LENGTH = 12;

    private const int TAG_LENGTH = 16;

    public function encrypt(string $key, string $plaintext, string $additionalData): AuthenticatedData
    {
        self::assertKeyLength($key);

        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $additionalData,
            self::TAG_LENGTH,
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Authenticated data encryption failed.');
        }

        return new AuthenticatedData($nonce, $ciphertext, $tag);
    }

    public function decrypt(string $key, AuthenticatedData $encrypted, string $additionalData): string
    {
        self::assertKeyLength($key);

        $plaintext = openssl_decrypt(
            $encrypted->ciphertext,
            self::ALGORITHM,
            $key,
            OPENSSL_RAW_DATA,
            $encrypted->nonce,
            $encrypted->tag,
            $additionalData,
        );

        if ($plaintext === false) {
            throw new SensitiveDataDecryptionFailed('Stored sensitive data cannot be decrypted.');
        }

        return $plaintext;
    }

    private static function assertKeyLength(string $key): void
    {
        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('AES-256-GCM keys must contain exactly 32 bytes.');
        }
    }
}
