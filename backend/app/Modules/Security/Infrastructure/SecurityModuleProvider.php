<?php

declare(strict_types=1);

namespace App\Modules\Security\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use App\Modules\Security\Application\DataProtectionService;
use App\Modules\Security\Application\SensitiveDataProtector;
use App\Modules\Security\Domain\Crypto\AuthenticatedDataCipher;
use App\Modules\Security\Infrastructure\Crypto\OpenSslAuthenticatedDataCipher;
use Illuminate\Contracts\Container\Container;

final class SecurityModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Security;
    }

    public function register(Container $container): void
    {
        $container->bind(AuthenticatedDataCipher::class, OpenSslAuthenticatedDataCipher::class);
        $container->bind(SensitiveDataProtector::class, DataProtectionService::class);
    }
}
