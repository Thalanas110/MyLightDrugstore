<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use App\Modules\Transport\Domain\Crypto\RequestAdditionalData;
use App\Modules\Transport\Domain\Crypto\TransportEnvelopeCodec;
use App\Modules\Transport\Infrastructure\Crypto\OpenSslAes256GcmCipher;
use Illuminate\Testing\TestResponse;
use RuntimeException;

final class EncryptedTransportResponseReader
{
    /**
     * @return array{contentType: string, headers: array<string, string>, body: string, bodyEncoding: string}
     */
    public function read(TestResponse $response, string $aesKey, string $method, string $path): array
    {
        $wireEnvelope = $response->json();

        if (! is_array($wireEnvelope)) {
            throw new RuntimeException('The encrypted test response is invalid.');
        }

        $envelopePayload = [];

        foreach ($wireEnvelope as $key => $value) {
            if (! is_string($key)) {
                throw new RuntimeException('The encrypted test response is invalid.');
            }

            $envelopePayload[$key] = $value;
        }

        $envelope = (new TransportEnvelopeCodec(new Base64UrlCodec))->decode($envelopePayload);
        $plaintext = (new OpenSslAes256GcmCipher)->decrypt(
            $aesKey,
            $envelope->encrypted,
            RequestAdditionalData::fromRequestTarget($method, $path),
        );
        $descriptor = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($descriptor)
            || ! is_string($descriptor['contentType'] ?? null)
            || ! is_array($descriptor['headers'] ?? null)
            || ! is_string($descriptor['body'] ?? null)
            || ! is_string($descriptor['bodyEncoding'] ?? null)) {
            throw new RuntimeException('The decrypted test response is invalid.');
        }

        return [
            'contentType' => $descriptor['contentType'],
            'headers' => $descriptor['headers'],
            'body' => $descriptor['body'],
            'bodyEncoding' => $descriptor['bodyEncoding'],
        ];
    }
}
