<?php

declare(strict_types=1);

namespace App\Modules\Transport\Presentation\Middleware;

use App\Modules\Transport\Domain\Crypto\AuthenticatedEncryptor;
use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use App\Modules\Transport\Domain\Crypto\RequestAdditionalData;
use App\Modules\Transport\Domain\Crypto\RsaOaepKeyCipher;
use App\Modules\Transport\Domain\Crypto\TransportAuthenticationFailed;
use App\Modules\Transport\Domain\Crypto\TransportEnvelope;
use App\Modules\Transport\Domain\Crypto\TransportEnvelopeCodec;
use App\Modules\Transport\Domain\Crypto\TransportKeyExchangeFailed;
use App\Modules\Transport\Domain\Crypto\TransportKeyRing;
use App\Modules\Transport\Domain\Crypto\UnknownTransportKeyId;
use App\Modules\Transport\Domain\Payload\JsonTransportPayloadParser;
use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class DecryptTransportRequest
{
    public const SESSION_KEY_ATTRIBUTE = 'transport_session_key';

    public const SESSION_KEY_ID_ATTRIBUTE = 'transport_session_key_id';

    private const array SAFE_LOGICAL_RESPONSE_HEADERS = [
        'cache-control',
        'etag',
        'last-modified',
        'location',
        'retry-after',
        'vary',
    ];

    private const array SAFE_OUTER_RESPONSE_HEADERS = [
        'access-control-allow-credentials',
        'access-control-allow-headers',
        'access-control-allow-methods',
        'access-control-allow-origin',
        'access-control-expose-headers',
        'access-control-max-age',
        'cache-control',
        'content-security-policy',
        'date',
        'permissions-policy',
        'referrer-policy',
        'strict-transport-security',
        'vary',
        'x-content-type-options',
        'x-frame-options',
        'x-request-id',
    ];

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
        if ($request->routeIs('api.v1.transport.public-key')) {
            return $next($request);
        }

        try {
            $transportKeyHeader = $request->headers->get('X-Transport-Key');

            if (! is_string($transportKeyHeader) || substr_count($transportKeyHeader, '.') !== 1) {
                throw new InvalidArgumentException('Encrypted request is invalid.');
            }

            [$keyId, $encodedWrappedKey] = explode('.', $transportKeyHeader, 2);
            $wrappedKey = $this->base64UrlCodec->decode($encodedWrappedKey);
            $keyMaterial = $this->transportKeyRing->forDecryption($keyId);
            $aesKey = $this->rsaOaepKeyCipher->unwrap($keyMaterial->privateKeyPem, $wrappedKey);
            $request->attributes->set(self::SESSION_KEY_ATTRIBUTE, $aesKey);
            $request->attributes->set(self::SESSION_KEY_ID_ATTRIBUTE, $keyId);

            if ($request->getContent() === '') {
                $payload = [];
            } else {
                $envelope = $this->envelopeCodec->decode($request->json()->all());

                if ($keyId === '' || ! hash_equals($keyId, $envelope->keyId)) {
                    throw new InvalidArgumentException('Encrypted request is invalid.');
                }

                $additionalData = RequestAdditionalData::fromRequestTarget($request->method(), $request->getRequestUri());
                $plaintext = $this->authenticatedEncryptor->decrypt($aesKey, $envelope->encrypted, $additionalData);
                $payload = $this->payloadParser->parse($plaintext);
            }
        } catch (InvalidArgumentException|TransportAuthenticationFailed|TransportKeyExchangeFailed|UnknownTransportKeyId) {
            return $this->encryptResponse($request, $this->rejected($request));
        }

        $request->request->replace($payload);
        $request->json()->replace($payload);

        return $this->encryptResponse($request, $next($request));
    }

    private function rejected(Request $request): Response
    {
        $requestId = $request->attributes->get('request_id');
        $error = [
            'code' => 'transport_error',
            'message' => 'The encrypted request could not be processed.',
        ];

        if (is_string($requestId)) {
            $error['requestId'] = $requestId;
        }

        return response()->json(['error' => $error], Response::HTTP_BAD_REQUEST);
    }

    private function encryptResponse(Request $request, Response $response): Response
    {
        $aesKey = $request->attributes->get(self::SESSION_KEY_ATTRIBUTE);
        $keyId = $request->attributes->get(self::SESSION_KEY_ID_ATTRIBUTE);
        $contentType = $response->headers->get('Content-Type');

        if (! is_string($aesKey) || ! is_string($keyId) || ! is_string($contentType)
            || ! str_contains(strtolower($contentType), 'json')
            || in_array($response->getStatusCode(), [Response::HTTP_NO_CONTENT, Response::HTTP_NOT_MODIFIED], true)) {
            return $response;
        }

        $body = $response->getContent();

        if (! is_string($body)) {
            return $response;
        }

        $safeHeaders = $this->safeLogicalHeaders($response);

        $descriptor = json_encode([
            'contentType' => $contentType,
            'headers' => $safeHeaders,
            'body' => $body,
            'bodyEncoding' => 'utf8',
        ], JSON_THROW_ON_ERROR);
        $additionalData = RequestAdditionalData::fromRequestTarget($request->method(), $request->getRequestUri());
        $encrypted = $this->authenticatedEncryptor->encrypt($aesKey, $descriptor, $additionalData);
        $envelope = new TransportEnvelope(1, 'A256GCM', $keyId, $encrypted);
        $encodedEnvelope = json_encode($this->envelopeCodec->encode($envelope), JSON_THROW_ON_ERROR);
        $cookies = $response->headers->getCookies();
        $outerHeaders = [];
        $responseHeaders = $response->headers->all();

        foreach (self::SAFE_OUTER_RESPONSE_HEADERS as $name) {
            if (isset($responseHeaders[$name])) {
                $outerHeaders[$name] = $responseHeaders[$name];
            }
        }

        $response->headers->replace($outerHeaders);

        foreach ($cookies as $cookie) {
            $response->headers->setCookie($cookie);
        }

        $response->setContent($encodedEnvelope);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->remove('Content-Length');
        $response->headers->remove('Content-Encoding');

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function safeLogicalHeaders(Response $response): array
    {
        $headers = $response->headers->all();
        $safeHeaders = [];

        foreach (self::SAFE_LOGICAL_RESPONSE_HEADERS as $name) {
            $values = $headers[$name] ?? null;

            if (is_array($values) && $values !== []) {
                $safeHeaders[$name] = implode(', ', $values);
            }
        }

        return $safeHeaders;
    }
}
