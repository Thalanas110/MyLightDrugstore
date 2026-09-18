<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Security;

use App\Modules\Security\Domain\Crypto\DataEncryptionKeyRing;
use App\Modules\Security\Infrastructure\Crypto\DataEncryptionKeyRingFactory;
use InvalidArgumentException;
use Tests\TestCase;

final class DataEncryptionKeyRingFactoryTest extends TestCase
{
    public function test_it_builds_current_and_retiring_keys_from_configuration(): void
    {
        $currentKey = str_repeat('c', 32);
        $retiringKey = str_repeat('r', 32);
        $factory = new DataEncryptionKeyRingFactory;

        $keyRing = $factory->fromConfiguration([
            'current' => [
                'key_id' => 'data-2026-02',
                'key_base64' => base64_encode($currentKey),
            ],
            'retiring' => [[
                'key_id' => 'data-2026-01',
                'key_base64' => base64_encode($retiringKey),
            ]],
        ]);

        $this->assertInstanceOf(DataEncryptionKeyRing::class, $keyRing);
        $this->assertSame($currentKey, $keyRing->current()->key);
        $this->assertSame($retiringKey, $keyRing->forDecryption('data-2026-01')->key);
    }

    public function test_it_rejects_missing_or_malformed_key_configuration(): void
    {
        $factory = new DataEncryptionKeyRingFactory;
        $configurations = [
            [],
            ['current' => ['key_id' => 'data-2026-02', 'key_base64' => '***'], 'retiring' => []],
            ['current' => ['key_id' => 'data-2026-02', 'key_base64' => base64_encode(str_repeat('c', 31))], 'retiring' => []],
            ['current' => ['key_id' => 'data-2026-02', 'key_base64' => base64_encode(str_repeat('c', 32))."\n"], 'retiring' => []],
            ['current' => ['key_id' => 'data-2026-02', 'key_base64' => base64_encode(str_repeat('c', 32))], 'retiring' => 'not-a-list'],
        ];

        foreach ($configurations as $configuration) {
            try {
                $factory->fromConfiguration($configuration);
                $this->fail('Invalid encryption key configuration was accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_it_rejects_malformed_retiring_key_entries(): void
    {
        $factory = new DataEncryptionKeyRingFactory;

        $this->expectException(InvalidArgumentException::class);

        $factory->fromConfiguration([
            'current' => [
                'key_id' => 'data-2026-02',
                'key_base64' => base64_encode(str_repeat('c', 32)),
            ],
            'retiring' => [['key_id' => 'data-2026-01', 'key_base64' => 'invalid']],
        ]);
    }
}
