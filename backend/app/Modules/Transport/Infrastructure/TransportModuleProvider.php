<?php

declare(strict_types=1);

namespace App\Modules\Transport\Infrastructure;

use App\Modules\ModuleName;
use App\Modules\ModuleProvider;
use App\Modules\Transport\Domain\Crypto\AuthenticatedEncryptor;
use App\Modules\Transport\Domain\Crypto\RsaOaepKeyCipher;
use App\Modules\Transport\Domain\Crypto\TransportKeyRing;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use App\Modules\Transport\Infrastructure\Crypto\PhpseclibRsaOaepKeyCipher;
use App\Modules\Transport\Infrastructure\Crypto\TransportKeyRingFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

final class TransportModuleProvider implements ModuleProvider
{
    public function module(): ModuleName
    {
        return ModuleName::Transport;
    }

    public function register(Container $container): void
    {
        $container->bind(AuthenticatedEncryptor::class, OpenSslAes256GcmCipher::class);
        $container->bind(RsaOaepKeyCipher::class, PhpseclibRsaOaepKeyCipher::class);
        $container->singleton(TransportKeyRing::class, static function (Container $container): TransportKeyRing {
            $configuration = $container->make(Repository::class);
            $keyConfiguration = $configuration->get('transport.keys');

            if (! is_array($keyConfiguration)) {
                throw new \RuntimeException('Transport key configuration is unavailable.');
            }

            return (new TransportKeyRingFactory)->fromConfiguration($keyConfiguration);
        });
    }
}
