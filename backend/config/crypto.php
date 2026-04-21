<?php

return [
    'ton' => [
        'master_address'     => env('TON_MASTER_ADDRESS'),
        'api_key'            => env('TON_API_KEY', ''),
        'api_url'            => env('TON_API_URL', 'https://toncenter.com/api/v2'),
        'api_v3_url'         => env('TON_API_V3_URL', 'https://toncenter.com/api/v3'),
        'deposit_ttl_minutes' => (int) env('TON_DEPOSIT_TTL_MINUTES', 20),
        // USDT-TON Jetton master contract (mainnet). Override via TON_USDT_JETTON_MASTER in .env.
        'usdt_jetton_master' => env('TON_USDT_JETTON_MASTER', 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs'),
    ],
    'price_oracle' => [
        'base_url'          => 'https://api.coingecko.com/api/v3',
        'cache_ttl_seconds' => 60,
    ],
];
