<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Swagger Documentation Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Swagger documentation.
    |
    */

    'title' => env('SWAGGER_TITLE', 'Education API System'),
    'description' => env('SWAGGER_DESCRIPTION', '教育管理系统API文档'),
    'version' => env('SWAGGER_VERSION', '1.0.0'),
    'host' => env('SWAGGER_HOST', 'api.localhost'),
    'base_path' => env('SWAGGER_BASE_PATH', '/api'),
    'schemes' => ['http', 'https'],
    'consumes' => ['application/json'],
    'produces' => ['application/json'],
    
    /*
    |--------------------------------------------------------------------------
    | Security Definitions
    |--------------------------------------------------------------------------
    |
    | Define the security schemes for your API.
    |
    */
    'security_definitions' => [
        'bearer_token' => [
            'type' => 'apiKey',
            'name' => 'Authorization',
            'in' => 'header',
            'description' => 'Bearer token for API authentication'
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Global Parameters
    |--------------------------------------------------------------------------
    |
    | Define global parameters that can be used across all endpoints.
    |
    */
    'global_parameters' => [
        'page' => [
            'name' => 'page',
            'in' => 'query',
            'type' => 'integer',
            'description' => 'Page number for pagination',
            'default' => 1
        ],
        'per_page' => [
            'name' => 'per_page',
            'in' => 'query',
            'type' => 'integer',
            'description' => 'Number of items per page',
            'default' => 15
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Response Templates
    |--------------------------------------------------------------------------
    |
    | Define common response templates.
    |
    */
    'response_templates' => [
        'success' => [
            'description' => 'Success',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => true
                    ],
                    'message' => [
                        'type' => 'string',
                        'example' => 'Operation successful'
                    ],
                    'data' => [
                        'type' => 'object'
                    ]
                ]
            ]
        ],
        'error' => [
            'description' => 'Error',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => false
                    ],
                    'message' => [
                        'type' => 'string',
                        'example' => 'Error message'
                    ],
                    'errors' => [
                        'type' => 'object',
                        'description' => 'Validation errors'
                    ]
                ]
            ]
        ],
        'unauthorized' => [
            'description' => 'Unauthorized',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => false
                    ],
                    'message' => [
                        'type' => 'string',
                        'example' => 'Unauthorized'
                    ]
                ]
            ]
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Tags
    |--------------------------------------------------------------------------
    |
    | Define tags for grouping endpoints.
    |
    */
    'tags' => [
        [
            'name' => 'Authentication',
            'description' => '用户认证相关接口'
        ],
        [
            'name' => 'User',
            'description' => '用户管理相关接口'
        ],
        [
            'name' => 'Course',
            'description' => '课程管理相关接口'
        ],
        [
            'name' => 'Invoice',
            'description' => '账单管理相关接口'
        ]
    ]
];
