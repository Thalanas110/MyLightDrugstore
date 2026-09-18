<?php

declare(strict_types=1);

namespace App\Modules\Transport\Infrastructure\Crypto;

use App\Modules\Transport\Domain\Crypto\AesGcmCiphertext;
use App\Modules\Transport\Domain\Crypto\AuthenticatedEncryptor;
use InvalidArgumentException;
use RuntimeException;

final class OpenSslAes256GcmCipher implements AuthenticatedEncryptor
{
    private const string ALGORITHM = 'aes-256-gcm';

    private const int NONCE_LENGTH = 12;

    private const int TAG_LENGTH = 16;

    public function encrypt(string $key, string $plaintext, string $additionalData): AesGcmCiphertext
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
            throw new RuntimeException('Authenticated encryption failed.');
        }

        return new AesGcmCiphertext($nonce, $ciphertext, $tag);
    }

    public function decrypt(string $key, AesGcmCiphertext $encrypted, string $additionalData): string
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
            throw new RuntimeException('Authenticated decryption failed.');
        }

        return $plaintext;
    }

    private static function assertKeyLength(string $key): void
    {
        if (strlen($key) !== 32) {
            throw new InvalidArgumentException('The AES-256 key must be exactly 32 bytes.');
        }
    }
}
