<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

use App\Modules\Transport\Infrastructure\Crypto\TransportKeyRingFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TransportKeyRingFactoryTest extends TestCase
{
    public function test_configuration_loads_current_and_retiring_key_material(): void
    {
        $ring = (new TransportKeyRingFactory)->fromConfiguration(self::validConfiguration());

        $this->assertSame('transport-2026-02', $ring->current()->keyId);
        $this->assertSame('transport-2026-01', $ring->forDecryption('transport-2026-01')->keyId);
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $ring->forDecryption('transport-2026-01')->privateKeyPem);
    }

    #[DataProvider('invalidConfigurations')]
    public function test_invalid_configuration_is_rejected(array $configuration): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new TransportKeyRingFactory)->fromConfiguration($configuration);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidConfigurations(): array
    {
        $valid = self::validConfiguration();
        $mismatched = $valid;
        $fixture = self::fixture();
        $mismatched['current']['private_key_base64'] = base64_encode(self::pem('PRIVATE KEY', $fixture['wrongPrivateKeyBase64']));

        return [
            'missing current key' => [['retiring' => []]],
            'invalid key id' => [[...$valid, 'current' => [...$valid['current'], 'key_id' => 'bad key']]],
            'invalid base64 key' => [[...$valid, 'current' => [...$valid['current'], 'public_key_base64' => '%%%']]],
            'public and private keys do not match' => [$mismatched],
            'retiring keys must be a list' => [[...$valid, 'retiring' => ['bad' => 'shape']]],
        ];
    }

    /**
     * @return array{
     *     current: array{key_id: string, public_key_base64: string, private_key_base64: string},
     *     retiring: list<array{key_id: string, public_key_base64: string, private_key_base64: string}>
     * }
     */
    private static function validConfiguration(): array
    {
        $fixture = self::fixture();
        $current = [
            'key_id' => 'transport-2026-02',
            'public_key_base64' => base64_encode(self::pem('PUBLIC KEY', $fixture['publicKeyBase64'])),
            'private_key_base64' => base64_encode(self::pem('PRIVATE KEY', $fixture['privateKeyBase64'])),
        ];

        return [
            'current' => $current,
            'retiring' => [[...$current, 'key_id' => 'transport-2026-01']],
        ];
    }

    /**
     * @return array{publicKeyBase64: string, privateKeyBase64: string, wrongPrivateKeyBase64: string, aesKeyHex: string, wrappedKeyBase64: string}
     */
    private static function fixture(): array
    {
        return require dirname(__DIR__, 3).'/Fixtures/Transport/rsa_oaep_webcrypto.php';
    }

    private static function pem(string $type, string $encodedKey): string
    {
        return "-----BEGIN {$type}-----\n".chunk_split($encodedKey, 64, "\n")."-----END {$type}-----\n";
    }
}
