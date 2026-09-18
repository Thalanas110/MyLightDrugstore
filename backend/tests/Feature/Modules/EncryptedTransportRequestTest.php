<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use App\Modules\Transport\Domain\Crypto\RequestAdditionalData;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use App\Modules\Transport\Infrastructure\Crypto\PhpseclibRsaOaepKeyCipher;
use App\Modules\Transport\Presentation\Middleware\DecryptTransportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class EncryptedTransportRequestTest extends TestCase
{
    public function test_real_rsa_wrapped_aes_gcm_request_is_available_as_the_logical_laravel_body(): void
    {
        $fixture = require dirname(__DIR__, 2).'/Fixtures/Transport/rsa_oaep_webcrypto.php';
        $publicKeyPem = self::pem('PUBLIC KEY', $fixture['publicKeyBase64']);
        $privateKeyPem = self::pem('PRIVATE KEY', $fixture['privateKeyBase64']);
        config()->set('transport.keys', [
            'current' => [
                'key_id' => 'transport-current',
                'public_key_base64' => base64_encode($publicKeyPem),
                'private_key_base64' => base64_encode($privateKeyPem),
            ],
            'retiring' => [],
        ]);

        Route::post('/api/v1/transport/test-echo', static function (Request $request): JsonResponse {
            return response()->json(['data' => $request->all()]);
        })->middleware(DecryptTransportRequest::class);

        $payload = ['medicineId' => 42, 'note' => 'test request'];
        $aesKey = random_bytes(32);
        $descriptor = json_encode([
            'kind' => 'json',
            'contentType' => 'application/json',
            'value' => json_encode($payload, JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
        $aad = RequestAdditionalData::fromRequestTarget('POST', '/api/v1/transport/test-echo');
        $encrypted = (new OpenSslAes256GcmCipher)->encrypt($aesKey, $descriptor, $aad);
        $codec = new Base64UrlCodec;
        $wrappedKey = (new PhpseclibRsaOaepKeyCipher)->wrap($publicKeyPem, $aesKey);

        $envelope = json_encode([
            'version' => 1,
            'algorithm' => 'A256GCM',
            'keyId' => 'transport-current',
            'iv' => $codec->encode($encrypted->nonce),
            'ciphertext' => $codec->encode($encrypted->ciphertext.$encrypted->tag),
        ], JSON_THROW_ON_ERROR);

        $response = $this->call('POST', '/api/v1/transport/test-echo', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TRANSPORT_KEY' => 'transport-current.'.$codec->encode($wrappedKey),
        ], $envelope)->assertOk();

        $descriptor = (new EncryptedTransportResponseReader)->read(
            $response,
            $aesKey,
            'POST',
            '/api/v1/transport/test-echo',
        );
        $this->assertSame('{"data":{"medicineId":42,"note":"test request"}}', $descriptor['body']);
    }

    private static function pem(string $type, string $encodedKey): string
    {
        return "-----BEGIN {$type}-----\n".chunk_split($encodedKey, 64, "\n")."-----END {$type}-----\n";
    }
}
