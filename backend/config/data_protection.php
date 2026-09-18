<?php

declare(strict_types=1);

return [
    'keys' => [
        'current' => [
            'key_id' => env('DATA_ENCRYPTION_CURRENT_KEY_ID', ''),
            'key_base64' => env('DATA_ENCRYPTION_CURRENT_KEY_BASE64', ''),
        ],
        'retiring' => json_decode((string) env('DATA_ENCRYPTION_RETIRING_KEYS_JSON', '[]'), true, 512, JSON_THROW_ON_ERROR),
    ],
];
