<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Security;

use App\Modules\Security\Domain\Crypto\DataEncryptionKey;
use App\Modules\Security\Domain\Crypto\DataEncryptionKeyRing;
use App\Modules\Security\Domain\Crypto\UnknownDataEncryptionKeyId;
use InvalidArgumentException;
use Tests\TestCase;

final class DataEncryptionKeyRingTest extends TestCase
{
    public function test_current_and_retiring_keys_resolve_for_encryption_and_decryption(): void
    {
        $current = new DataEncryptionKey('data-2026-02', str_repeat('c', 32));
        $retiring = new DataEncryptionKey('data-2026-01', str_repeat('r', 32));
        $keyRing = new DataEncryptionKeyRing($current, [$retiring]);

        $this->assertSame($current, $keyRing->current());
        $this->assertSame($current, $keyRing->forDecryption('data-2026-02'));
        $this->assertSame($retiring, $keyRing->forDecryption('data-2026-01'));
    }

    public function test_unknown_key_ids_fail_without_exposing_key_material(): void
    {
        $keyRing = new DataEncryptionKeyRing(
            new DataEncryptionKey('data-2026-02', str_repeat('c', 32)),
            [],
        );

        $this->expectException(UnknownDataEncryptionKeyId::class);
        $this->expectExceptionMessage('Data encryption key is unavailable.');

        $keyRing->forDecryption('unknown');
    }

    public function test_duplicate_key_ids_are_rejected(): void
    {
        $current = new DataEncryptionKey('data-2026-02', str_repeat('c', 32));

        $this->expectException(InvalidArgumentException::class);

        new DataEncryptionKeyRing($current, [
            new DataEncryptionKey('data-2026-02', str_repeat('r', 32)),
        ]);
    }

    public function test_invalid_key_ids_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataEncryptionKey('unsafe key', str_repeat('c', 32));
    }

    public function test_aes_keys_must_contain_exactly_32_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DataEncryptionKey('data-2026-02', str_repeat('c', 31));
    }
}
