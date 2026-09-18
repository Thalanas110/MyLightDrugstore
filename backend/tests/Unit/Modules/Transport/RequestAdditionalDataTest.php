<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

use App\Modules\Transport\Domain\Crypto\RequestAdditionalData;
use App\Modules\Transport\Domain\Crypto\TransportAuthenticationFailed;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class RequestAdditionalDataTest extends TestCase
{
    public function test_method_is_uppercase_and_query_string_is_omitted(): void
    {
        $this->assertSame(
            'GET /api/v1/medicines',
            RequestAdditionalData::fromRequestTarget('get', '/api/v1/medicines?page=2&search=amox'),
        );
    }

    public function test_full_url_and_relative_path_produce_a_leading_normalized_path(): void
    {
        $this->assertSame(
            'POST /api/v1/sales',
            RequestAdditionalData::fromRequestTarget('post', 'https://pharmacy.example/api/v1/sales?source=pos'),
        );
    }

    public function test_changing_the_path_invalidates_the_authenticated_ciphertext(): void
    {
        $cipher = new OpenSslAes256GcmCipher;
        $key = random_bytes(32);
        $encrypted = $cipher->encrypt(
            $key,
            '{"kind":"json","value":"{}"}',
            RequestAdditionalData::fromRequestTarget('post', '/api/v1/sales'),
        );

        $this->expectException(TransportAuthenticationFailed::class);

        $cipher->decrypt(
            $key,
            $encrypted,
            RequestAdditionalData::fromRequestTarget('post', '/api/v1/reports'),
        );
    }

    #[DataProvider('invalidRequestTargets')]
    public function test_invalid_method_or_request_target_is_rejected(string $method, string $target): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RequestAdditionalData::fromRequestTarget($method, $target);
    }

    public static function invalidRequestTargets(): array
    {
        return [
            'empty method' => ['', '/api/v1/sales'],
            'method with whitespace' => ['POST /OTHER', '/api/v1/sales'],
            'invalid target' => ['GET', "https://example.test\n/path"],
        ];
    }
}
