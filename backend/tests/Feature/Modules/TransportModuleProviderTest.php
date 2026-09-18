<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\Transport\Domain\Crypto\AuthenticatedEncryptor;
use App\Modules\Transport\Domain\Crypto\RsaOaepKeyCipher;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use App\Modules\Transport\Infrastructure\Crypto\PhpseclibRsaOaepKeyCipher;
use Tests\TestCase;

final class TransportModuleProviderTest extends TestCase
{
    public function test_container_resolves_the_openssl_authenticated_encryptor_adapter(): void
    {
        $this->assertInstanceOf(OpenSslAes256GcmCipher::class, app(AuthenticatedEncryptor::class));
    }

    public function test_container_resolves_the_phpseclib_rsa_oaep_key_cipher(): void
    {
        $this->assertInstanceOf(PhpseclibRsaOaepKeyCipher::class, app(RsaOaepKeyCipher::class));
    }
}
