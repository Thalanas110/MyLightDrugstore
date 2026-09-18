<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class UserPersistenceTest extends TestCase
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
    }

    public function test_it_encrypts_names_and_hashes_passwords_before_database_storage(): void
    {
        $username = new Username('  Pharmacy.Staff_1 ');
        $fullName = new FullName('Ada María Dimate');
        $plainPassword = 'CorrectHorseBatteryStaple!2026';
        $user = new User;
        $user->setUsername($username);
        $user->setFullName($fullName);
        $user->password = $plainPassword;
        $user->role = StaffRole::Staff;
        $user->active = true;
        $user->save();

        $stored = DB::table('users')->where('id', $user->getKey())->first();

        $this->assertNotNull($stored);
        $this->assertStringStartsWith('v1:test-data-2026-01:', $stored->username_encrypted);
        $this->assertStringStartsWith('v1:test-data-2026-01:', $stored->full_name_encrypted);
        $this->assertStringNotContainsString($username->value, $stored->username_encrypted);
        $this->assertStringNotContainsString($fullName->value, $stored->full_name_encrypted);
        $this->assertNotSame($plainPassword, $stored->password);
        $this->assertSame('argon2id', password_get_info($stored->password)['algoName']);
        $this->assertSame(64, strlen($stored->username_lookup_digest));
        $this->assertSame($username->value, $user->username()->value);
        $this->assertSame($fullName->value, $user->fullName()->value);
        $this->assertSame(StaffRole::Staff, $user->role);
        $this->assertTrue($user->active);
        $this->assertArrayNotHasKey('username_encrypted', $user->toArray());
        $this->assertArrayNotHasKey('full_name_encrypted', $user->toArray());
        $this->assertArrayNotHasKey('username_lookup_digest', $user->toArray());
        $this->assertArrayNotHasKey('password', $user->toArray());
    }
}
