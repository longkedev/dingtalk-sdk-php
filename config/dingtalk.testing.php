<?php

declare(strict_types=1);

/**
 * 钉钉SDK测试环境配置
 */
return [
    // API配置
    'api' => [
        'timeout' => 5,
        'retry_times' => 2,
        'debug' => false,
    ],
    
    // 缓存配置
    'cache' => [
        'driver' => 'memory',
        'ttl' => 60, // 1分钟
    ],
    
    // 日志配置
    'log' => [
        'level' => 'info',
        'path' => './logs/dingtalk-test.log',
        'max_files' => 3,
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