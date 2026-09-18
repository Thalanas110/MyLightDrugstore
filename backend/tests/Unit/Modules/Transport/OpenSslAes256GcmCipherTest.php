<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

use App\Modules\Transport\Domain\Crypto\AesGcmCiphertext;
use App\Modules\Transport\Domain\Crypto\TransportAuthenticationFailed;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use InvalidArgumentException;
use Tests\TestCase;

final class OpenSslAes256GcmCipherTest extends TestCase
{
    public function test_encrypt_uses_the_transport_nonce_and_tag_lengths_and_round_trips_plaintext(): void
    {
        $cipher = new OpenSslAes256GcmCipher;
        $key = random_bytes(32);
        $plaintext = '{"data":{"medicineId":42}}';
        $additionalData = 'POST /api/v1/sales';

        $encrypted = $cipher->encrypt($key, $plaintext, $additionalData);

        $this->assertSame(12, strlen($encrypted->nonce));
        $this->assertSame(16, strlen($encrypted->tag));
        $this->assertSame(strlen($plaintext), strlen($encrypted->ciphertext));
        $this->assertSame($plaintext, $cipher->decrypt($key, $encrypted, $additionalData));
    }

    public function test_encrypt_rejects_a_key_that_is_not_32_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new OpenSslAes256GcmCipher)->encrypt(str_repeat('k', 31), 'payload', '');
    }

    public function test_ciphertext_tag_nonce_and_aad_fail_with_the_same_generic_authentication_error(): void
    {
        $cipher = new OpenSslAes256GcmCipher;
        $key = random_bytes(32);
        $additionalData = 'POST /api/v1/sales';
        $encrypted = $cipher->encrypt($key, 'sensitive payload', $additionalData);
        $cases = [
            'ciphertext' => [new AesGcmCiphertext($encrypted->nonce, self::flipFirstByte($encrypted->ciphertext), $encrypted->tag), $additionalData],
            'tag' => [new AesGcmCiphertext($encrypted->nonce, $encrypted->ciphertext, self::flipFirstByte($encrypted->tag)), $additionalData],
            'nonce' => [new AesGcmCiphertext(self::flipFirstByte($encrypted->nonce), $encrypted->ciphertext, $encrypted->tag), $additionalData],
            'additional data' => [$encrypted, $additionalData.'?page=2'],
        ];

        foreach ($cases as $field => [$tampered, $aad]) {
            try {
                $cipher->decrypt($key, $tampered, $aad);
                self::fail(sprintf('Tampered %s was accepted.', $field));
            } catch (TransportAuthenticationFailed $exception) {
                $this->assertSame('Authenticated decryption failed.', $exception->getMessage());
            }
        }
    }

    private static function flipFirstByte(string $value): string
    {
        $value[0] = chr(ord($value[0]) ^ 1);

        return $value;
    }
}
