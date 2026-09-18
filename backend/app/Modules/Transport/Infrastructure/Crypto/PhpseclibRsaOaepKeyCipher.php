<?php

declare(strict_types=1);

namespace App\Modules\Transport\Infrastructure\Crypto;

use App\Modules\Transport\Domain\Crypto\RsaOaepKeyCipher;
use App\Modules\Transport\Domain\Crypto\TransportKeyExchangeFailed;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;
use Throwable;

final class PhpseclibRsaOaepKeyCipher implements RsaOaepKeyCipher
{
    private const int AES_KEY_LENGTH = 32;

    public function wrap(string $publicKeyPem, string $aesKey): string
    {
        if (strlen($aesKey) !== self::AES_KEY_LENGTH) {
            throw self::failed();
        }

        try {
            $publicKey = PublicKeyLoader::loadPublicKey($publicKeyPem);

            if (! $publicKey instanceof PublicKey) {
                throw self::failed();
            }

            $wrappedKey = $publicKey
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->encrypt($aesKey);

            if (! is_string($wrappedKey) || $wrappedKey === '') {
                throw self::failed();
            }

            return $wrappedKey;
        } catch (Throwable) {
            throw self::failed();
        }
    }

    public function unwrap(string $privateKeyPem, string $wrappedKey): string
    {
        if ($wrappedKey === '') {
            throw self::failed();
        }

        try {
            $privateKey = PublicKeyLoader::loadPrivateKey($privateKeyPem);

            if (! $privateKey instanceof PrivateKey) {
                throw self::failed();
            }

            $aesKey = $privateKey
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->decrypt($wrappedKey);

            if (! is_string($aesKey) || strlen($aesKey) !== self::AES_KEY_LENGTH) {
                throw self::failed();
            }

            return $aesKey;
        } catch (Throwable) {
            throw self::failed();
        }
    }

    private static function failed(): TransportKeyExchangeFailed
    {
        return new TransportKeyExchangeFailed('Transport key exchange failed.');
    }
}
