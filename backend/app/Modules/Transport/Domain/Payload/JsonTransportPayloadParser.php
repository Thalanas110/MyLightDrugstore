<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Payload;

use InvalidArgumentException;
use JsonException;
use stdClass;

final class JsonTransportPayloadParser
{
    private const int MAX_DESCRIPTOR_BYTES = 524_288;

    /**
     * @return array<string, mixed>
     */
    public function parse(string $plaintext): array
    {
        if (strlen($plaintext) > self::MAX_DESCRIPTOR_BYTES) {
            throw self::invalidDescriptor();
        }

        try {
            $descriptor = json_decode($plaintext, flags: JSON_THROW_ON_ERROR);

            if (! $descriptor instanceof stdClass) {
                throw self::invalidDescriptor();
            }

            $fields = get_object_vars($descriptor);
            $expectedFields = ['kind', 'contentType', 'value'];

            if (count($fields) !== count($expectedFields) || array_diff(array_keys($fields), $expectedFields) !== []) {
                throw self::invalidDescriptor();
            }

            if (($fields['kind'] ?? null) !== 'json' || ($fields['contentType'] ?? null) !== 'application/json'
                || ! is_string($fields['value'] ?? null)) {
                throw self::invalidDescriptor();
            }

            $jsonObject = json_decode($fields['value'], flags: JSON_THROW_ON_ERROR);

            if (! $jsonObject instanceof stdClass) {
                throw self::invalidDescriptor();
            }

            $payload = json_decode($fields['value'], true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($payload)) {
                throw self::invalidDescriptor();
            }

            $logicalPayload = [];

            foreach ($payload as $key => $value) {
                if (! is_string($key)) {
                    throw self::invalidDescriptor();
                }

                $logicalPayload[$key] = $value;
            }

            return $logicalPayload;
        } catch (JsonException) {
            throw self::invalidDescriptor();
        }
    }

    private static function invalidDescriptor(): InvalidArgumentException
    {
        return new InvalidArgumentException('Transport request descriptor is invalid.');
    }
}
