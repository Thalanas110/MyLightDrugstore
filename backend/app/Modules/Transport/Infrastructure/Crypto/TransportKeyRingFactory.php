<?php

declare(strict_types=1);

namespace App\Modules\Transport\Infrastructure\Crypto;

use App\Modules\Transport\Domain\Crypto\TransportKeyMaterial;
use App\Modules\Transport\Domain\Crypto\TransportKeyRing;
use InvalidArgumentException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;
use Throwable;

final class TransportKeyRingFactory
{
    /**
     * @param  array<mixed, mixed>  $configuration
     */
    public function fromConfiguration(array $configuration): TransportKeyRing
    {
        $currentConfiguration = $configuration['current'] ?? null;
        $retiringConfiguration = $configuration['retiring'] ?? null;

        if (! is_array($currentConfiguration) || ! is_array($retiringConfiguration) || ! array_is_list($retiringConfiguration)) {
            throw self::invalidConfiguration();
        }

        $current = $this->material($currentConfiguration);
        $retiring = [];

        foreach ($retiringConfiguration as $retiringKeyConfiguration) {
            if (! is_array($retiringKeyConfiguration)) {
                throw self::invalidConfiguration();
            }

            $retiring[] = $this->material($retiringKeyConfiguration);
        }

        return new TransportKeyRing($current, $retiring);
    }

    /**
     * @param  array<mixed, mixed>  $configuration
     */
    private function material(array $configuration): TransportKeyMaterial
    {
        $keyId = $configuration['key_id'] ?? null;
        $publicKeyBase64 = $configuration['public_key_base64'] ?? null;
        $privateKeyBase64 = $configuration['private_key_base64'] ?? null;

        if (! is_string($keyId) || ! is_string($publicKeyBase64) || ! is_string($privateKeyBase64)) {
            throw self::invalidConfiguration();
        }

        $publicKeyPem = self::decodePem($publicKeyBase64);
        $privateKeyPem = self::decodePem($privateKeyBase64);

        try {
            $publicKey = PublicKeyLoader::loadPublicKey($publicKeyPem);
            $privateKey = PublicKeyLoader::loadPrivateKey($privateKeyPem);

            if (! $publicKey instanceof PublicKey || ! $privateKey instanceof PrivateKey
                || $publicKey->getLength() < 2048 || $privateKey->getLength() < 2048) {
                throw self::invalidConfiguration();
            }

            $derivedPublicKey = $privateKey->getPublicKey();

            if (! $derivedPublicKey instanceof PublicKey) {
                throw self::invalidConfiguration();
            }

            $publicFingerprint = $publicKey->getFingerprint('sha256');
            $derivedFingerprint = $derivedPublicKey->getFingerprint('sha256');

            if (! is_string($publicFingerprint) || ! is_string($derivedFingerprint)
                || ! hash_equals($publicFingerprint, $derivedFingerprint)) {
                throw self::invalidConfiguration();
            }
        } catch (Throwable) {
            throw self::invalidConfiguration();
        }

        return new TransportKeyMaterial($keyId, $publicKeyPem, $privateKeyPem);
    }

    private static function decodePem(string $encoded): string
    {
        $pem = base64_decode($encoded, true);

        if ($pem === false || base64_encode($pem) !== $encoded) {
            throw self::invalidConfiguration();
        }

        return $pem;
    }

    private static function invalidConfiguration(): InvalidArgumentException
    {
        return new InvalidArgumentException('Transport key configuration is invalid.');
    }
}
