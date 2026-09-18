<?php

declare(strict_types=1);

namespace App\Modules\Security\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use App\Modules\Security\Application\DataProtectionService;
use App\Modules\Security\Application\SensitiveDataProtector;
use App\Modules\Security\Domain\Crypto\AuthenticatedDataCipher;
use App\Modules\Security\Domain\Crypto\DataEncryptionKeyRing;
use App\Modules\Security\Infrastructure\Crypto\DataEncryptionKeyRingFactory;
use App\Modules\Security\Infrastructure\Crypto\OpenSslAuthenticatedDataCipher;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class SecurityModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Security;
    }

    public function register(Container $container): void
    {
        $container->bind(DataEncryptionKeyRing::class, static function (): DataEncryptionKeyRing {
            $configuration = config('data_protection.keys', []);

            if (! is_array($configuration)) {
                throw new InvalidArgumentException('Data encryption keys configuration must be an object.');
            }

            return (new DataEncryptionKeyRingFactory)->fromConfiguration($configuration);
        });
        $container->bind(AuthenticatedDataCipher::class, OpenSslAuthenticatedDataCipher::class);
        $container->bind(SensitiveDataProtector::class, DataProtectionService::class);
    }
}
