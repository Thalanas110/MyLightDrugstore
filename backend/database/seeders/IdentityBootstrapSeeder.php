<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Security\Application\UsernameLookupDigest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

final class IdentityBootstrapSeeder extends Seeder
{
    public function run(): void
    {
        $configuration = config('identity.bootstrap_admin', []);

        if (! is_array($configuration)) {
            throw new LogicException('Bootstrap administrator configuration must be an object.');
        }

        $usernameValue = $configuration['username'] ?? '';
        $fullNameValue = $configuration['full_name'] ?? '';
        $password = $configuration['password'] ?? '';

        if ($usernameValue === '' && $fullNameValue === '' && $password === '') {
            return;
        }

        if (! is_string($usernameValue) || ! is_string($fullNameValue) || ! is_string($password)) {
            throw new LogicException('All bootstrap administrator values must be strings.');
        }

        if ($usernameValue === '' || $fullNameValue === '' || mb_strlen($password, 'UTF-8') < 12) {
            throw new LogicException('Configure a username, full name, and a bootstrap password of at least 12 characters.');
        }

        $username = new Username($usernameValue);
        $fullName = new FullName($fullNameValue);
        $usernameDigest = app(UsernameLookupDigest::class)->digest($username->value);

        if (DB::table('users')->where('username_lookup_digest', $usernameDigest)->exists()) {
            return;
        }

        $user = new User;
        $user->setUsername($username);
        $user->setFullName($fullName);
        $user->password = $password;
        $user->role = StaffRole::Admin;
        $user->active = true;
        $user->save();
    }
}
