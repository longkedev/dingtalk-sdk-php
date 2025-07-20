<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 钉钉应用配置
    |--------------------------------------------------------------------------
    |
    | 这里配置钉钉应用的基本信息，包括应用类型、认证信息等
    |
    */

    // 应用基本信息
    'app_key' => '',
    'app_secret' => '',
    
    // 应用类型：internal（企业内部应用）、third_party_enterprise（第三方企业应用）、third_party_personal（第三方个人应用）
    'app_type' => 'internal',
    
    // API版本：auto（自动检测）、v1（旧版API）、v2（新版API）
    'api_version' => 'auto',
    
    /*
    |--------------------------------------------------------------------------
    | API配置
    |--------------------------------------------------------------------------
    */
    
    // API基础URL
    'api_base_url' => 'https://oapi.dingtalk.com',
    
    // 新版API基础URL
    'api_v2_base_url' => 'https://api.dingtalk.com',
    
    // 请求超时时间（秒）
    'timeout' => 30,
    
    // 连接超时时间（秒）
    'connect_timeout' => 10,
    
    // 重试次数
    'retry_times' => 3,
    
    /*
    |--------------------------------------------------------------------------
    | 缓存配置
    |--------------------------------------------------------------------------
    */
    
    'cache' => [
        // 缓存驱动：file、redis、memory
        'driver' => 'file',
        
        // 缓存前缀
        'prefix' => 'dingtalk_',
        
        // 默认缓存时间（秒）
        'default_ttl' => 7200,
        
        // 文件缓存配置
        'file' => [
            'path' => sys_get_temp_dir() . '/dingtalk_cache',
        ],
        
        // Redis缓存配置
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => '',
            'database' => 0,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 日志配置
    |--------------------------------------------------------------------------
    */
    
    'log' => [
        // 日志级别：debug、info、notice、warning、error、critical、alert、emergency
        'level' => 'info',
        
        // 日志处理器：file、console、syslog
        'handlers' => [
            'file' => [
                'enabled' => true,
                'path' => './logs/dingtalk.log',
                'max_files' => 30,
            ],
            'console' => [
                'enabled' => false,
            ],
        ],
        
        // 敏感信息脱敏
        'mask_sensitive' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 安全配置
    |--------------------------------------------------------------------------
    */
    
    'security' => [
        // 是否验证SSL证书
        'verify_ssl' => true,
        
        // 加密密钥（用于敏感信息加密）
        'encrypt_key' => '',
        
        // IP白名单（钉钉回调IP）
        'ip_whitelist' => [
            '114.215.201.0/24',
            '114.215.146.0/24',
            '47.102.106.0/24',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | HTTP客户端配置
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => env('DINGTALK_HTTP_TIMEOUT', 30),
        'connect_timeout' => env('DINGTALK_HTTP_CONNECT_TIMEOUT', 10),
        'retries' => env('DINGTALK_HTTP_RETRIES', 3),
        'retry_delay' => env('DINGTALK_HTTP_RETRY_DELAY', 1000),
        'pool' => [
            'concurrency' => env('DINGTALK_HTTP_POOL_CONCURRENCY', 10),
            'max_connections' => env('DINGTALK_HTTP_POOL_MAX_CONNECTIONS', 100),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | 开发配置
    |--------------------------------------------------------------------------
    */
    
    'debug' => [
        // 是否开启调试模式
        'enabled' => false,
        
        // 是否记录请求响应日志
        'log_requests' => false,
        
        // 是否使用沙箱环境
        'sandbox' => false,
    ],
];