<?php

return [
    'deposit_ttl_minutes' => (int) env('CRYPTO_DEPOSIT_TTL_MINUTES', 20),

    'ton' => [
        'master_address'     => env('TON_MASTER_ADDRESS'),
        'api_key'            => env('TON_API_KEY', ''),
        'api_url'            => env('TON_API_URL', 'https://toncenter.com/api/v2'),
        'api_v3_url'         => env('TON_API_V3_URL', 'https://toncenter.com/api/v3'),
        // USDT-TON Jetton master contract (mainnet). Override via TON_USDT_JETTON_MASTER in .env.
        'usdt_jetton_master' => env('TON_USDT_JETTON_MASTER', 'EQCxE6mUtQJKFnGfaROTKOt1lZbDiiX1kCixRv7Nw2Id_sDs'),
    ],

    'bitcoin' => [
        // Comma-separated list of your BTC receiving addresses.
        // Each address handles one deposit at a time; add more for higher concurrency.
        'deposit_addresses' => array_filter(explode(',', env('BTC_DEPOSIT_ADDRESSES', ''))),
        'api_url'           => env('BTC_API_URL', 'https://mempool.space/api'),
    ],

    'tron' => [
        // Comma-separated list of TRON receiving addresses (handles TRX + USDT-TRC20).
        'deposit_addresses' => array_filter(explode(',', env('TRON_DEPOSIT_ADDRESSES', ''))),
        'api_url'           => env('TRON_API_URL', 'https://api.trongrid.io'),
        // Free API key from trongrid.io (optional, increases rate limits).
        'api_key'           => env('TRONGRID_API_KEY', ''),
        // USDT-TRC20 contract address (mainnet).
        'usdt_contract'     => env('TRON_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
    ],

    'price_oracle' => [
        'base_url'          => 'https://api.coingecko.com/api/v3',
        'cache_ttl_seconds' => 60,
    ],
];
