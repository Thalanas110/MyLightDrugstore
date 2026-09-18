<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\EncryptedTransportRequestBuilder;
use Tests\TestCase;

final class TransportRequestRejectionTest extends TestCase
{
    public function test_invalid_transport_inputs_return_one_generic_error_before_controller_execution(): void
    {
        $controllerCalls = 0;
        Route::middleware('api')->post('/api/v1/_transport-test/rejection', static function (Request $request) use (&$controllerCalls): JsonResponse {
            $controllerCalls++;

            return response()->json(['data' => $request->all()]);
        });

        $builder = new EncryptedTransportRequestBuilder;
        $path = '/api/v1/_transport-test/rejection';
        $valid = $builder->build('POST', $path, ['sensitive' => 'never returned']);
        $requests = [];

        $missingHeader = $valid;
        $missingHeader['header'] = '';
        $requests['missing header'] = $missingHeader;

        $malformedHeader = $valid;
        $malformedHeader['header'] = 'not-a-valid-header';
        $requests['malformed header'] = $malformedHeader;

        $mismatchedKeyId = $valid;
        $mismatchedKeyId['header'] = 'another-key.'.substr($valid['header'], strpos($valid['header'], '.') + 1);
        $requests['mismatched key ID'] = $mismatchedKeyId;

        $invalidWrappedKey = $valid;
        $invalidWrappedKey['header'] = EncryptedTransportRequestBuilder::KEY_ID.'.'.(new Base64UrlCodec)->encode('invalid');
        $requests['invalid wrapped key'] = $invalidWrappedKey;

        $invalidTag = $valid;
        $ciphertext = (new Base64UrlCodec)->decode($valid['envelope']['ciphertext']);
        $ciphertext[strlen($ciphertext) - 1] = chr(ord($ciphertext[strlen($ciphertext) - 1]) ^ 1);
        $invalidTag['envelope']['ciphertext'] = (new Base64UrlCodec)->encode($ciphertext);
        $requests['invalid tag'] = $invalidTag;

        $wrongAad = $builder->build('POST', $path, ['sensitive' => 'never returned'], '/api/v1/_transport-test/other');
        $requests['wrong AAD'] = $wrongAad;

        foreach ($requests as $case => $request) {
            $response = $this->sendEncrypted($path, $request['header'], $request['envelope']);
            $response->assertStatus(400)
                ->assertJsonPath('error.code', 'transport_error')
                ->assertJsonPath('error.message', 'The encrypted request could not be processed.');

            $requestId = $response->json('error.requestId');
            $this->assertIsString($requestId, $case);
            $this->assertSame($requestId, $response->headers->get('X-Request-ID'), $case);
            $this->assertSame(['code', 'message', 'requestId'], array_keys($response->json('error')), $case);
        }

        $this->assertSame(0, $controllerCalls);
    }

    /**
     * @param  array{version: int, algorithm: string, keyId: string, iv: string, ciphertext: string}  $envelope
     */
    private function sendEncrypted(string $path, string $header, array $envelope): TestResponse
    {
        return $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TRANSPORT_KEY' => $header,
        ], json_encode($envelope, JSON_THROW_ON_ERROR));
    }
}
