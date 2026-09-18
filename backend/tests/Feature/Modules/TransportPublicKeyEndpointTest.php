<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use Tests\TestCase;

final class TransportPublicKeyEndpointTest extends TestCase
{
    public function test_public_key_route_returns_the_current_rsa_key_as_plaintext_bootstrap_metadata(): void
    {
        $fixture = require dirname(__DIR__, 2).'/Fixtures/Transport/rsa_oaep_webcrypto.php';
        $publicKeyPem = self::pem('PUBLIC KEY', $fixture['publicKeyBase64']);
        $privateKeyPem = self::pem('PRIVATE KEY', $fixture['privateKeyBase64']);
        config()->set('transport.keys', [
            'current' => [
                'key_id' => 'transport-current',
                'public_key_base64' => base64_encode($publicKeyPem),
                'private_key_base64' => base64_encode($privateKeyPem),
            ],
            'retiring' => [[
                'key_id' => 'transport-retiring',
                'public_key_base64' => base64_encode($publicKeyPem),
                'private_key_base64' => base64_encode($privateKeyPem),
            ]],
        ]);

        $this->getJson('/api/v1/transport/public-key')->assertExactJson([
            'data' => [
                'version' => 1,
                'algorithm' => 'RSA-OAEP-256',
                'transportAlgorithm' => 'A256GCM',
                'keyId' => 'transport-current',
                'publicKey' => $publicKeyPem,
            ],
        ])->assertOk();
    }

    private static function pem(string $type, string $encodedKey): string
    {
        return "-----BEGIN {$type}-----\n".chunk_split($encodedKey, 64, "\n")."-----END {$type}-----\n";
    }
}
