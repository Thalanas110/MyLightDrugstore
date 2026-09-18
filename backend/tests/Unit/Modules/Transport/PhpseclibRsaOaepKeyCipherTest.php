<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

use App\Modules\Transport\Domain\Crypto\TransportKeyExchangeFailed;
use App\Modules\Transport\Infrastructure\Crypto\PhpseclibRsaOaepKeyCipher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PhpseclibRsaOaepKeyCipherTest extends TestCase
{
    public function test_unwraps_a_webcrypto_rsa_oaep_sha256_fixture_and_round_trips_a_32_byte_key(): void
    {
        $fixture = self::webCryptoFixture();
        $cipher = new PhpseclibRsaOaepKeyCipher;
        $publicKey = self::pem('PUBLIC KEY', $fixture['publicKeyBase64']);
        $privateKey = self::pem('PRIVATE KEY', $fixture['privateKeyBase64']);
        $aesKey = hex2bin($fixture['aesKeyHex']);
        $wrappedKey = base64_decode($fixture['wrappedKeyBase64'], true);

        self::assertIsString($aesKey);
        self::assertIsString($wrappedKey);
        $this->assertSame($aesKey, $cipher->unwrap($privateKey, $wrappedKey));

        $freshWrappedKey = $cipher->wrap($publicKey, $aesKey);

        $this->assertNotSame($wrappedKey, $freshWrappedKey);
        $this->assertSame($aesKey, $cipher->unwrap($privateKey, $freshWrappedKey));
    }

    #[DataProvider('invalidExchanges')]
    public function test_invalid_key_material_and_wrapped_keys_fail_with_a_generic_exception(string $publicOrPrivateKey, string $wrappedKey): void
    {
        $this->expectException(TransportKeyExchangeFailed::class);
        $this->expectExceptionMessage('Transport key exchange failed.');

        (new PhpseclibRsaOaepKeyCipher)->unwrap($publicOrPrivateKey, $wrappedKey);
    }

    public function test_wrap_rejects_an_aes_key_that_is_not_32_bytes(): void
    {
        $fixture = self::webCryptoFixture();

        $this->expectException(TransportKeyExchangeFailed::class);
        $this->expectExceptionMessage('Transport key exchange failed.');

        (new PhpseclibRsaOaepKeyCipher)->wrap(
            self::pem('PUBLIC KEY', $fixture['publicKeyBase64']),
            str_repeat('k', 31),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidExchanges(): array
    {
        $fixture = self::webCryptoFixture();
        $privateKey = self::pem('PRIVATE KEY', $fixture['privateKeyBase64']);
        $wrappedKey = base64_decode($fixture['wrappedKeyBase64'], true);

        if (! is_string($wrappedKey)) {
            throw new \RuntimeException('Invalid test fixture.');
        }

        $wrongKey = $wrappedKey;
        $wrongKey[0] = chr(ord($wrongKey[0]) ^ 1);

        return [
            'malformed private key' => ['invalid', $wrappedKey],
            'public key cannot decrypt' => [self::pem('PUBLIC KEY', $fixture['publicKeyBase64']), $wrappedKey],
            'wrong private key' => [self::pem('PRIVATE KEY', $fixture['wrongPrivateKeyBase64']), $wrappedKey],
            'tampered wrapped key' => [$privateKey, $wrongKey],
            'wrong wrapped key size' => [$privateKey, 'invalid'],
        ];
    }

    /**
     * @return array{publicKeyBase64: string, privateKeyBase64: string, wrongPrivateKeyBase64: string, aesKeyHex: string, wrappedKeyBase64: string}
     */
    private static function webCryptoFixture(): array
    {
        return require dirname(__DIR__, 3).'/Fixtures/Transport/rsa_oaep_webcrypto.php';
    }

    private static function pem(string $type, string $encodedKey): string
    {
        return "-----BEGIN {$type}-----\n".chunk_split($encodedKey, 64, "\n")."-----END {$type}-----\n";
    }
}
