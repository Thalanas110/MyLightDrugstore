<?php

declare(strict_types=1);

namespace App\Modules\Transport\Presentation\Middleware;

use App\Modules\Transport\Domain\Crypto\AuthenticatedEncryptor;
use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use App\Modules\Transport\Domain\Crypto\RequestAdditionalData;
use App\Modules\Transport\Domain\Crypto\RsaOaepKeyCipher;
use App\Modules\Transport\Domain\Crypto\TransportEnvelopeCodec;
use App\Modules\Transport\Domain\Crypto\TransportKeyRing;
use App\Modules\Transport\Domain\Payload\JsonTransportPayloadParser;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class DecryptTransportRequest
{
    public function __construct(
        private TransportKeyRing $transportKeyRing,
        private RsaOaepKeyCipher $rsaOaepKeyCipher,
        private AuthenticatedEncryptor $authenticatedEncryptor,
        private TransportEnvelopeCodec $envelopeCodec,
        private Base64UrlCodec $base64UrlCodec,
        private JsonTransportPayloadParser $payloadParser,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $transportKeyHeader = $request->headers->get('X-Transport-Key');

        if (! is_string($transportKeyHeader) || substr_count($transportKeyHeader, '.') !== 1) {
            throw new InvalidArgumentException('Encrypted request is invalid.');
        }

        [$keyId, $encodedWrappedKey] = explode('.', $transportKeyHeader, 2);

        try {
            $wrappedKey = $this->base64UrlCodec->decode($encodedWrappedKey);
            $envelope = $this->envelopeCodec->decode($request->json()->all());

            if ($keyId === '' || ! hash_equals($keyId, $envelope->keyId)) {
                throw new InvalidArgumentException('Encrypted request is invalid.');
            }

            $keyMaterial = $this->transportKeyRing->forDecryption($keyId);
            $aesKey = $this->rsaOaepKeyCipher->unwrap($keyMaterial->privateKeyPem, $wrappedKey);
            $additionalData = RequestAdditionalData::fromRequestTarget($request->method(), $request->getRequestUri());
            $plaintext = $this->authenticatedEncryptor->decrypt($aesKey, $envelope->encrypted, $additionalData);
            $payload = $this->payloadParser->parse($plaintext);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('Encrypted request is invalid.', previous: $exception);
        }

        $request->request->replace($payload);
        $request->json()->replace($payload);

        return $next($request);
    }
}
