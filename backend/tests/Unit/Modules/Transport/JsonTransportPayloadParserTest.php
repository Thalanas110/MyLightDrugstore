<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Transport;

use App\Modules\Transport\Domain\Payload\JsonTransportPayloadParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class JsonTransportPayloadParserTest extends TestCase
{
    public function test_parse_returns_the_logical_json_object_from_the_transport_descriptor(): void
    {
        $payload = (new JsonTransportPayloadParser)->parse(
            '{"kind":"json","contentType":"application/json","value":"{\"medicineId\":42,\"note\":\"café\"}"}',
        );

        $this->assertSame(['medicineId' => 42, 'note' => 'café'], $payload);
    }

    public function test_parse_rejects_a_valid_json_descriptor_that_exceeds_the_size_limit(): void
    {
        $descriptor = json_encode([
            'kind' => 'json',
            'contentType' => 'application/json',
            'value' => json_encode(['payload' => str_repeat('x', 524_289)], JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transport request descriptor is invalid.');

        (new JsonTransportPayloadParser)->parse($descriptor);
    }

    #[DataProvider('invalidDescriptors')]
    public function test_parse_rejects_malformed_or_unsupported_descriptors(string $descriptor): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transport request descriptor is invalid.');

        (new JsonTransportPayloadParser)->parse($descriptor);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidDescriptors(): array
    {
        return [
            'malformed descriptor JSON' => ['{not json'],
            'descriptor must be an object' => ['[]'],
            'unsupported kind' => ['{"kind":"binary","contentType":"application/json","value":"{}"}'],
            'wrong content type' => ['{"kind":"json","contentType":"text/plain","value":"{}"}'],
            'missing value' => ['{"kind":"json","contentType":"application/json"}'],
            'unknown field' => ['{"kind":"json","contentType":"application/json","value":"{}","encoding":"utf8"}'],
            'invalid nested JSON' => ['{"kind":"json","contentType":"application/json","value":"{bad}"}'],
            'nested value must be an object' => ['{"kind":"json","contentType":"application/json","value":"[1,2]"}'],
            'top-level object keys must be strings' => ['{"kind":"json","contentType":"application/json","value":"{\"0\":\"value\"}"}'],
        ];
    }
}
