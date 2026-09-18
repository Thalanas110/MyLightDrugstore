<?php

declare(strict_types=1);

return [
    'keys' => [
        'current' => [
            'key_id' => env('TRANSPORT_CURRENT_KEY_ID', ''),
            'public_key_base64' => env('TRANSPORT_CURRENT_PUBLIC_KEY_BASE64', ''),
            'private_key_base64' => env('TRANSPORT_CURRENT_PRIVATE_KEY_BASE64', ''),
        ],
        'retiring' => json_decode((string) env('TRANSPORT_RETIRING_KEYS_JSON', '[]'), true, 512, JSON_THROW_ON_ERROR),
    ],
];
