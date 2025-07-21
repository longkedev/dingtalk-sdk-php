<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 限流异常类
 * 
 * 用于处理API调用频率限制、配额超限等限流相关的异常
 */
class RateLimitException extends DingTalkException
{
    /**
     * 请求频率超限
     */
    public const RATE_LIMIT_EXCEEDED = 'RATE_LIMIT_EXCEEDED';
    
    /**
     * 配额超限
     */
    public const QUOTA_EXCEEDED = 'QUOTA_EXCEEDED';
    
    /**
     * 并发限制
     */
    public const CONCURRENT_LIMIT = 'CONCURRENT_LIMIT';
    
    /**
     * 每秒请求数限制
     */
    public const QPS_LIMIT = 'QPS_LIMIT';
    
    /**
     * 每分钟请求数限制
     */
    public const QPM_LIMIT = 'QPM_LIMIT';
    
    /**
     * 每小时请求数限制
     */
    public const QPH_LIMIT = 'QPH_LIMIT';
    
    /**
     * 每日请求数限制
     */
    public const QPD_LIMIT = 'QPD_LIMIT';
    
    /**
     * 限制类型
     */
    private string $limitType = '';
    
    /**
     * 当前请求数
     */
    private int $currentRequests = 0;
    
    /**
     * 最大请求数
     */
    private int $maxRequests = 0;
    
    /**
     * 重置时间（Unix时间戳）
     */
    private int $resetTime = 0;
    
    /**
     * 重试延迟时间（秒）
     */
    private int $retryAfter = 0;
    
    /**
     * 创建请求频率超限异常
     */
    public static function rateLimitExceeded(
        int $currentRequests,
        int $maxRequests,
        int $resetTime,
        int $retryAfter = 0,
        ?\Throwable $previous = null
    ): self {
        $exception = new self(
            "Rate limit exceeded. {$currentRequests}/{$maxRequests} requests used. Reset at: " . date('Y-m-d H:i:s', $resetTime),
            self::RATE_LIMIT_EXCEEDED,
            [
                'current_requests' => $currentRequests,
                'max_requests' => $maxRequests,
                'reset_time' => $resetTime,
                'retry_after' => $retryAfter
            ],
            429,
            $previous
        );
        
        $exception->setLimitDetails('rate_limit', $currentRequests, $maxRequests, $resetTime, $retryAfter);
        
        return $exception;
    }
    
    /**
     * 创建配额超限异常
     */
    public static function quotaExceeded(
        int $currentUsage,
        int $maxQuota,
        int $resetTime,
        ?\Throwable $previous = null
    ): self {
        $exception = new self(
            "Quota exceeded. {$currentUsage}/{$maxQuota} quota used. Reset at: " . date('Y-m-d H:i:s', $resetTime),
            self::QUOTA_EXCEEDED,
            [
                'current_usage' => $currentUsage,
                'max_quota' => $maxQuota,
                'reset_time' => $resetTime
            ],
            429,
            $previous
        );
        
        $exception->setLimitDetails('quota', $currentUsage, $maxQuota, $resetTime);
        
        return $exception;
    }
    
    /**
     * 创建并发限制异常
     */
    public static function concurrentLimit(
        int $currentConnections,
        int $maxConnections,
        int $retryAfter = 0,
        ?\Throwable $previous = null
    ): self {
        $exception = new self(
            "Concurrent limit exceeded. {$currentConnections}/{$maxConnections} connections active",
            self::CONCURRENT_LIMIT,
            [
                'current_connections' => $currentConnections,
                'max_connections' => $maxConnections,
                'retry_after' => $retryAfter
            ],
            429,
            $previous
        );
        
        $exception->setLimitDetails('concurrent', $currentConnections, $maxConnections, 0, $retryAfter);
        
        return $exception;
    }
    
    /**
     * 创建QPS限制异常
     */
    public static function qpsLimit(
        int $currentQps,
        int $maxQps,
        int $retryAfter = 1,
        ?\Throwable $previous = null
    ): self {
        $exception = new self(
            "QPS limit exceeded. {$currentQps}/{$maxQps} requests per second",
            self::QPS_LIMIT,
            [
                'current_qps' => $currentQps,
                'max_qps' => $maxQps,
                'retry_after' => $retryAfter
            ],
            429,
            $previous
        );
        
        $exception->setLimitDetails('qps', $currentQps, $maxQps, 0, $retryAfter);
        
        return $exception;
    }
    
    /**
     * 创建QPM限制异常
     */
    public static function qpmLimit(
        int $currentQpm,
        int $maxQpm,
        int $resetTime,
        ?\Throwable $previous = null
    ): self {
        $exception = new self(
            "QPM limit exceeded. {$currentQpm}/{$maxQpm} requests per minute. Reset at: " . date('Y-m-d H:i:s', $resetTime),
            self::QPM_LIMIT,
            [
                'current_qpm' => $currentQpm,
                'max_qpm' => $maxQpm,
                'reset_time' => $resetTime
            ],
            429,
            $previous
        );
        
        $exception->setLimitDetails('qpm', $currentQpm, $maxQpm, $resetTime);
        
        return $exception;
    }
    
    /**
     * 设置限制详情
     */
    private function setLimitDetails(
        string $limitType,
        int $currentRequests,
        int $maxRequests,
        int $resetTime = 0,
        int $retryAfter = 0
    ): void {
        $this->limitType = $limitType;
        $this->currentRequests = $currentRequests;
        $this->maxRequests = $maxRequests;
        $this->resetTime = $resetTime;
        $this->retryAfter = $retryAfter;
    }
    
    /**
     * 获取限制类型
     */
    public function getLimitType(): string
    {
        return $this->limitType;
    }
    
    /**
     * 获取当前请求数
     */
    public function getCurrentRequests(): int
    {
        return $this->currentRequests;
    }
    
    /**
     * 获取最大请求数
     */
    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }
    
    /**
     * 获取重置时间
     */
    public function getResetTime(): int
    {
        return $this->resetTime;
    }
    
    /**
     * 获取重试延迟时间
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
    
    /**
     * 获取剩余请求数
     */
    public function getRemainingRequests(): int
    {
        return max(0, $this->maxRequests - $this->currentRequests);
    }
    
    /**
     * 获取距离重置的时间（秒）
     */
    public function getTimeToReset(): int
    {
        if ($this->resetTime <= 0) {
            return 0;
        }
        
        return max(0, $this->resetTime - time());
    }
    
    /**
     * 是否可以重试
     */
    public function canRetry(): bool
    {
        return $this->retryAfter > 0 || $this->resetTime > 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'limit_type' => $this->limitType,
            'current_requests' => $this->currentRequests,
            'max_requests' => $this->maxRequests,
            'remaining_requests' => $this->getRemainingRequests(),
            'reset_time' => $this->resetTime,
            'time_to_reset' => $this->getTimeToReset(),
            'retry_after' => $this->retryAfter,
            'can_retry' => $this->canRetry(),
        ]);
    }
}