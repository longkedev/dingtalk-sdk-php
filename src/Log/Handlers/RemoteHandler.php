<?php

declare(strict_types=1);

namespace DingTalk\Log\Handlers;

use DingTalk\Log\LogHandlerInterface;
use DingTalk\Log\LogRecord;
use Psr\Log\LogLevel;

/**
 * 远程日志处理器
 * 
 * 将日志发送到远程服务器
 */
class RemoteHandler implements LogHandlerInterface
{
    /**
     * 远程服务器URL
     */
    private string $url;

    /**
     * 最小日志级别
     */
    private int $minLevel;

    /**
     * HTTP客户端选项
     */
    private array $httpOptions;

    /**
     * 批量发送缓冲区
     */
    private array $buffer = [];

    /**
     * 缓冲区大小
     */
    private int $bufferSize;

    /**
     * 超时时间
     */
    private int $timeout;

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
     * 构造函数
     */
    public function __construct(
        string $url,
        string $minLevel = LogLevel::DEBUG,
        array $httpOptions = [],
        int $bufferSize = 100,
        int $timeout = 30
    ) {
        $this->url = $url;
        $this->minLevel = self::LEVEL_MAP[$minLevel] ?? 100;
        $this->httpOptions = array_merge([
            'Content-Type' => 'application/json',
            'User-Agent' => 'DingTalk-SDK-PHP/1.0',
        ], $httpOptions);
        $this->bufferSize = $bufferSize;
        $this->timeout = $timeout;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record->getLevel())) {
            return false;
        }

        $this->buffer[] = $record;

        // 如果缓冲区满了，立即发送
        if (count($this->buffer) >= $this->bufferSize) {
            return $this->flush();
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(string $level): bool
    {
        $levelValue = self::LEVEL_MAP[$level] ?? 100;
        return $levelValue >= $this->minLevel;
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records): bool
    {
        $handled = [];
        
        foreach ($records as $record) {
            if ($this->isHandling($record->getLevel())) {
                $handled[] = $record;
            }
        }

        if (empty($handled)) {
            return true;
        }

        return $this->sendRecords($handled);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        $this->flush();
    }

    /**
     * 刷新缓冲区
     */
    public function flush(): bool
    {
        if (empty($this->buffer)) {
            return true;
        }

        $records = $this->buffer;
        $this->buffer = [];

        return $this->sendRecords($records);
    }

    /**
     * 发送日志记录到远程服务器
     */
    private function sendRecords(array $records): bool
    {
        $payload = [
            'logs' => array_map([$this, 'formatRecord'], $records),
            'timestamp' => time(),
            'source' => 'dingtalk-sdk-php',
        ];

        $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return $this->sendHttpRequest($jsonData);
    }

    /**
     * 格式化日志记录
     */
    private function formatRecord(LogRecord $record): array
    {
        return [
            'level' => $record->getLevel(),
            'message' => $record->getMessage(),
            'context' => $record->getContext(),
            'datetime' => $record->getDatetime()->format(\DateTime::ATOM),
            'caller' => $record->getCaller(),
            'extra' => $record->getExtra(),
        ];
    }

    /**
     * 发送HTTP请求
     */
    private function sendHttpRequest(string $data): bool
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $this->buildHeaders(),
                'content' => $data,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($this->url, false, $context);
        
        if ($result === false) {
            // 记录发送失败，但不抛出异常避免循环
            error_log("Failed to send logs to remote server: {$this->url}");
            return false;
        }

        // 检查HTTP状态码
        if (isset($http_response_header[0])) {
            $statusCode = (int) substr($http_response_header[0], 9, 3);
            return $statusCode >= 200 && $statusCode < 300;
        }

        return true;
    }

    /**
     * 构建HTTP头
     */
    private function buildHeaders(): string
    {
        $headers = [];
        
        foreach ($this->httpOptions as $key => $value) {
            $headers[] = "{$key}: {$value}";
        }

        return implode("\r\n", $headers);
    }

    /**
     * 设置认证信息
     */
    public function setAuth(string $username, string $password): void
    {
        $this->httpOptions['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
    }

    /**
     * 设置API密钥
     */
    public function setApiKey(string $apiKey, string $header = 'X-API-Key'): void
    {
        $this->httpOptions[$header] = $apiKey;
    }

    /**
     * 获取缓冲区大小
     */
    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    /**
     * 设置缓冲区大小
     */
    public function setBufferSize(int $size): void
    {
        $this->bufferSize = max(1, $size);
    }

    /**
     * 获取当前缓冲区记录数
     */
    public function getBufferCount(): int
    {
        return count($this->buffer);
    }
}