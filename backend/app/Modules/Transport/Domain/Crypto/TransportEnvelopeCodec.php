<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

use InvalidArgumentException;

final class TransportEnvelopeCodec
{
    private const int VERSION = 1;

    private const string ALGORITHM = 'A256GCM';

    private const int NONCE_LENGTH = 12;

    private const int TAG_LENGTH = 16;

    private const int MAX_CIPHERTEXT_LENGTH = 1_048_576;

    private const int MAX_ENCODED_CIPHERTEXT_LENGTH = 1_398_102;

    public function __construct(private Base64UrlCodec $base64UrlCodec) {}

    /**
     * @return array{version: int, algorithm: string, keyId: string, iv: string, ciphertext: string}
     */
    public function encode(TransportEnvelope $envelope): array
    {
        $ciphertextAndTag = $envelope->encrypted->ciphertext.$envelope->encrypted->tag;

        if ($envelope->version !== self::VERSION || $envelope->algorithm !== self::ALGORITHM
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $envelope->keyId) !== 1
            || strlen($envelope->encrypted->nonce) !== self::NONCE_LENGTH
            || strlen($envelope->encrypted->tag) !== self::TAG_LENGTH
            || strlen($ciphertextAndTag) > self::MAX_CIPHERTEXT_LENGTH) {
            throw self::invalidEnvelope();
        }

        return [
            'version' => $envelope->version,
            'algorithm' => $envelope->algorithm,
            'keyId' => $envelope->keyId,
            'iv' => $this->base64UrlCodec->encode($envelope->encrypted->nonce),
            'ciphertext' => $this->base64UrlCodec->encode($ciphertextAndTag),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function decode(array $payload): TransportEnvelope
    {
        $expectedFields = ['version', 'algorithm', 'keyId', 'iv', 'ciphertext'];

        if (count($payload) !== count($expectedFields) || array_diff(array_keys($payload), $expectedFields) !== []) {
            throw self::invalidEnvelope();
        }

        if (($payload['version'] ?? null) !== self::VERSION || ($payload['algorithm'] ?? null) !== self::ALGORITHM) {
            throw self::invalidEnvelope();
        }

        $keyId = $payload['keyId'] ?? null;
        $encodedNonce = $payload['iv'] ?? null;
        $encodedCiphertext = $payload['ciphertext'] ?? null;

        if (! is_string($keyId) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/', $keyId) !== 1
            || ! is_string($encodedNonce) || ! is_string($encodedCiphertext)
            || strlen($encodedCiphertext) > self::MAX_ENCODED_CIPHERTEXT_LENGTH) {
            throw self::invalidEnvelope();
        }

        try {
            $nonce = $this->base64UrlCodec->decode($encodedNonce);
            $ciphertextAndTag = $this->base64UrlCodec->decode($encodedCiphertext);
        } catch (InvalidArgumentException) {
            throw self::invalidEnvelope();
        }

        if (strlen($nonce) !== self::NONCE_LENGTH
            || strlen($ciphertextAndTag) < self::TAG_LENGTH
            || strlen($ciphertextAndTag) > self::MAX_CIPHERTEXT_LENGTH) {
            throw self::invalidEnvelope();
        }

        $encrypted = new AesGcmCiphertext(
            $nonce,
            substr($ciphertextAndTag, 0, -self::TAG_LENGTH),
            substr($ciphertextAndTag, -self::TAG_LENGTH),
        );

        return new TransportEnvelope(self::VERSION, self::ALGORITHM, $keyId, $encrypted);
    }

    private static function invalidEnvelope(): InvalidArgumentException
    {
        return new InvalidArgumentException('The transport envelope is invalid.');
    }
}
