<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Security\Application\SensitiveDataProtector;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class SecurityModuleProviderTest extends TestCase
{
    public function test_module_binds_sensitive_data_protection_using_its_separate_key_ring(): void
    {
        Config::set('data_protection.keys', [
            'current' => [
                'key_id' => 'data-test-2026-01',
                'key_base64' => base64_encode(str_repeat('d', 32)),
            ],
            'retiring' => [],
        ]);

        $protector = app(SensitiveDataProtector::class);
        $encrypted = $protector->encrypt('private username', 'users.username');

        $this->assertSame('private username', $protector->decrypt($encrypted, 'users.username'));
    }
}
