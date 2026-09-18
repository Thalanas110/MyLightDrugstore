<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Security;

use App\Modules\Security\Domain\Crypto\AuthenticatedData;
use App\Modules\Security\Domain\Crypto\EncryptedSensitiveValue;
use App\Modules\Security\Domain\Crypto\EncryptedSensitiveValueCodec;
use InvalidArgumentException;
use Tests\TestCase;

final class EncryptedSensitiveValueCodecTest extends TestCase
{
    public function test_it_round_trips_a_versioned_value_with_its_key_id(): void
    {
        $value = new EncryptedSensitiveValue(
            'data-2026-02',
            new AuthenticatedData(random_bytes(12), "\x00ciphertext\xff", random_bytes(16)),
        );
        $codec = new EncryptedSensitiveValueCodec;

        $encoded = $codec->encode($value);

        $this->assertStringStartsWith('v1:data-2026-02:', $encoded);
        $this->assertEquals($value, $codec->decode($encoded));
    }

    public function test_it_preserves_empty_ciphertext(): void
    {
        $value = new EncryptedSensitiveValue(
            'data-2026-02',
            new AuthenticatedData(random_bytes(12), '', random_bytes(16)),
        );
        $codec = new EncryptedSensitiveValueCodec;

        $this->assertEquals($value, $codec->decode($codec->encode($value)));
    }

    public function test_it_rejects_unsupported_versions_and_malformed_fields(): void
    {
        $codec = new EncryptedSensitiveValueCodec;

        foreach ([
            'v2:data-2026-02:AAAAAAAAAAAAAAAA::AAAAAAAAAAAAAAAAAAAAAA==',
            'v1:data-2026-02:AAAA::AAAAAAAAAAAAAAAAAAAAAA==',
            'v1:data-2026-02:AAAAAAAAAAAAAAAA::AA==',
            'v1:data-2026-02:AAAAAAAAAAAAAAAA::AAAAAAAAAAAAAAAAAAAAAA==:extra',
            'v1:bad key:AAAAAAAAAAAAAAAA::AAAAAAAAAAAAAAAAAAAAAA==',
        ] as $malformed) {
            try {
                $codec->decode($malformed);
                $this->fail('Malformed encrypted value was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_it_rejects_noncanonical_base64(): void
    {
        $codec = new EncryptedSensitiveValueCodec;

        $this->expectException(InvalidArgumentException::class);

        $codec->decode('v1:data-2026-02:AAAAAAAAAAAAAAAA::AAAAAAAAAAAAAAAAAAAAAA==\n');
    }
}
