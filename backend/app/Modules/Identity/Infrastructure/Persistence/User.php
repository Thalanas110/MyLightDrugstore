<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Persistence;

use App\Modules\Identity\Domain\Users\FullName;
use App\Modules\Identity\Domain\Users\StaffRole;
use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Security\Application\SensitiveDataProtector;
use App\Modules\Security\Application\UsernameLookupDigest;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use LogicException;

#[Fillable(['password', 'role', 'active'])]
#[Hidden(['username_encrypted', 'username_lookup_digest', 'full_name_encrypted', 'password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => StaffRole::class,
            'active' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function setUsername(Username $username): void
    {
        $this->attributes['username_encrypted'] = app(SensitiveDataProtector::class)
            ->encrypt($username->value, 'users.username');
        $this->attributes['username_lookup_digest'] = app(UsernameLookupDigest::class)
            ->digest($username->value);
    }

    public function username(): Username
    {
        $encrypted = $this->attributes['username_encrypted'] ?? null;

        if (! is_string($encrypted)) {
            throw new LogicException('The user record has no encrypted username.');
        }

        $plaintext = app(SensitiveDataProtector::class)->decrypt($encrypted, 'users.username');

        return new Username($plaintext);
    }

    public function setFullName(FullName $fullName): void
    {
        $this->attributes['full_name_encrypted'] = app(SensitiveDataProtector::class)
            ->encrypt($fullName->value, 'users.full_name');
    }

    public function fullName(): FullName
    {
        $encrypted = $this->attributes['full_name_encrypted'] ?? null;

        if (! is_string($encrypted)) {
            throw new LogicException('The user record has no encrypted full name.');
        }

        $plaintext = app(SensitiveDataProtector::class)->decrypt($encrypted, 'users.full_name');

        return new FullName($plaintext);
    }

    public function staffRole(): StaffRole
    {
        $role = $this->getAttribute('role');

        if (! $role instanceof StaffRole) {
            throw new LogicException('The user record has an invalid role.');
        }

        return $role;
    }

    public function isActive(): bool
    {
        return $this->getAttribute('active') === true;
    }
}
