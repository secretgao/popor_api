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
    'public_key' => 'pkey_test_65ggqd9jdlaax89pkex',

    // 私钥（后端使用）
    'secret_key' => 'skey_test_65ggqda75e2fzsxfvty',

    // 环境设置
    'environment' => 'test', // test 或 live

    // 默认货币
    'default_currency' => 'JPY',

    // 支持的货币
    'supported_currencies' => [
        'JPY' => '日元',
        'USD' => '美元',
        'EUR' => '欧元',
        'THB' => '泰铢',
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
        'enabled' => true,
        'url' => '/api/payment/webhook',
        'secret' => env('OMISE_WEBHOOK_SECRET', 'webhook_secret_test_123456789'),
    ],

    // 超时设置
    'timeout' => 30,

    // 重试设置
    'retry' => [
        'enabled' => true,
        'max_attempts' => 3,
        'delay' => 1000, // 毫秒
    ],
];
