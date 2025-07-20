<?php

declare(strict_types=1);

/**
 * 钉钉SDK生产环境配置
 */
return [
    // API配置
    'api' => [
        'timeout' => 30,
        'retry_times' => 3,
        'debug' => false,
    ],
    
    // 缓存配置
    'cache' => [
        'driver' => 'redis',
        'ttl' => 3600, // 1小时
    ],
    
    // 日志配置
    'log' => [
        'level' => 'error',
        'path' => './logs/dingtalk-prod.log',
        'max_files' => 10,
    ],
    
    // 安全配置
    'security' => [
        'verify_ssl' => true,
    ],
    
    // 开发配置
    'dev' => [
        'mock_api' => false,
        'log_requests' => false,
        'log_responses' => false,
    ],
];