<?php

declare(strict_types=1);

namespace App\Modules\Security\Infrastructure\Crypto;

use App\Modules\Security\Domain\Crypto\DataEncryptionKey;
use App\Modules\Security\Domain\Crypto\DataEncryptionKeyRing;
use InvalidArgumentException;

final class DataEncryptionKeyRingFactory
{
    /**
     * @param  array<mixed, mixed>  $configuration
     */
    public function fromConfiguration(array $configuration): DataEncryptionKeyRing
    {
        $currentKey = self::keyFromConfiguration($configuration['current'] ?? null);
        $retiringConfigurations = $configuration['retiring'] ?? null;

        if (! is_array($retiringConfigurations) || ! array_is_list($retiringConfigurations)) {
            throw new InvalidArgumentException('Retiring data encryption keys must be configured as a list.');
        }

        $retiringKeys = [];

        foreach ($retiringConfigurations as $retiringConfiguration) {
            $retiringKeys[] = self::keyFromConfiguration($retiringConfiguration);
        }

        return new DataEncryptionKeyRing($currentKey, $retiringKeys);
    }

    private static function keyFromConfiguration(mixed $configuration): DataEncryptionKey
    {
        if (
            ! is_array($configuration)
            || ! is_string($configuration['key_id'] ?? null)
            || ! is_string($configuration['key_base64'] ?? null)
        ) {
            throw new InvalidArgumentException('Data encryption key configuration is incomplete.');
        }

        $key = base64_decode($configuration['key_base64'], true);

        if ($key === false || base64_encode($key) !== $configuration['key_base64']) {
            throw new InvalidArgumentException('Data encryption keys must use canonical base64 encoding.');
        }

        return new DataEncryptionKey($configuration['key_id'], $key);
    }
}
