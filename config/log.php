<?php

/**
 * 钉钉SDK日志管理器配置示例
 * 
 * 这个文件展示了如何在不同环境中配置日志管理器
 */

// 简单的环境变量获取函数
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}

// 简单的存储路径函数
if (!function_exists('storage_path')) {
    function storage_path($path = '') {
        $basePath = __DIR__ . '/../storage';
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }
}

return [
    // 默认日志级别
    'default_level' => env('LOG_LEVEL', 'info'),
    
    // 日志根目录
    'log_path' => env('LOG_PATH', storage_path('logs')),
    
    // 环境配置
    'environments' => [
        'development' => [
            'level' => 'debug',
            'handlers' => [
                'console' => [
                    'class' => 'DingTalk\Log\Handlers\ConsoleHandler',
                    'level' => 'debug',
                    'colored' => true,
                    'formatter' => [
                        'class' => 'DingTalk\Log\Formatters\LineFormatter',
                        'format' => "[%datetime%] %level_name%: %message% %context%\n",
                        'date_format' => 'Y-m-d H:i:s'
                    ]
                ],
                'file' => [
                    'class' => 'DingTalk\Log\Handlers\FileHandler',
                    'level' => 'debug',
                    'path' => 'dingtalk-dev.log',
                    'formatter' => [
                        'class' => 'DingTalk\Log\Formatters\JsonFormatter',
                        'pretty_print' => true,
                        'include_caller' => true
                    ]
                ]
            ],
            'sanitization' => [
                'enabled' => true,
                'mode' => 'default'
            ]
        ],
        
        'testing' => [
            'level' => 'info',
            'handlers' => [
                'file' => [
                    'class' => 'DingTalk\Log\Handlers\FileHandler',
                    'level' => 'info',
                    'path' => 'dingtalk-test.log',
                    'formatter' => [
                        'class' => 'DingTalk\Log\Formatters\JsonFormatter',
                        'pretty_print' => false
                    ]
                ]
            ],
            'sanitization' => [
                'enabled' => true,
                'mode' => 'strict'
            ]
        ],
        
        'production' => [
            'level' => 'warning',
            'handlers' => [
                'app' => [
                    'class' => 'DingTalk\Log\Handlers\RotatingFileHandler',
                    'level' => 'info',
                    'path' => 'dingtalk-app.log',
                    'rotation_type' => 'daily',
                    'max_files' => 30,
                    'formatter' => [
                        'class' => 'DingTalk\Log\Formatters\JsonFormatter',
                        'pretty_print' => false,
                        'include_caller' => false
                    ]
                ],
                'error' => [
                    'class' => 'DingTalk\Log\Handlers\RotatingFileHandler',
                    'level' => 'error',
                    'path' => 'dingtalk-error.log',
                    'rotation_type' => 'daily',
                    'max_files' => 90,
                    'formatter' => [
                        'class' => 'DingTalk\Log\Formatters\JsonFormatter',
                        'pretty_print' => true,
                        'include_caller' => true,
                        'include_extra' => true
                    ]
                ],
                'remote' => [
                    'class' => 'DingTalk\Log\Handlers\RemoteHandler',
                    'level' => 'error',
                    'url' => env('LOG_REMOTE_URL'),
                    'api_key' => env('LOG_REMOTE_API_KEY'),
                    'batch_size' => 50,
                    'timeout' => 10,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'DingTalk-SDK-PHP/1.0'
                    ]
                ],
                'performance' => [
                    'class' => 'DingTalk\Log\Handlers\PerformanceHandler',
                    'level' => 'info',
                    'thresholds' => [
                        'duration' => 2.0,
                        'memory' => 50 * 1024 * 1024, // 50MB
                        'cpu' => 90.0
                    ]
                ]
            ],
            'sanitization' => [
                'enabled' => true,
                'mode' => 'strict',
                'custom_fields' => [
                    'internal_token',
                    'company_secret',
                    'user_credential'
                ],
                'custom_patterns' => [
                    '/\bCORP_[A-Z0-9]{16}\b/',
                    '/\bSECRET_[a-f0-9]{32}\b/'
                ]
            ]
        ]
    ],
    
    // 全局敏感信息脱敏配置
    'sanitization' => [
        'default_fields' => [
            'password',
            'passwd',
            'secret',
            'token',
            'api_key',
            'apikey',
            'access_token',
            'refresh_token',
            'private_key',
            'credit_card',
            'creditcard',
            'card_number',
            'cvv',
            'ssn',
            'social_security',
            'phone',
            'mobile',
            'email',
            'mail'
        ],
        'default_patterns' => [
            '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', // 信用卡号
            '/\b1[3-9]\d{9}\b/',                           // 手机号
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // 邮箱
            '/\bsk_[a-zA-Z0-9]{24,}\b/',                   // API密钥
            '/\bBearer\s+[A-Za-z0-9\-\._~\+\/]+=*\b/'      // Bearer token
        ],
        'replacement' => '[FILTERED]'
    ],
    
    // 性能监控配置
    'performance' => [
        'enabled' => env('LOG_PERFORMANCE_ENABLED', false),
        'sample_rate' => env('LOG_PERFORMANCE_SAMPLE_RATE', 0.1), // 10%采样
        'thresholds' => [
            'duration' => [
                'warning' => 1.0,
                'critical' => 3.0
            ],
            'memory' => [
                'warning' => 20 * 1024 * 1024, // 20MB
                'critical' => 50 * 1024 * 1024  // 50MB
            ],
            'cpu' => [
                'warning' => 70.0,
                'critical' => 90.0
            ]
        ]
    ],
    
    // 远程日志配置
    'remote' => [
        'enabled' => env('LOG_REMOTE_ENABLED', false),
        'url' => env('LOG_REMOTE_URL'),
        'api_key' => env('LOG_REMOTE_API_KEY'),
        'timeout' => env('LOG_REMOTE_TIMEOUT', 5),
        'retry_attempts' => env('LOG_REMOTE_RETRY', 3),
        'batch_size' => env('LOG_REMOTE_BATCH_SIZE', 10),
        'flush_interval' => env('LOG_REMOTE_FLUSH_INTERVAL', 30), // 秒
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'DingTalk-SDK-PHP/' . env('SDK_VERSION', '1.0.0')
        ]
    ],
    
    // 日志轮转配置
    'rotation' => [
        'type' => env('LOG_ROTATION_TYPE', 'daily'), // daily, weekly, monthly, size
        'max_files' => env('LOG_ROTATION_MAX_FILES', 7),
        'max_size' => env('LOG_ROTATION_MAX_SIZE', 10 * 1024 * 1024), // 10MB
        'compress' => env('LOG_ROTATION_COMPRESS', false),
        'date_format' => env('LOG_ROTATION_DATE_FORMAT', 'Y-m-d')
    ],
    
    // 格式化器配置
    'formatters' => [
        'json' => [
            'pretty_print' => env('LOG_JSON_PRETTY', false),
            'include_caller' => env('LOG_JSON_INCLUDE_CALLER', true),
            'include_extra' => env('LOG_JSON_INCLUDE_EXTRA', true),
            'json_flags' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ],
        'line' => [
            'format' => env('LOG_LINE_FORMAT', "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"),
            'date_format' => env('LOG_DATE_FORMAT', 'Y-m-d H:i:s'),
            'allow_inline_line_breaks' => env('LOG_ALLOW_INLINE_BREAKS', false),
            'ignore_empty_context_and_extra' => env('LOG_IGNORE_EMPTY', true),
            'max_line_length' => env('LOG_MAX_LINE_LENGTH', 0)
        ]
    ],
    
    // 缓冲区配置
    'buffer' => [
        'size' => env('LOG_BUFFER_SIZE', 100),
        'flush_on_overflow' => env('LOG_BUFFER_FLUSH_ON_OVERFLOW', true),
        'flush_on_shutdown' => env('LOG_BUFFER_FLUSH_ON_SHUTDOWN', true)
    ],
    
    // 错误处理配置
    'error_handling' => [
        'ignore_handler_errors' => env('LOG_IGNORE_HANDLER_ERRORS', true),
        'fallback_handler' => [
            'class' => 'DingTalk\Log\Handlers\FileHandler',
            'path' => 'fallback.log',
            'level' => 'error'
        ]
    ]
];