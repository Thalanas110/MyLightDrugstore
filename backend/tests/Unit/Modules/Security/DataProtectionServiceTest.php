<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Security;

use App\Modules\Security\Application\DataProtectionService;
use App\Modules\Security\Application\SensitiveDataProtector;
use App\Modules\Security\Domain\Crypto\DataEncryptionKey;
use App\Modules\Security\Domain\Crypto\DataEncryptionKeyRing;
use App\Modules\Security\Domain\Crypto\EncryptedSensitiveValueCodec;
use App\Modules\Security\Domain\Crypto\SensitiveDataDecryptionFailed;
use App\Modules\Security\Infrastructure\Crypto\OpenSslAuthenticatedDataCipher;
use Tests\TestCase;

final class DataProtectionServiceTest extends TestCase
{
    public function test_it_encrypts_values_with_the_active_key_and_decrypts_them(): void
    {
        $protector = $this->protector(new DataEncryptionKeyRing(
            new DataEncryptionKey('data-2026-02', random_bytes(32)),
            [],
        ));

        $encrypted = $protector->encrypt('private staff name', 'users.full_name');

        $this->assertStringStartsWith('v1:data-2026-02:', $encrypted);
        $this->assertSame('private staff name', $protector->decrypt($encrypted, 'users.full_name'));
    }

    public function test_encrypting_the_same_value_uses_a_fresh_nonce(): void
    {
        $protector = $this->protector(new DataEncryptionKeyRing(
            new DataEncryptionKey('data-2026-02', random_bytes(32)),
            [],
        ));

        $first = $protector->encrypt('private staff name', 'users.full_name');
        $second = $protector->encrypt('private staff name', 'users.full_name');

        $this->assertNotSame($first, $second);
    }

    public function test_retiring_keys_can_decrypt_values_while_new_values_use_the_current_key(): void
    {
        $retiringKey = new DataEncryptionKey('data-2026-01', random_bytes(32));
        $oldProtector = $this->protector(new DataEncryptionKeyRing($retiringKey, []));
        $oldValue = $oldProtector->encrypt('old staff name', 'users.full_name');
        $currentKey = new DataEncryptionKey('data-2026-02', random_bytes(32));
        $rotatedProtector = $this->protector(new DataEncryptionKeyRing($currentKey, [$retiringKey]));

        $newValue = $rotatedProtector->encrypt('new staff name', 'users.full_name');

        $this->assertSame('old staff name', $rotatedProtector->decrypt($oldValue, 'users.full_name'));
        $this->assertStringStartsWith('v1:data-2026-02:', $newValue);
    }

    public function test_ciphertext_tampering_context_mismatch_and_unknown_keys_fail_generically(): void
    {
        $protector = $this->protector(new DataEncryptionKeyRing(
            new DataEncryptionKey('data-2026-02', random_bytes(32)),
            [],
        ));
        $encrypted = $protector->encrypt('private staff name', 'users.full_name');
        $tampered = substr($encrypted, 0, -1).($encrypted[-1] === 'A' ? 'B' : 'A');

        foreach ([
            [$encrypted, 'users.username'],
            [$tampered, 'users.full_name'],
            ['v1:missing-key:AAAAAAAAAAAAAAAA::AAAAAAAAAAAAAAAAAAAAAA==', 'users.full_name'],
            ['malformed', 'users.full_name'],
        ] as [$value, $context]) {
            try {
                $protector->decrypt($value, $context);
                $this->fail('Unauthenticated sensitive data was accepted.');
            } catch (SensitiveDataDecryptionFailed $exception) {
                $this->assertSame('Stored sensitive data cannot be decrypted.', $exception->getMessage());
            }
        }
    }

    private function protector(DataEncryptionKeyRing $keyRing): SensitiveDataProtector
    {
        return new DataProtectionService(
            $keyRing,
            new OpenSslAuthenticatedDataCipher,
            new EncryptedSensitiveValueCodec,
        );
    }
}
