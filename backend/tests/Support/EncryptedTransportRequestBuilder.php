<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use App\Modules\Transport\Domain\Crypto\RequestAdditionalData;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use App\Modules\Transport\Infrastructure\Crypto\PhpseclibRsaOaepKeyCipher;

final class EncryptedTransportRequestBuilder
{
    public const KEY_ID = 'transport-test-current';

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     header: string,
     *     aesKey: string,
     *     envelope: array{version: int, algorithm: string, keyId: string, iv: string, ciphertext: string}
     * }
     */
    public function build(string $method, string $path, array $payload, ?string $aadTarget = null): array
    {
        [$publicKeyPem] = $this->configureCurrentKey();

        $aesKey = random_bytes(32);
        $descriptor = json_encode([
            'kind' => 'json',
            'contentType' => 'application/json',
            'value' => $payload === [] ? '{}' : json_encode($payload, JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
        $additionalData = RequestAdditionalData::fromRequestTarget($method, $aadTarget ?? $path);
        $encrypted = (new OpenSslAes256GcmCipher)->encrypt($aesKey, $descriptor, $additionalData);
        $codec = new Base64UrlCodec;
        $wrappedKey = (new PhpseclibRsaOaepKeyCipher)->wrap($publicKeyPem, $aesKey);

        return [
            'header' => self::KEY_ID.'.'.$codec->encode($wrappedKey),
            'aesKey' => $aesKey,
            'envelope' => [
                'version' => 1,
                'algorithm' => 'A256GCM',
                'keyId' => self::KEY_ID,
                'iv' => $codec->encode($encrypted->nonce),
                'ciphertext' => $codec->encode($encrypted->ciphertext.$encrypted->tag),
            ],
        ];
    }

    /**
     * @return array{header: string, aesKey: string}
     */
    public function buildBodyless(): array
    {
        [$publicKeyPem] = $this->configureCurrentKey();
        $aesKey = random_bytes(32);
        $wrappedKey = (new PhpseclibRsaOaepKeyCipher)->wrap($publicKeyPem, $aesKey);

        return [
            'header' => self::KEY_ID.'.'.(new Base64UrlCodec)->encode($wrappedKey),
            'aesKey' => $aesKey,
        ];
    }

    /**
     * @return array{string, string}
     */
    private function configureCurrentKey(): array
    {
        $fixture = $this->fixture();
        $publicKeyPem = self::pem('PUBLIC KEY', $fixture['publicKeyBase64']);
        $privateKeyPem = self::pem('PRIVATE KEY', $fixture['privateKeyBase64']);
        config()->set('transport.keys', [
            'current' => [
                'key_id' => self::KEY_ID,
                'public_key_base64' => base64_encode($publicKeyPem),
                'private_key_base64' => base64_encode($privateKeyPem),
            ],
            'retiring' => [],
        ]);

        return [$publicKeyPem, $privateKeyPem];
    }

    /**
     * @return array{publicKeyBase64: string, privateKeyBase64: string, wrongPrivateKeyBase64: string, aesKeyHex: string, wrappedKeyBase64: string}
     */
    private function fixture(): array
    {
        return require dirname(__DIR__).'/Fixtures/Transport/rsa_oaep_webcrypto.php';
    }

    private static function pem(string $type, string $encodedKey): string
    {
        return "-----BEGIN {$type}-----\n".chunk_split($encodedKey, 64, "\n")."-----END {$type}-----\n";
    }
}
