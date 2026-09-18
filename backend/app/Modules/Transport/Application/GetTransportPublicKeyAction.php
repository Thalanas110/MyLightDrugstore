<?php

declare(strict_types=1);

namespace App\Modules\Transport\Application;

use App\Modules\Transport\Domain\Crypto\TransportKeyRing;

final class GetTransportPublicKeyAction
{
    public function __construct(private TransportKeyRing $transportKeyRing) {}

    public function execute(): PublicTransportKey
    {
        $currentKey = $this->transportKeyRing->current();

        return new PublicTransportKey($currentKey->keyId, $currentKey->publicKeyPem);
    }
}
