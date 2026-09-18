<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

use App\Modules\Transport\Domain\Crypto\AesGcmCiphertext;
use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use App\Modules\Transport\Domain\Crypto\TransportEnvelope;
use App\Modules\Transport\Domain\Crypto\TransportEnvelopeCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TransportEnvelopeCodecTest extends TestCase
{
    public function test_decode_validates_metadata_and_splits_the_authenticated_tag(): void
    {
        $codec = new Base64UrlCodec;
        $encrypted = random_bytes(23).random_bytes(16);
        $envelope = (new TransportEnvelopeCodec($codec))->decode([
            'version' => 1,
            'algorithm' => 'A256GCM',
            'keyId' => 'transport-2026-01',
            'iv' => $codec->encode(random_bytes(12)),
            'ciphertext' => $codec->encode($encrypted),
        ]);

        $this->assertSame(1, $envelope->version);
        $this->assertSame('A256GCM', $envelope->algorithm);
        $this->assertSame('transport-2026-01', $envelope->keyId);
        $this->assertInstanceOf(AesGcmCiphertext::class, $envelope->encrypted);
        $this->assertSame(12, strlen($envelope->encrypted->nonce));
        $this->assertSame(substr($encrypted, 0, -16), $envelope->encrypted->ciphertext);
        $this->assertSame(substr($encrypted, -16), $envelope->encrypted->tag);
    }

    public function test_encode_builds_the_documented_wire_shape_and_round_trips(): void
    {
        $codec = new Base64UrlCodec;
        $envelopeCodec = new TransportEnvelopeCodec($codec);
        $encrypted = new AesGcmCiphertext(random_bytes(12), 'encrypted bytes', str_repeat('t', 16));
        $envelope = new TransportEnvelope(1, 'A256GCM', 'transport-2026-01', $encrypted);

        $wire = $envelopeCodec->encode($envelope);

        $this->assertSame(['version', 'algorithm', 'keyId', 'iv', 'ciphertext'], array_keys($wire));
        $this->assertSame(1, $wire['version']);
        $this->assertSame('A256GCM', $wire['algorithm']);
        $this->assertSame('transport-2026-01', $wire['keyId']);
        $this->assertSame($encrypted->nonce, $codec->decode($wire['iv']));
        $decoded = $envelopeCodec->decode($wire);
        $this->assertSame($encrypted->ciphertext, $decoded->encrypted->ciphertext);
        $this->assertSame($encrypted->tag, $decoded->encrypted->tag);
    }

    #[DataProvider('invalidEnvelopes')]
    public function test_decode_rejects_invalid_metadata_and_encoding(array $changes): void
    {
        $codec = new Base64UrlCodec;
        $payload = self::validPayload($codec);

        foreach ($changes as $field => $value) {
            if ($value === null) {
                unset($payload[$field]);
            } else {
                $payload[$field] = $value;
            }
        }

        $this->expectException(InvalidArgumentException::class);

        (new TransportEnvelopeCodec($codec))->decode($payload);
    }

    public function test_decode_rejects_ciphertext_larger_than_the_envelope_limit_before_decoding(): void
    {
        $codec = new Base64UrlCodec;
        $payload = self::validPayload($codec);
        $payload['ciphertext'] = str_repeat('A', 1_398_124);

        $this->expectException(InvalidArgumentException::class);

        (new TransportEnvelopeCodec($codec))->decode($payload);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidEnvelopes(): array
    {
        return [
            'unsupported version' => [['version' => 2]],
            'wrong version type' => [['version' => '1']],
            'wrong algorithm' => [['algorithm' => 'A128GCM']],
            'invalid key id' => [['keyId' => 'bad key id']],
            'missing key id' => [['keyId' => null]],
            'invalid nonce encoding' => [['iv' => 'AA==']],
            'wrong nonce length' => [['iv' => 'AA']],
            'invalid ciphertext encoding' => [['ciphertext' => '****']],
            'ciphertext shorter than tag' => [['ciphertext' => (new Base64UrlCodec)->encode(str_repeat('x', 15))]],
            'unknown field' => [['unexpected' => 'value']],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function validPayload(Base64UrlCodec $codec): array
    {
        return [
            'version' => 1,
            'algorithm' => 'A256GCM',
            'keyId' => 'transport-2026-01',
            'iv' => $codec->encode(str_repeat('n', 12)),
            'ciphertext' => $codec->encode(str_repeat('c', 16)),
        ];
    }
}
