<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Authentication;

use App\Modules\Identity\Domain\Users\Username;
use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Security\Application\UsernameLookupDigest;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use InvalidArgumentException;
use SensitiveParameter;

final class EncryptedUsernameUserProvider extends EloquentUserProvider
{
    public function __construct(
        Hasher $hasher,
        string $model,
        private readonly UsernameLookupDigest $usernameLookupDigest,
    ) {
        parent::__construct($hasher, $model);
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials): ?User
    {
        $usernameValue = $credentials['username'] ?? null;

        if (! is_string($usernameValue)) {
            return null;
        }

        try {
            $username = new Username($usernameValue);
        } catch (InvalidArgumentException) {
            return null;
        }

        $digest = $this->usernameLookupDigest->digest($username->value);

        return User::query()
            ->where('username_lookup_digest', $digest)
            ->where('active', true)
            ->first();
    }
}
