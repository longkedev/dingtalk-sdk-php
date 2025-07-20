<?php

namespace DingTalk\Log;

use DingTalk\Log\Handlers\FileHandler;
use DingTalk\Log\Handlers\ConsoleHandler;
use DingTalk\Log\Handlers\RemoteHandler;
use DingTalk\Log\Handlers\RotatingFileHandler;
use DingTalk\Log\Handlers\PerformanceHandler;
use DingTalk\Log\Formatters\JsonFormatter;
use DingTalk\Log\Formatters\LineFormatter;
use DingTalk\Log\Formatters\FormatterInterface;
use Psr\Log\LogLevel;
use InvalidArgumentException;

// 简单的存储路径函数
if (!function_exists('storage_path')) {
    function storage_path($path = '') {
        $basePath = __DIR__ . '/../../storage';
        return $path ? $basePath . '/' . ltrim($path, '/') : $basePath;
    }
}

/**
 * 日志管理器工厂类
 * 
 * 用于根据配置创建和配置日志管理器实例
 */
class LogManagerFactory
{
    /**
     * 默认配置
     */
    private static array $defaultConfig = [
        'level' => LogLevel::INFO,
        'handlers' => [],
        'sanitization' => [
            'enabled' => false,
            'mode' => 'default'
        ]
    ];

    /**
     * 根据配置创建日志管理器
     *
     * @param array $config 配置数组
     * @return LogManager
     */
    public static function create(array $config = []): LogManager
    {
        $config = array_merge(self::$defaultConfig, $config);
        
        // 创建日志管理器
        $logger = new LogManager($config['level']);
        
        // 配置敏感信息脱敏
        if ($config['sanitization']['enabled']) {
            self::configureSanitization($logger, $config['sanitization']);
        }
        
        // 添加处理器
        foreach ($config['handlers'] as $name => $handlerConfig) {
            $handler = self::createHandler($handlerConfig);
            $logger->addHandler($handler);
        }
        
        return $logger;
    }

    /**
     * 根据环境创建日志管理器
     *
     * @param string $environment 环境名称
     * @param array $config 完整配置数组
     * @return LogManager
     */
    public static function createForEnvironment(string $environment, array $config): LogManager
    {
        if (!isset($config['environments'][$environment])) {
            throw new InvalidArgumentException("Environment '{$environment}' not found in config");
        }
        
        $envConfig = $config['environments'][$environment];
        
        // 合并全局配置
        if (isset($config['sanitization'])) {
            $envConfig['sanitization'] = array_merge(
                $config['sanitization'],
                $envConfig['sanitization'] ?? []
            );
        }
        
        return self::create($envConfig);
    }

    /**
     * 创建开发环境日志管理器
     *
     * @param string $logPath 日志路径
     * @return LogManager
     */
    public static function createForDevelopment(string $logPath = null): LogManager
    {
        $config = [
            'level' => LogLevel::DEBUG,
            'handlers' => [
                'console' => [
                    'class' => ConsoleHandler::class,
                    'level' => LogLevel::DEBUG,
                    'colored' => true,
                    'formatter' => [
                        'class' => LineFormatter::class,
                        'format' => "[%datetime%] %level_name%: %message% %context%\n"
                    ]
                ]
            ],
            'sanitization' => [
                'enabled' => true,
                'mode' => 'default'
            ]
        ];
        
        if ($logPath) {
            $config['handlers']['file'] = [
                'class' => FileHandler::class,
                'level' => LogLevel::DEBUG,
                'path' => $logPath,
                'formatter' => [
                    'class' => JsonFormatter::class,
                    'pretty_print' => true
                ]
            ];
        }
        
        return self::create($config);
    }

