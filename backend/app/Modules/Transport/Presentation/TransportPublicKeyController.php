<?php

declare(strict_types=1);

namespace App\Modules\Transport\Presentation;

use App\Modules\Transport\Application\GetTransportPublicKeyAction;
use Illuminate\Http\JsonResponse;

final class TransportPublicKeyController
{
    public function __construct(private GetTransportPublicKeyAction $getTransportPublicKey) {}

    public function __invoke(): JsonResponse
    {
        $key = $this->getTransportPublicKey->execute();

        return response()->json([
            'data' => [
                'version' => 1,
                'algorithm' => 'RSA-OAEP-256',
                'transportAlgorithm' => 'A256GCM',
                'keyId' => $key->keyId,
                'publicKey' => $key->publicKeyPem,
            ],
        ]);
    }
}
