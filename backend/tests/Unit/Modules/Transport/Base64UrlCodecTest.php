<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

use App\Modules\Transport\Domain\Crypto\Base64UrlCodec;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class Base64UrlCodecTest extends TestCase
{
    public function test_encode_returns_canonical_unpadded_base64url(): void
    {
        $codec = new Base64UrlCodec;

        $this->assertSame('Zm9v', $codec->encode('foo'));
        $this->assertSame('-_8', $codec->encode("\xfb\xff"));
        $this->assertSame('', $codec->encode(''));
    }

    public function test_decode_round_trips_arbitrary_binary_bytes(): void
    {
        $codec = new Base64UrlCodec;
        $binary = "\x00\xFF\x10\x80\xC3\x28\x00";

        $this->assertSame($binary, $codec->decode($codec->encode($binary)));
    }

    #[DataProvider('invalidEncodedValues')]
    public function test_decode_rejects_padded_illegal_impossible_and_noncanonical_values(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Base64UrlCodec)->decode($value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidEncodedValues(): array
    {
        return [
            'padding' => ['Zg=='],
            'standard plus character' => ['+w'],
            'standard slash character' => ['/w'],
            'whitespace' => ['Zm 8'],
            'impossible length' => ['A'],
            'nonzero trailing bits' => ['Zh'],
        ];
    }
}