    /**
     * 创建生产环境日志管理器
     *
     * @param string $logPath 日志路径
     * @param array $options 额外选项
     * @return LogManager
     */
    public static function createForProduction(string $logPath, array $options = []): LogManager
    {
        $config = [
            'level' => $options['level'] ?? LogLevel::WARNING,
            'handlers' => [
                'app' => [
                    'class' => RotatingFileHandler::class,
                    'level' => LogLevel::INFO,
                    'path' => $logPath . '/app.log',
                    'rotation_type' => 'daily',
                    'max_files' => 30,
                    'formatter' => [
                        'class' => JsonFormatter::class,
                        'pretty_print' => false
                    ]
                ],
                'error' => [
                    'class' => RotatingFileHandler::class,
                    'level' => LogLevel::ERROR,
                    'path' => $logPath . '/error.log',
                    'rotation_type' => 'daily',
                    'max_files' => 90,
                    'formatter' => [
                        'class' => JsonFormatter::class,
                        'pretty_print' => true,
                        'include_caller' => true
                    ]
                ]
            ],
            'sanitization' => [
                'enabled' => true,
                'mode' => 'strict'
            ]
        ];
        
        // 添加远程处理器（如果配置了）
        if (isset($options['remote_url']) && isset($options['remote_api_key'])) {
            $config['handlers']['remote'] = [
                'class' => RemoteHandler::class,
                'level' => LogLevel::ERROR,
                'url' => $options['remote_url'],
                'api_key' => $options['remote_api_key'],
                'batch_size' => $options['remote_batch_size'] ?? 50
            ];
        }
        
        // 添加性能处理器（如果启用）
        if ($options['performance_enabled'] ?? false) {
            $config['handlers']['performance'] = [
                'class' => PerformanceHandler::class,
                'level' => LogLevel::INFO,
                'thresholds' => $options['performance_thresholds'] ?? []
            ];
        }
        
        return self::create($config);
    }

    /**
     * 创建测试环境日志管理器
     *
     * @param string $logPath 日志路径
     * @return LogManager
     */
    public static function createForTesting(string $logPath = null): LogManager
    {
        $config = [
            'level' => LogLevel::INFO,
            'handlers' => [],
            'sanitization' => [
                'enabled' => true,
                'mode' => 'strict'
            ]
        ];
        
        if ($logPath) {
            $config['handlers']['file'] = [
                'class' => FileHandler::class,
                'level' => LogLevel::INFO,
                'path' => $logPath,
                'formatter' => [
                    'class' => JsonFormatter::class,
                    'pretty_print' => false
                ]
            ];
        }
        
        return self::create($config);
    }

    /**
     * 创建处理器
     *
     * @param array $config 处理器配置
     * @return LogHandlerInterface
     */
    private static function createHandler(array $config): LogHandlerInterface
    {
        $class = $config['class'];
        
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Handler class '{$class}' not found");
        }
        
