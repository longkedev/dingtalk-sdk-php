<?php

declare(strict_types=1);

/**
 * 钉钉SDK开发环境配置
 */
return [
    // API配置
    'api' => [
        'timeout' => 10,
        'retry_times' => 1,
        'debug' => true,
    ],
    
    // 缓存配置
    'cache' => [
        'driver' => 'file',
        'ttl' => 300, // 5分钟
    ],
    
    // 日志配置
    'log' => [
        'level' => 'debug',
        'path' => './logs/dingtalk-dev.log',
        'max_files' => 5,
    ],
    
    // 安全配置
    'security' => [
        'verify_ssl' => false, // 开发环境可以关闭SSL验证
    ],
    
    // 开发配置
    'dev' => [
        'mock_api' => true,
        'log_requests' => true,
        'log_responses' => true,
    ],
];