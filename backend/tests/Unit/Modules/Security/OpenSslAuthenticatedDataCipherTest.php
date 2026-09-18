<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Security;

use App\Modules\Security\Domain\Crypto\AuthenticatedData;
use App\Modules\Security\Domain\Crypto\SensitiveDataDecryptionFailed;
use App\Modules\Security\Infrastructure\Crypto\OpenSslAuthenticatedDataCipher;
use InvalidArgumentException;
use Tests\TestCase;

final class OpenSslAuthenticatedDataCipherTest extends TestCase
{
    public function test_it_encrypts_and_decrypts_with_a_fresh_nonce(): void
    {
        $cipher = new OpenSslAuthenticatedDataCipher;
        $key = random_bytes(32);

        $first = $cipher->encrypt($key, 'private staff name', 'users.full_name');
        $second = $cipher->encrypt($key, 'private staff name', 'users.full_name');

        $this->assertSame('private staff name', $cipher->decrypt($key, $first, 'users.full_name'));
        $this->assertNotSame($first->nonce, $second->nonce);
        $this->assertSame(12, strlen($first->nonce));
        $this->assertSame(16, strlen($first->tag));
    }

    public function test_authentication_failures_use_one_generic_exception(): void
    {
        $cipher = new OpenSslAuthenticatedDataCipher;
        $key = random_bytes(32);
        $encrypted = $cipher->encrypt($key, 'private staff name', 'users.full_name');

        foreach ([
            $cipher->encrypt(random_bytes(32), 'private staff name', 'users.full_name'),
            $cipher->encrypt($key, 'private staff name', 'users.username'),
            new AuthenticatedData(
                $encrypted->nonce,
                $encrypted->ciphertext.chr(ord($encrypted->ciphertext[0]) ^ 1),
                $encrypted->tag,
            ),
            new AuthenticatedData($encrypted->nonce, $encrypted->ciphertext, str_repeat("\0", 16)),
        ] as $tampered) {
            try {
                $cipher->decrypt($key, $tampered, 'users.full_name');
                $this->fail('Tampered authenticated data was accepted.');
            } catch (SensitiveDataDecryptionFailed $exception) {
                $this->assertSame('Stored sensitive data cannot be decrypted.', $exception->getMessage());
            }
        }
    }

    public function test_aes_256_keys_must_be_exactly_32_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new OpenSslAuthenticatedDataCipher)->encrypt(str_repeat('x', 31), 'payload', 'context');
    }

    public function test_ciphertext_values_reject_invalid_nonce_or_tag_lengths(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AuthenticatedData('short', 'body', str_repeat('t', 16));
    }
}
