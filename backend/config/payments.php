<?php

return [
    'default' => env('PAYMENT_PROVIDER', 'yookassa'),

    'multi_tenant' => [
        'enabled' => env('MULTI_TENANT_ENABLED', false),
    ],

    'antifraud' => [
        'enabled' => env('ANTIFRAUD_ENABLED', true),
        'rules' => [
            // Max 10 платежей с одного IP за 1 минуту
            ['dimension' => 'ip', 'max_count' => 10, 'window_seconds' => 60],
            // Max 50 платежей с одного IP за 1 час
            ['dimension' => 'ip', 'max_count' => 50, 'window_seconds' => 3600],
            // Max 5 платежей от одного user_id за 1 минуту
            ['dimension' => 'user_id', 'max_count' => 5, 'window_seconds' => 60],
            // Max 500 000 руб (50 000 000 коп) с одного user_id за 24 часа
            ['dimension' => 'user_id', 'max_count' => 100, 'window_seconds' => 86400, 'max_amount_kopecks' => 50_000_000],
            // Max 3 платежа с одного payment_method_id за 5 минут (recurring)
            ['dimension' => 'payment_method_id', 'max_count' => 3, 'window_seconds' => 300],
        ],
    ],

    'circuit_breaker' => [
        'enabled' => env('CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => (int) env('CIRCUIT_BREAKER_FAILURE_THRESHOLD', 5),
        'recovery_timeout_seconds' => (int) env('CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 30),
    ],

    'yookassa' => [
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
        'webhook_ips' => [
            '185.71.76.0/27',
            '185.71.77.0/27',
            '77.75.153.0/25',
            '77.75.156.11',
            '77.75.156.35',
            '77.75.154.128/25',
        ],
    ],

    'robokassa' => [
        'login' => env('ROBOKASSA_LOGIN'),
        'password1' => env('ROBOKASSA_PASSWORD1'),
        'password2' => env('ROBOKASSA_PASSWORD2'),
        'is_test' => env('ROBOKASSA_IS_TEST', true),
        'webhook_ips' => [
            '185.26.103.0/24',
            '185.60.211.0/24',
        ],
    ],

    'sbp' => [
        'merchant_id' => env('SBP_MERCHANT_ID'),
        'api_key' => env('SBP_API_KEY'),
        'webhook_secret' => env('SBP_WEBHOOK_SECRET'),
        'base_url' => env('SBP_BASE_URL', 'https://api.nspk.ru/sbp/v1/merchant-integrations'),
    ],

    'alfabank' => [
        'login' => env('ALFABANK_LOGIN'),
        'password' => env('ALFABANK_PASSWORD'),
        'base_url' => env('ALFABANK_BASE_URL', 'https://pay.alfabank.ru/payment/rest'),
        'is_test' => env('ALFABANK_IS_TEST', true),
        'webhook_ips' => [
            '84.204.0.0/16',
            '212.48.16.0/20',
            '195.98.79.0/24',
        ],
    ],

    'cloudpayments' => [
        'public_id' => env('CLOUDPAYMENTS_PUBLIC_ID'),
        'api_secret' => env('CLOUDPAYMENTS_API_SECRET'),
    ],
];
