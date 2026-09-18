<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

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
}
