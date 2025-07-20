<?php

declare(strict_types=1);

namespace DingTalk\Log;

/**
 * 日志记录
 * 
 * 表示一条日志记录的数据结构
 */
class LogRecord
{
    /**
     * 日志级别
     */
    private string $level;

    /**
     * 日志消息
     */
    private string $message;

    /**
     * 上下文数据
     */
    private array $context;

    /**
     * 记录时间
     */
    private \DateTimeImmutable $datetime;

    /**
     * 调用者信息
     */
    private array $caller;

    /**
     * 额外数据
     */
    private array $extra;

    /**
     * 构造函数
     */
    public function __construct(
        string $level,
        string $message,
        array $context = [],
        \DateTimeImmutable $datetime = null,
        array $caller = [],
        array $extra = []
    ) {
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->datetime = $datetime ?: new \DateTimeImmutable();
        $this->caller = $caller;
        $this->extra = $extra;
    }

    /**
     * 获取日志级别
     */
    public function getLevel(): string
    {
        return $this->level;
    }

    /**
     * 获取日志消息
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * 获取上下文数据
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 获取记录时间
     */
    public function getDatetime(): \DateTimeImmutable
    {
        return $this->datetime;
    }

    /**
     * 获取调用者信息
     */
    public function getCaller(): array
    {
        return $this->caller;
    }

    /**
     * 获取额外数据
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * 设置额外数据
     */
    public function setExtra(array $extra): void
    {
        $this->extra = $extra;
    }

    /**
     * 添加额外数据
     */
    public function addExtra(string $key, $value): void
    {
        $this->extra[$key] = $value;
    }

    /**
     * 获取格式化的时间戳
     */
    public function getFormattedDatetime(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->datetime->format($format);
    }

    /**
     * 获取调用者文件名
     */
    public function getCallerFile(): string
    {
        return $this->caller['file'] ?? 'unknown';
    }

    /**
     * 获取调用者行号
     */
    public function getCallerLine(): int
    {
        return $this->caller['line'] ?? 0;
    }

    /**
     * 获取调用者函数名
     */
    public function getCallerFunction(): string
    {
        return $this->caller['function'] ?? 'unknown';
    }

    /**
     * 获取调用者类名
     */
    public function getCallerClass(): ?string
    {
        return $this->caller['class'] ?? null;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'datetime' => $this->datetime->format(\DateTime::ATOM),
            'caller' => $this->caller,
            'extra' => $this->extra,
        ];
    }

    /**
     * 转换为JSON
     */
    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 克隆记录
     */
    public function with(array $changes = []): self
    {
        return new self(
            $changes['level'] ?? $this->level,
            $changes['message'] ?? $this->message,
            $changes['context'] ?? $this->context,
            $changes['datetime'] ?? $this->datetime,
            $changes['caller'] ?? $this->caller,
            $changes['extra'] ?? $this->extra
        );
    }

    /**
     * 检查是否包含敏感信息
     */
    public function hasSensitiveData(): bool
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization'];
        
        foreach ($this->context as $key => $value) {
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * 清理敏感信息
     */
    public function sanitize(): self
    {
        if (!$this->hasSensitiveData()) {
            return $this;
        }
        
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization'];
        $context = $this->context;
        
        foreach ($context as $key => $value) {
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $context[$key] = '***';
                }
            }
        }
        
        return $this->with(['context' => $context]);
    }

    /**
     * 获取内存使用情况
     */
    public function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
        ];
    }

    /**
     * 魔术方法：转换为字符串
     */
    public function __toString(): string
    {
        return sprintf(
            '[%s] %s: %s',
            $this->getFormattedDatetime(),
            strtoupper($this->level),
            $this->message
        );
    }
}