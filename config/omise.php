<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Omise Configuration
    |--------------------------------------------------------------------------
    |
    | 这里配置 Omise 支付网关的相关设置
    |
    */

    // 公钥（前端使用）
    'public_key' => env('OMISE_PUBLIC_KEY', 'pkey_test_65ggqd9jdlaax89pkex'),

    // 私钥（后端使用）
    'secret_key' => env('OMISE_SECRET_KEY', 'skey_test_65ggqda75e2fzsxfvty'),

    // 环境设置
    'environment' => env('OMISE_ENVIRONMENT', 'test'), // test 或 live

    // 默认货币
    'default_currency' => env('OMISE_DEFAULT_CURRENCY', 'THB'),

    // 支持的货币
    'supported_currencies' => [
        'THB' => '泰铢',
        'USD' => '美元',
        'EUR' => '欧元',
        'JPY' => '日元',
        'SGD' => '新加坡元',
    ],

    // 支付方式
    'payment_methods' => [
        'credit_card' => '信用卡',
        'bank_transfer' => '银行转账',
        'convenience_store' => '便利店支付',
        'internet_banking' => '网银支付',
    ],

    // Webhook 设置
    'webhook' => [
        'enabled' => env('OMISE_WEBHOOK_ENABLED', true),
        'url' => env('OMISE_WEBHOOK_URL', '/api/payment/webhook'),
        'secret' => env('OMISE_WEBHOOK_SECRET'),
    ],

    // 超时设置
    'timeout' => env('OMISE_TIMEOUT', 30),

    // 重试设置
    'retry' => [
        'enabled' => env('OMISE_RETRY_ENABLED', true),
        'max_attempts' => env('OMISE_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('OMISE_RETRY_DELAY', 1000), // 毫秒
    ],
];
