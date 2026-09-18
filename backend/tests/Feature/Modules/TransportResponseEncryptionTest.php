<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use App\Modules\Transport\Domain\Crypto\RequestAdditionalData;
use App\Modules\Transport\Domain\Crypto\TransportEnvelopeCodec;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\Support\EncryptedTransportResponseReader;
use Tests\TestCase;

final class TransportResponseEncryptionTest extends TestCase
{
    public function test_json_success_responses_are_encrypted_without_changing_the_http_status(): void
    {
        $path = '/api/v1/_transport-test/response-success';
        Route::middleware('api')->post($path, static fn (): JsonResponse => response()->json([
            'data' => ['message' => 'sensitive success body'],
        ]));
        $request = (new EncryptedTransportRequestBuilder)->build('POST', $path, ['probe' => true]);

        $response = $this->send($path, $request);

        $response->assertOk()->assertHeader('Content-Type', 'application/json');
        $this->assertStringNotContainsString('sensitive success body', $response->getContent());
        $this->assertSame([
            'contentType' => 'application/json',
            'headers' => ['cache-control' => 'no-cache, private'],
            'body' => '{"data":{"message":"sensitive success body"}}',
            'bodyEncoding' => 'utf8',
        ], $this->decryptResponse($response->json(), $request['aesKey'], 'POST', $path));
    }

    public function test_json_error_responses_keep_their_http_status_and_are_encrypted(): void
    {
        $path = '/api/v1/_transport-test/response-error';
        Route::middleware('api')->post($path, static fn (): JsonResponse => response()->json([
            'error' => ['code' => 'validation_failed', 'message' => 'The request is invalid.'],
        ], 422));
        $request = (new EncryptedTransportRequestBuilder)->build('POST', $path, ['probe' => true]);

        $response = $this->send($path, $request);

        $response->assertUnprocessable();
        $this->assertSame(
            '{"error":{"code":"validation_failed","message":"The request is invalid."}}',
            $this->decryptResponse($response->json(), $request['aesKey'], 'POST', $path)['body'],
        );
    }

    public function test_validation_exceptions_are_rendered_and_encrypted_after_the_route(): void
    {
        $path = '/api/v1/_transport-test/response-validation-exception';
        Route::middleware('api')->post($path, static function (): never {
            throw ValidationException::withMessages(['medicineId' => ['The medicine does not exist.']]);
        });
        $request = (new EncryptedTransportRequestBuilder)->build('POST', $path, ['medicineId' => 999]);

        $response = $this->send($path, $request);

        $response->assertUnprocessable();
        $descriptor = (new EncryptedTransportResponseReader)->read($response, $request['aesKey'], 'POST', $path);
        $this->assertSame(
            'validation_failed',
            json_decode($descriptor['body'], true, 512, JSON_THROW_ON_ERROR)['error']['code'],
        );
    }

    public function test_safe_logical_headers_are_encrypted_and_cookies_remain_http_metadata(): void
    {
        $path = '/api/v1/_transport-test/response-headers';
        Route::middleware('api')->post($path, static function (): JsonResponse {
            $response = response()->json(['data' => ['created' => true]], 201);
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Location', '/api/v1/medicines/42');
            $response->headers->set('X-Internal-Secret', 'do-not-expose');
            $response->headers->setCookie(Cookie::create('pharmacy_session', 'session-secret')->withHttpOnly());

            return $response;
        });
        $request = (new EncryptedTransportRequestBuilder)->build('POST', $path, ['probe' => true]);

        $response = $this->send($path, $request);

        $response->assertCreated()->assertHeader('X-Request-ID');
        $cookies = $response->headers->getCookies();
        $this->assertCount(1, $cookies);
        $this->assertSame('pharmacy_session', $cookies[0]->getName());
        $this->assertSame('session-secret', $cookies[0]->getValue());
        $this->assertFalse($response->headers->has('X-Internal-Secret'));
        $this->assertFalse($response->headers->has('Location'));
        $descriptor = (new EncryptedTransportResponseReader)->read($response, $request['aesKey'], 'POST', $path);
        $this->assertSame([
            'cache-control' => 'no-store, private',
            'location' => '/api/v1/medicines/42',
        ], $descriptor['headers']);
        $this->assertArrayNotHasKey('x-internal-secret', $descriptor['headers']);
    }

    /**
     * @param  array{header: string, aesKey: string, envelope: array{version: int, algorithm: string, keyId: string, iv: string, ciphertext: string}}  $request
     */
    private function send(string $path, array $request): TestResponse
    {
        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TRANSPORT_KEY' => $request['header'],
        ], json_encode($request['envelope'], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @return array<string, mixed>
     */
    private function decryptResponse(array $envelope, string $aesKey, string $method, string $path): array
    {
        $decodedEnvelope = (new TransportEnvelopeCodec(new Base64UrlCodec))->decode($envelope);
        $plaintext = (new OpenSslAes256GcmCipher)->decrypt(
            $aesKey,
            $decodedEnvelope->encrypted,
            RequestAdditionalData::fromRequestTarget($method, $path),
        );
        $descriptor = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($descriptor);

        return $descriptor;
    }
}
