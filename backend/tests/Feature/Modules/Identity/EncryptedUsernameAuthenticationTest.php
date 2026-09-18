<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Identity;

use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class EncryptedUsernameAuthenticationTest extends TestCase
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

    public function test_it_authenticates_by_normalized_username_digest(): void
    {
        $user = $this->createUser('Pharmacy.Staff_1', 'CorrectHorseBatteryStaple!2026');

        $authenticated = Auth::attempt([
            'username' => '  PHARMACY.STAFF_1 ',
            'password' => 'CorrectHorseBatteryStaple!2026',
        ]);

        $this->assertTrue($authenticated);
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_it_rejects_inactive_accounts_and_incorrect_passwords(): void
    {
        $this->createUser('inactive.staff', 'CorrectHorseBatteryStaple!2026', active: false);
        $this->createUser('active.staff', 'CorrectHorseBatteryStaple!2026');

        $this->assertFalse(Auth::attempt([
            'username' => 'inactive.staff',
            'password' => 'CorrectHorseBatteryStaple!2026',
        ]));
        $this->assertFalse(Auth::attempt([
            'username' => 'active.staff',
            'password' => 'incorrect-password',
        ]));
    }

    public function test_unknown_or_invalid_usernames_fail_without_querying_plaintext_columns(): void
    {
        $this->assertFalse(Auth::attempt(['username' => 'unknown.staff', 'password' => 'incorrect-password']));
        $this->assertFalse(Auth::attempt(['username' => 'invalid username', 'password' => 'incorrect-password']));
    }

    private function createUser(string $username, string $password, bool $active = true): User
    {
        $user = new User;
        $user->setUsername(new Username($username));
        $user->setFullName(new FullName('Test Staff'));
        $user->password = $password;
        $user->role = StaffRole::Staff;
        $user->active = $active;
        $user->save();

        return $user;
    }
}