        switch ($class) {
            case FileHandler::class:
                return self::createFileHandler($config);
                
            case ConsoleHandler::class:
                return self::createConsoleHandler($config);
                
            case RotatingFileHandler::class:
                return self::createRotatingFileHandler($config);
                
            case RemoteHandler::class:
                return self::createRemoteHandler($config);
                
            case PerformanceHandler::class:
                return self::createPerformanceHandler($config);
                
            default:
                throw new InvalidArgumentException("Unsupported handler class '{$class}'");
        }
    }

    /**
     * 创建文件处理器
     */
    private static function createFileHandler(array $config): FileHandler
    {
        $handler = new FileHandler(
            $config['path'],
            $config['level'] ?? LogLevel::INFO,
            $config['file_permission'] ?? 0644,
            $config['use_locking'] ?? true
        );
        
        if (isset($config['formatter'])) {
            $formatter = self::createFormatter($config['formatter']);
            $handler->setFormatter($formatter);
        }
        
        return $handler;
    }

    /**
     * 创建控制台处理器
     */
    private static function createConsoleHandler(array $config): ConsoleHandler
    {
        $handler = new ConsoleHandler(
            $config['level'] ?? LogLevel::INFO,
            $config['colored'] ?? false,
            $config['stream'] ?? STDOUT
        );
        
        if (isset($config['formatter'])) {
            $formatter = self::createFormatter($config['formatter']);
            $handler->setFormatter($formatter);
        }
        
        return $handler;
    }

    /**
     * 创建轮转文件处理器
     */
    private static function createRotatingFileHandler(array $config): RotatingFileHandler
    {
        $handler = new RotatingFileHandler(
            $config['path'],
            $config['level'] ?? LogLevel::INFO,
            $config['rotation_type'] ?? 'daily',
            $config['max_files'] ?? 7,
            $config['max_size'] ?? 0
        );
        
        if (isset($config['formatter'])) {
            $formatter = self::createFormatter($config['formatter']);
            $handler->setFormatter($formatter);
        }
        
        return $handler;
    }

    /**
     * 创建远程处理器
     */
    private static function createRemoteHandler(array $config): RemoteHandler
    {
        $handler = new RemoteHandler(
            $config['url'],
            $config['level'] ?? LogLevel::ERROR,
            $config['headers'] ?? [],
            $config['http_options'] ?? []
        );
        
        if (isset($config['api_key'])) {
            $handler->setApiKey($config['api_key']);
        }
        
        // 注意：RemoteHandler 目前不支持 setBatchSize 方法
        // 如果需要批量处理，可以在未来版本中添加
        
        return $handler;
    }

    /**
     * 创建性能处理器
     */
    private static function createPerformanceHandler(array $config): PerformanceHandler
    {
        // 创建一个基础的文件处理器作为性能处理器的底层处理器
        $baseHandler = new FileHandler(
            $config['base_log_path'] ?? storage_path('logs/performance.log'),
            LogLevel::INFO
        );
        
        $handler = new PerformanceHandler($baseHandler);
        
        // 注意：PerformanceHandler 目前不支持 setThresholds 方法
        // 如果需要自定义阈值，可以在未来版本中添加
        
        return $handler;
    }

    /**
     * 创建格式化器
     */
    private static function createFormatter(array $config): FormatterInterface
    {
        $class = $config['class'];
        
        switch ($class) {
            case JsonFormatter::class:
                return new JsonFormatter(
                    $config['pretty_print'] ?? false,
                    $config['include_caller'] ?? false,
                    $config['include_extra'] ?? false,
                    $config['json_flags'] ?? 0
                );
                
            case LineFormatter::class:
                return new LineFormatter(
                    $config['format'] ?? null,
                    $config['date_format'] ?? null,
                    $config['allow_inline_line_breaks'] ?? false,
                    $config['ignore_empty_context_and_extra'] ?? false,
                    $config['max_line_length'] ?? 0
                );
                
            default:
                throw new InvalidArgumentException("Unsupported formatter class '{$class}'");
        }
    }

    /**
     * 配置敏感信息脱敏
     */
    private static function configureSanitization(LogManager $logger, array $config): void
    {
        switch ($config['mode']) {
            case 'default':
                $logger->enableDefaultSanitization();
                break;
                
            case 'strict':
                $logger->enableStrictSanitization();
                break;
                
            case 'custom':
                $sanitizer = new SensitiveDataSanitizer(
                    $config['custom_fields'] ?? [],
                    $config['custom_patterns'] ?? [],
                    $config['replacement'] ?? '[FILTERED]'
                );
                $logger->setSanitizer($sanitizer);
                break;
                
            default:
                $logger->enableDefaultSanitization();
        }
    }

    /**
     * 从配置文件创建日志管理器
     *
     * @param string $configPath 配置文件路径
     * @param string $environment 环境名称
     * @return LogManager
     */
    public static function createFromConfigFile(string $configPath, string $environment = 'production'): LogManager
    {
        if (!file_exists($configPath)) {
            throw new InvalidArgumentException("Config file '{$configPath}' not found");
        }
        
        $config = require $configPath;
        
        if (!is_array($config)) {
            throw new InvalidArgumentException("Config file must return an array");
        }
        
        return self::createForEnvironment($environment, $config);
    }
}