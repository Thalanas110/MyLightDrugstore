<?php

declare(strict_types=1);

namespace App\Modules\Security\Application;

use App\Modules\Security\Domain\Crypto\AuthenticatedDataCipher;
use App\Modules\Security\Domain\Crypto\DataEncryptionKeyRing;
use App\Modules\Security\Domain\Crypto\EncryptedSensitiveValue;
use App\Modules\Security\Domain\Crypto\EncryptedSensitiveValueCodec;
use App\Modules\Security\Domain\Crypto\EncryptionContext;
use App\Modules\Security\Domain\Crypto\SensitiveDataDecryptionFailed;
use Throwable;

final class DataProtectionService implements SensitiveDataProtector
{
    public function __construct(
        private readonly DataEncryptionKeyRing $keyRing,
        private readonly AuthenticatedDataCipher $cipher,
        private readonly EncryptedSensitiveValueCodec $codec,
    ) {}

    public function encrypt(string $plaintext, string $context): string
    {
        $encryptionContext = new EncryptionContext($context);
        $currentKey = $this->keyRing->current();
        $encrypted = $this->cipher->encrypt($currentKey->key, $plaintext, $encryptionContext->additionalData);

        return $this->codec->encode(new EncryptedSensitiveValue($currentKey->keyId, $encrypted));
    }

    public function decrypt(string $encoded, string $context): string
    {
        $encryptionContext = new EncryptionContext($context);

        try {
            $encryptedValue = $this->codec->decode($encoded);
            $key = $this->keyRing->forDecryption($encryptedValue->keyId);

            return $this->cipher->decrypt($key->key, $encryptedValue->authenticatedData, $encryptionContext->additionalData);
        } catch (Throwable) {
            throw new SensitiveDataDecryptionFailed('Stored sensitive data cannot be decrypted.');
        }
    }
}
