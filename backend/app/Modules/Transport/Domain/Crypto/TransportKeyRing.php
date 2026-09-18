<?php

declare(strict_types=1);

namespace App\Modules\Transport\Domain\Crypto;

use InvalidArgumentException;

final readonly class TransportKeyRing
{
    /**
     * @var list<TransportKeyMaterial>
     */
    private array $retiringKeys;

    /**
     * @param  list<mixed>  $retiringKeys
     */
    public function __construct(
        private TransportKeyMaterial $currentKey,
        array $retiringKeys,
    ) {
        $keyIds = [$currentKey->keyId];
        $validatedRetiringKeys = [];

        foreach ($retiringKeys as $retiringKey) {
            if (! $retiringKey instanceof TransportKeyMaterial || in_array($retiringKey->keyId, $keyIds, true)) {
                throw new InvalidArgumentException('Transport key IDs must be unique.');
            }

            $keyIds[] = $retiringKey->keyId;
            $validatedRetiringKeys[] = $retiringKey;
        }

        $this->retiringKeys = $validatedRetiringKeys;
    }

    public function current(): TransportKeyMaterial
    {
        return $this->currentKey;
    }

    public function forDecryption(string $keyId): TransportKeyMaterial
    {
        if ($this->currentKey->keyId === $keyId) {
            return $this->currentKey;
        }

        foreach ($this->retiringKeys as $retiringKey) {
            if ($retiringKey->keyId === $keyId) {
                return $retiringKey;
            }
        }

        throw new UnknownTransportKeyId('Transport key is unavailable.');
    }
}
