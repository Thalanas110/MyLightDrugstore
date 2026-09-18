<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\Transport\Domain\Crypto\AuthenticatedEncryptor;
use App\Modules\Transport\Domain\Crypto\RsaOaepKeyCipher;
use App\Modules\Transport\Domain\Crypto\TransportKeyRing;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use App\Modules\Transport\Infrastructure\Crypto\PhpseclibRsaOaepKeyCipher;
use Tests\TestCase;

final class TransportModuleProviderTest extends TestCase
{
    public function test_container_resolves_the_openssl_authenticated_encryptor_adapter(): void
    {
        $this->assertInstanceOf(OpenSslAes256GcmCipher::class, app(AuthenticatedEncryptor::class));
    }

    public function test_container_resolves_the_phpseclib_rsa_oaep_key_cipher(): void
    {
        $this->assertInstanceOf(PhpseclibRsaOaepKeyCipher::class, app(RsaOaepKeyCipher::class));
    }

    public function test_container_builds_the_configured_transport_key_ring(): void
    {
        $fixture = require dirname(__DIR__, 2).'/Fixtures/Transport/rsa_oaep_webcrypto.php';
        config()->set('transport.keys', [
            'current' => [
                'key_id' => 'transport-test-current',
                'public_key_base64' => base64_encode(self::pem('PUBLIC KEY', $fixture['publicKeyBase64'])),
                'private_key_base64' => base64_encode(self::pem('PRIVATE KEY', $fixture['privateKeyBase64'])),
            ],
            'retiring' => [],
        ]);

        $this->assertSame('transport-test-current', app(TransportKeyRing::class)->current()->keyId);
    }

    private static function pem(string $type, string $encodedKey): string
    {
        return "-----BEGIN {$type}-----\n".chunk_split($encodedKey, 64, "\n")."-----END {$type}-----\n";
    }
}
