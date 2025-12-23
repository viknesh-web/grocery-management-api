<?php

return [
    'product_types' => env('ENABLE_PRODUCT_TYPES', true),
    'address_field' => env('ENABLE_ADDRESS_FIELD', false),
    'advanced_analytics' => env('ENABLE_ANALYTICS', true),
    'whatsapp_integration' => env('ENABLE_WHATSAPP', true),

    'address_api' => [
        'url' => env('ADDRESS_API_URL'),
        'key' => env('ADDRESS_API_KEY'),
        'timeout' => env('ADDRESS_API_TIMEOUT', 5),
        'cache_ttl' => env('ADDRESS_API_CACHE_TTL', 86400),
    ],
];


