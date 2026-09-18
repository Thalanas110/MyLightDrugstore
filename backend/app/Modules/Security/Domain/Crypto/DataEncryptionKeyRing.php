<?php

declare(strict_types=1);

namespace App\Modules\Security\Domain\Crypto;

use InvalidArgumentException;

final readonly class DataEncryptionKeyRing
{
    /**
     * @var list<DataEncryptionKey>
     */
    private array $retiringKeys;

    /**
     * @param  list<mixed>  $retiringKeys
     */
    public function __construct(
        private DataEncryptionKey $currentKey,
        array $retiringKeys,
    ) {
        $keyIds = [$currentKey->keyId];
        $validatedKeys = [];

        foreach ($retiringKeys as $retiringKey) {
            if (! $retiringKey instanceof DataEncryptionKey || in_array($retiringKey->keyId, $keyIds, true)) {
                throw new InvalidArgumentException('Data encryption key IDs must be unique.');
            }

            $keyIds[] = $retiringKey->keyId;
            $validatedKeys[] = $retiringKey;
        }

        $this->retiringKeys = $validatedKeys;
    }

    public function current(): DataEncryptionKey
    {
        return $this->currentKey;
    }

    public function forDecryption(string $keyId): DataEncryptionKey
    {
        if ($this->currentKey->keyId === $keyId) {
            return $this->currentKey;
        }

        foreach ($this->retiringKeys as $retiringKey) {
            if ($retiringKey->keyId === $keyId) {
                return $retiringKey;
            }
        }

        throw new UnknownDataEncryptionKeyId('Data encryption key is unavailable.');
    }
}
