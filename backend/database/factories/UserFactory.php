<?php

namespace Database\Factories;

use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Security\Application\SensitiveDataProtector;
use App\Modules\Security\Application\UsernameLookupDigest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = new Username(fake()->unique()->regexify('[A-Za-z][A-Za-z0-9]{7}'));
        $fullName = new FullName(fake()->name());

        return [
            'username_encrypted' => app(SensitiveDataProtector::class)->encrypt($username->value, 'users.username'),
            'username_lookup_digest' => app(UsernameLookupDigest::class)->digest($username->value),
            'full_name_encrypted' => app(SensitiveDataProtector::class)->encrypt($fullName->value, 'users.full_name'),
            'password' => static::$password ??= Hash::make(Str::random(40)),
            'role' => StaffRole::Staff,
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => StaffRole::Admin]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
