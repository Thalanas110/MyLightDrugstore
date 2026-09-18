<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

use App\Modules\Transport\Domain\Crypto\TransportKeyMaterial;
use App\Modules\Transport\Domain\Crypto\TransportKeyRing;
use App\Modules\Transport\Domain\Crypto\UnknownTransportKeyId;
use InvalidArgumentException;
use Tests\TestCase;

final class TransportKeyRingTest extends TestCase
{
    public function test_current_and_retiring_key_ids_resolve_for_decryption(): void
    {
        $current = new TransportKeyMaterial('transport-2026-02', 'current public key', 'current private key');
        $retiring = new TransportKeyMaterial('transport-2026-01', 'retiring public key', 'retiring private key');
        $ring = new TransportKeyRing($current, [$retiring]);

        $this->assertSame($current, $ring->current());
        $this->assertSame($current, $ring->forDecryption('transport-2026-02'));
        $this->assertSame($retiring, $ring->forDecryption('transport-2026-01'));
    }

    public function test_unknown_key_ids_fail_with_a_generic_exception(): void
    {
        $ring = new TransportKeyRing(
            new TransportKeyMaterial('transport-2026-02', 'public', 'private'),
            [],
        );

        $this->expectException(UnknownTransportKeyId::class);
        $this->expectExceptionMessage('Transport key is unavailable.');

        $ring->forDecryption('unrecognized-key');
    }

    public function test_duplicate_current_or_retiring_ids_are_rejected(): void
    {
        $current = new TransportKeyMaterial('transport-2026-02', 'current public', 'current private');

        $this->expectException(InvalidArgumentException::class);

        new TransportKeyRing($current, [
            new TransportKeyMaterial('transport-2026-02', 'duplicate public', 'duplicate private'),
        ]);
    }

    public function test_invalid_key_material_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TransportKeyMaterial('bad key id', '', 'private key');
    }

    public function test_non_key_material_cannot_be_added_to_the_ring(): void
    {
        $current = new TransportKeyMaterial('transport-2026-02', 'current public', 'current private');

        $this->expectException(InvalidArgumentException::class);

        new TransportKeyRing($current, ['not a key']);
    }
}
