<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use App\Modules\Transport\Domain\Crypto\RequestAdditionalData;
use App\Modules\Transport\Domain\Crypto\TransportAuthenticationFailed;
use App\Modules\Transport\Domain\Crypto\TransportEnvelopeCodec;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use App\Modules\Transport\Infrastructure\Crypto\PhpseclibRsaOaepKeyCipher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class TransportEndToEndTest extends TestCase
{
    public function test_public_key_bootstrap_request_response_and_response_tampering(): void
    {
        $fixture = require dirname(__DIR__, 2).'/Fixtures/Transport/rsa_oaep_webcrypto.php';
        $publicKeyPem = self::pem('PUBLIC KEY', $fixture['publicKeyBase64']);
        $privateKeyPem = self::pem('PRIVATE KEY', $fixture['privateKeyBase64']);
        config()->set('transport.keys', [
            'current' => [
                'key_id' => 'transport-e2e',
                'public_key_base64' => base64_encode($publicKeyPem),
                'private_key_base64' => base64_encode($privateKeyPem),
            ],
            'retiring' => [],
        ]);

        $publicKeyResponse = $this->getJson('/api/v1/transport/public-key')->assertOk();
        $this->assertSame('RSA-OAEP-256', $publicKeyResponse->json('data.algorithm'));
        $this->assertSame('transport-e2e', $publicKeyResponse->json('data.keyId'));
        $clientPublicKeyPem = $publicKeyResponse->json('data.publicKey');
        $this->assertIsString($clientPublicKeyPem);

        $path = '/api/v1/_transport-test/end-to-end';
        Route::middleware('api')->post($path, static function (Request $request): JsonResponse {
            $response = response()->json(['data' => ['medicineId' => $request->input('medicineId')]], 201);
            $response->headers->set('Location', '/api/v1/medicines/42');

            return $response;
        });

        $logicalRequest = ['medicineId' => 42];
        $aesKey = random_bytes(32);
        $descriptor = json_encode([
            'kind' => 'json',
            'contentType' => 'application/json',
            'value' => json_encode($logicalRequest, JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);
        $additionalData = RequestAdditionalData::fromRequestTarget('POST', $path);
        $cipher = new OpenSslAes256GcmCipher;
        $base64Url = new Base64UrlCodec;
        $envelopeCodec = new TransportEnvelopeCodec($base64Url);
        $encryptedRequest = $cipher->encrypt($aesKey, $descriptor, $additionalData);
        $wrappedKey = (new PhpseclibRsaOaepKeyCipher)->wrap($clientPublicKeyPem, $aesKey);
        $wireRequest = [
            'version' => 1,
            'algorithm' => 'A256GCM',
            'keyId' => 'transport-e2e',
            'iv' => $base64Url->encode($encryptedRequest->nonce),
            'ciphertext' => $base64Url->encode($encryptedRequest->ciphertext.$encryptedRequest->tag),
        ];

        $response = $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_REQUEST_ID' => 'req_transport-e2e',
            'HTTP_X_TRANSPORT_KEY' => 'transport-e2e.'.$base64Url->encode($wrappedKey),
        ], json_encode($wireRequest, JSON_THROW_ON_ERROR));

        $response->assertCreated()->assertHeader('X-Request-ID', 'req_transport-e2e');
        $wireResponse = self::stringKeyArray($response->json());
        $this->assertSame('transport-e2e', $wireResponse['keyId']);
        $decodedResponse = $envelopeCodec->decode($wireResponse);
        $responsePlaintext = $cipher->decrypt($aesKey, $decodedResponse->encrypted, $additionalData);
        $responseDescriptor = json_decode($responsePlaintext, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('application/json', $responseDescriptor['contentType'] ?? null);
        $this->assertSame('/api/v1/medicines/42', $responseDescriptor['headers']['location'] ?? null);
        $this->assertSame('{"data":{"medicineId":42}}', $responseDescriptor['body'] ?? null);
        $this->assertSame('utf8', $responseDescriptor['bodyEncoding'] ?? null);

        $combinedCiphertext = $base64Url->decode($wireResponse['ciphertext']);
        $combinedCiphertext[0] = chr(ord($combinedCiphertext[0]) ^ 1);
        $tamperedResponse = $wireResponse;
        $tamperedResponse['ciphertext'] = $base64Url->encode($combinedCiphertext);
        $tamperedEnvelope = $envelopeCodec->decode($tamperedResponse);

        $this->expectException(TransportAuthenticationFailed::class);

        $cipher->decrypt($aesKey, $tamperedEnvelope->encrypted, $additionalData);
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringKeyArray(mixed $value): array
    {
        if (! is_array($value)) {
            throw new \RuntimeException('The transport envelope must be an object.');
        }

        $payload = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new \RuntimeException('The transport envelope contains an invalid field.');
            }

            $payload[$key] = $item;
        }

        return $payload;
    }

    private static function pem(string $type, string $encodedKey): string
    {
        return "-----BEGIN {$type}-----\n".chunk_split($encodedKey, 64, "\n")."-----END {$type}-----\n";
    }
}
