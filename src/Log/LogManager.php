<?php

declare(strict_types=1);

namespace DingTalk\Log;

use DingTalk\Log\SensitiveDataSanitizer;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * 日志管理器
 * 
 * 提供统一的日志记录功能
 */
class LogManager implements LoggerInterface
{
    /**
     * 日志处理器
     */
    private array $handlers = [];

    /**
     * 日志级别映射
     */
    private const LEVEL_MAP = [
        LogLevel::EMERGENCY => 800,
        LogLevel::ALERT => 700,
        LogLevel::CRITICAL => 600,
        LogLevel::ERROR => 500,
        LogLevel::WARNING => 400,
        LogLevel::NOTICE => 300,
        LogLevel::INFO => 200,
        LogLevel::DEBUG => 100,
    ];

    /**
     * 最小日志级别
     */
    private int $minLevel;

    /**
     * 敏感信息脱敏器
     */
    private ?SensitiveDataSanitizer $sanitizer = null;

    /**
     * 构造函数
     */
    public function __construct(
        string $minLevel = LogLevel::DEBUG,
        ?SensitiveDataSanitizer $sanitizer = null
    ) {
        $this->minLevel = self::LEVEL_MAP[$minLevel] ?? 100;
        $this->sanitizer = $sanitizer;
    }

    /**
     * 添加日志处理器
     */
    public function addHandler(LogHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * 移除所有处理器
     */
    public function clearHandlers(): void
    {
        $this->handlers = [];
    }

    /**
     * 获取所有处理器
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * 设置最小日志级别
     */
    public function setMinLevel(string $level): void
    {
        $this->minLevel = self::LEVEL_MAP[$level] ?? 100;
    }

    /**
     * {@inheritdoc}
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        $levelValue = self::LEVEL_MAP[$level] ?? 100;
        
        // 检查日志级别
        if ($levelValue < $this->minLevel) {
            return;
        }

        $record = $this->createRecord($level, $message, $context);
        
        foreach ($this->handlers as $handler) {
            $handler->handle($record);
        }
    }

    /**
     * 创建日志记录
     */
    private function createRecord(string $level, $message, array $context): LogRecord
    {
        // 脱敏处理
        if ($this->sanitizer) {
            $context = $this->sanitizer->sanitize($context);
            $message = $this->sanitizer->sanitize($message);
        }
        
        return new LogRecord(
            $level,
            $this->interpolate($message, $context),
            $context,
            new \DateTimeImmutable(),
            $this->getCallerInfo()
        );
    }

    /**
     * 插值处理消息
     */
    private function interpolate($message, array $context): string
    {
        if (!is_string($message)) {
            $message = $this->stringify($message);
        }

        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * 转换为字符串
     */
    private function stringify($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        
        if (is_scalar($value)) {
            return (string) $value;
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        
        return gettype($value);
    }

    /**
     * 获取调用者信息
     */
    private function getCallerInfo(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        foreach ($trace as $frame) {
            if (isset($frame['class']) && $frame['class'] === self::class) {
                continue;
            }
            
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }
        
        return [
            'file' => 'unknown',
            'line' => 0,
            'function' => 'unknown',
            'class' => null,
        ];
    }

    /**
     * 记录API请求
     */
    public function logApiRequest(string $method, string $url, array $data = [], array $headers = []): void
    {
        $this->info('API Request', [
            'method' => $method,
            'url' => $url,
            'data' => $data,
            'headers' => $this->sanitizeHeaders($headers),
        ]);
    }

    /**
     * 记录API响应
     */
    public function logApiResponse(int $statusCode, $response, float $duration = null): void
    {
        $level = $statusCode >= 400 ? LogLevel::ERROR : LogLevel::INFO;
        
        $context = [
            'status_code' => $statusCode,
            'response' => $response,
        ];
        
        if ($duration !== null) {
            $context['duration'] = round($duration, 3) . 's';
        }
        
        $this->log($level, 'API Response', $context);
    }

    /**
     * 记录异常
     */
    public function logException(\Throwable $exception, array $context = []): void
    {
        $this->error($exception->getMessage(), array_merge($context, [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]));
    }

    /**
     * 清理敏感的请求头
     */
    private function sanitizeHeaders(array $headers): array
    {
        if ($this->sanitizer) {
            return $this->sanitizer->sanitizeHeaders($headers);
        }
        
        $sensitiveHeaders = ['authorization', 'x-acs-dingtalk-access-token', 'cookie'];
        
        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $headers[$key] = '***';
            }
        }
        
        return $headers;
    }

    /**
     * 设置敏感信息脱敏器
     */
    public function setSanitizer(?SensitiveDataSanitizer $sanitizer): void
    {
        $this->sanitizer = $sanitizer;
    }

    /**
     * 获取敏感信息脱敏器
     */
    public function getSanitizer(): ?SensitiveDataSanitizer
    {
        return $this->sanitizer;
    }

    /**
     * 启用默认敏感信息脱敏
     */
    public function enableDefaultSanitization(): void
    {
        $this->sanitizer = SensitiveDataSanitizer::createDefault();
    }

    /**
     * 启用严格模式敏感信息脱敏
     */
    public function enableStrictSanitization(): void
    {
        $this->sanitizer = SensitiveDataSanitizer::createStrict();
    }

    /**
     * 禁用敏感信息脱敏
     */
    public function disableSanitization(): void
    {
        $this->sanitizer = null;
    }

    /**
     * 批量处理日志记录
     */
    public function handleBatch(array $records): void
    {
        foreach ($this->handlers as $handler) {
            if (method_exists($handler, 'handleBatch')) {
                $handler->handleBatch($records);
            } else {
                foreach ($records as $record) {
                    $handler->handle($record);
                }
            }
        }
    }

    /**
     * 关闭所有处理器
     */
    public function close(): void
    {
        foreach ($this->handlers as $handler) {
            if (method_exists($handler, 'close')) {
                $handler->close();
            }
        }
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }
}