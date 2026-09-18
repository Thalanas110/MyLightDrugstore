<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class IdentityBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('data_protection.keys', [
            'current' => [
                'key_id' => 'test-data-2026-01',
                'key_base64' => base64_encode(str_repeat('d', 32)),
            ],
            'retiring' => [],
        ]);
        Config::set('data_protection.username_lookup_key_base64', base64_encode(str_repeat('l', 32)));
        Config::set('identity.bootstrap_admin', [
            'username' => 'owner.admin',
            'full_name' => 'Store Owner',
            'password' => 'LongBootstrapPassword!2026',
        ]);
    }

    public function test_it_creates_one_encrypted_admin_when_seeded_repeatedly(): void
    {
        $this->seed(DatabaseSeeder::class);
        $firstUserId = User::query()->value('id');

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($firstUserId, User::query()->value('id'));
        $this->assertSame('owner.admin', User::query()->firstOrFail()->username()->value);
    }

    public function test_it_does_not_create_a_default_account_when_bootstrap_values_are_empty(): void
    {
        Config::set('identity.bootstrap_admin', ['username' => '', 'full_name' => '', 'password' => '']);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}
