<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure;

use App\Modules\Identity\Application\AuthenticationSession;
use App\Modules\Identity\Infrastructure\Authentication\EncryptedUsernameUserProvider;
use App\Modules\Identity\Infrastructure\Authentication\LaravelAuthenticationSession;
use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use App\Modules\Security\Application\UsernameLookupDigest;
use Illuminate\Contracts\Auth\UserProvider as UserProviderContract;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class IdentityModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Identity;
    }

    public function register(Container $container): void
    {
        $container->bind(AuthenticationSession::class, LaravelAuthenticationSession::class);

        Auth::provider('encrypted-username', static function (Application $application, array $configuration): UserProviderContract {
            $model = $configuration['model'] ?? null;

            if (! is_string($model)) {
                throw new LogicException('The authentication user model must be configured.');
            }

            return new EncryptedUsernameUserProvider(
                $application->make(Hasher::class),
                $model,
                $application->make(UsernameLookupDigest::class),
            );
        });
    }
}
