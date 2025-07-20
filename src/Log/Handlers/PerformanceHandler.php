<?php

declare(strict_types=1);

namespace DingTalk\Log\Handlers;

use DingTalk\Log\LogHandlerInterface;
use DingTalk\Log\LogRecord;
use Psr\Log\LogLevel;

/**
 * 性能监控日志处理器
 * 
 * 专门处理性能相关的日志记录
 */
class PerformanceHandler implements LogHandlerInterface
{
    /**
     * 基础处理器
     */
    private LogHandlerInterface $baseHandler;

    /**
     * 性能阈值配置
     */
    private array $thresholds;

    /**
     * 统计数据
     */
    private array $stats = [
        'total_requests' => 0,
        'slow_requests' => 0,
        'error_requests' => 0,
        'total_duration' => 0.0,
        'max_duration' => 0.0,
        'min_duration' => PHP_FLOAT_MAX,
        'avg_duration' => 0.0,
    ];

    /**
     * 性能数据缓存
     */
    private array $performanceData = [];

    /**
     * 最大缓存数量
     */
    private int $maxCacheSize;

    /**
     * 默认阈值配置
     */
    private const DEFAULT_THRESHOLDS = [
        'slow_request' => 1.0,      // 慢请求阈值（秒）
        'memory_usage' => 50,       // 内存使用阈值（MB）
        'cpu_usage' => 80,          // CPU使用阈值（%）
        'db_query_time' => 0.1,     // 数据库查询时间阈值（秒）
    ];

    /**
     * 构造函数
     */
    public function __construct(
        LogHandlerInterface $baseHandler,
        array $thresholds = [],
        int $maxCacheSize = 1000
    ) {
        $this->baseHandler = $baseHandler;
        $this->thresholds = array_merge(self::DEFAULT_THRESHOLDS, $thresholds);
        $this->maxCacheSize = $maxCacheSize;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(LogRecord $record): bool
    {
        // 处理性能相关的日志
        if ($this->isPerformanceLog($record)) {
            $this->processPerformanceLog($record);
        }

        // 增强日志记录
        $enhancedRecord = $this->enhanceRecord($record);

        return $this->baseHandler->handle($enhancedRecord);
    }

    /**
     * {@inheritdoc}
     */
    public function isHandling(string $level): bool
    {
        return $this->baseHandler->isHandling($level);
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records): bool
    {
        $enhancedRecords = [];
        
        foreach ($records as $record) {
            if ($this->isPerformanceLog($record)) {
                $this->processPerformanceLog($record);
            }
            
            $enhancedRecords[] = $this->enhanceRecord($record);
        }

        return $this->baseHandler->handleBatch($enhancedRecords);
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        // 输出最终统计信息
        $this->logFinalStats();
        
        $this->baseHandler->close();
    }

    /**
     * 检查是否为性能日志
     */
    private function isPerformanceLog(LogRecord $record): bool
    {
        $context = $record->getContext();
        
        return isset($context['duration']) || 
               isset($context['memory_usage']) || 
               isset($context['cpu_usage']) ||
               isset($context['performance']) ||
               strpos($record->getMessage(), 'performance') !== false ||
               strpos($record->getMessage(), 'duration') !== false;
    }

    /**
     * 处理性能日志
     */
    private function processPerformanceLog(LogRecord $record): void
    {
        $context = $record->getContext();
        
        // 更新统计数据
        $this->updateStats($context);
        
        // 缓存性能数据
        $this->cachePerformanceData($record, $context);
        
        // 检查阈值并生成警告
        $this->checkThresholds($record, $context);
    }

    /**
     * 更新统计数据
     */
    private function updateStats(array $context): void
    {
        $this->stats['total_requests']++;
        
        if (isset($context['duration'])) {
            $duration = (float) $context['duration'];
            
            $this->stats['total_duration'] += $duration;
            $this->stats['max_duration'] = max($this->stats['max_duration'], $duration);
            $this->stats['min_duration'] = min($this->stats['min_duration'], $duration);
            $this->stats['avg_duration'] = $this->stats['total_duration'] / $this->stats['total_requests'];
            
            if ($duration > $this->thresholds['slow_request']) {
                $this->stats['slow_requests']++;
            }
        }
        
        if (isset($context['status_code']) && $context['status_code'] >= 400) {
            $this->stats['error_requests']++;
        }
    }

    /**
     * 缓存性能数据
     */
    private function cachePerformanceData(LogRecord $record, array $context): void
    {
        $data = [
            'timestamp' => $record->getDatetime()->getTimestamp(),
            'level' => $record->getLevel(),
            'message' => $record->getMessage(),
            'duration' => $context['duration'] ?? null,
            'memory_usage' => $context['memory_usage'] ?? null,
            'cpu_usage' => $context['cpu_usage'] ?? null,
            'url' => $context['url'] ?? null,
            'method' => $context['method'] ?? null,
            'status_code' => $context['status_code'] ?? null,
        ];

        $this->performanceData[] = $data;

        // 限制缓存大小
        if (count($this->performanceData) > $this->maxCacheSize) {
            array_shift($this->performanceData);
        }
    }

    /**
     * 检查阈值
     */
    private function checkThresholds(LogRecord $record, array $context): void
    {
        $warnings = [];

        // 检查请求时间
        if (isset($context['duration'])) {
            $duration = (float) $context['duration'];
            if ($duration > $this->thresholds['slow_request']) {
                $warnings[] = "Slow request detected: {$duration}s (threshold: {$this->thresholds['slow_request']}s)";
            }
        }

        // 检查内存使用
        if (isset($context['memory_usage'])) {
            $memoryMB = (float) $context['memory_usage'] / 1024 / 1024;
            if ($memoryMB > $this->thresholds['memory_usage']) {
                $warnings[] = "High memory usage: {$memoryMB}MB (threshold: {$this->thresholds['memory_usage']}MB)";
            }
        }

        // 检查CPU使用
        if (isset($context['cpu_usage'])) {
            $cpuUsage = (float) $context['cpu_usage'];
            if ($cpuUsage > $this->thresholds['cpu_usage']) {
                $warnings[] = "High CPU usage: {$cpuUsage}% (threshold: {$this->thresholds['cpu_usage']}%)";
            }
        }

        // 记录警告
        foreach ($warnings as $warning) {
            $warningRecord = new LogRecord(
                LogLevel::WARNING,
                $warning,
                array_merge($context, ['performance_warning' => true]),
                $record->getDatetime(),
                $record->getCaller()
            );
            
            $this->baseHandler->handle($warningRecord);
        }
    }

    /**
     * 增强日志记录
     */
    private function enhanceRecord(LogRecord $record): LogRecord
    {
        $extra = $record->getExtra();
        
        // 添加当前性能信息
        $extra['current_memory'] = memory_get_usage(true);
        $extra['peak_memory'] = memory_get_peak_usage(true);
        
        // 添加统计信息
        if ($this->isPerformanceLog($record)) {
            $extra['performance_stats'] = [
                'total_requests' => $this->stats['total_requests'],
                'avg_duration' => round($this->stats['avg_duration'], 3),
                'slow_requests_ratio' => $this->stats['total_requests'] > 0 
                    ? round($this->stats['slow_requests'] / $this->stats['total_requests'] * 100, 2) 
                    : 0,
            ];
        }

        $record->setExtra($extra);
        
        return $record;
    }

    /**
     * 记录最终统计信息
     */
    private function logFinalStats(): void
    {
        if ($this->stats['total_requests'] === 0) {
            return;
        }

        $finalStats = [
            'total_requests' => $this->stats['total_requests'],
            'slow_requests' => $this->stats['slow_requests'],
            'error_requests' => $this->stats['error_requests'],
            'avg_duration' => round($this->stats['avg_duration'], 3),
            'max_duration' => round($this->stats['max_duration'], 3),
            'min_duration' => $this->stats['min_duration'] === PHP_FLOAT_MAX ? 0 : round($this->stats['min_duration'], 3),
            'slow_requests_ratio' => round($this->stats['slow_requests'] / $this->stats['total_requests'] * 100, 2),
            'error_rate' => round($this->stats['error_requests'] / $this->stats['total_requests'] * 100, 2),
        ];

        $statsRecord = new LogRecord(
            LogLevel::INFO,
            'Performance Summary',
            ['performance_summary' => $finalStats],
            new \DateTimeImmutable()
        );

        $this->baseHandler->handle($statsRecord);
    }

    /**
     * 获取统计数据
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * 获取性能数据
     */
    public function getPerformanceData(): array
    {
        return $this->performanceData;
    }

    /**
     * 获取慢请求数据
     */
    public function getSlowRequests(): array
    {
        return array_filter($this->performanceData, function($data) {
            return isset($data['duration']) && $data['duration'] > $this->thresholds['slow_request'];
        });
    }

    /**
     * 重置统计数据
     */
    public function resetStats(): void
    {
        $this->stats = [
            'total_requests' => 0,
            'slow_requests' => 0,
            'error_requests' => 0,
            'total_duration' => 0.0,
            'max_duration' => 0.0,
            'min_duration' => PHP_FLOAT_MAX,
            'avg_duration' => 0.0,
        ];
        
        $this->performanceData = [];
    }

    /**
     * 设置阈值
     */
    public function setThreshold(string $key, float $value): void
    {
        $this->thresholds[$key] = $value;
    }

    /**
     * 获取阈值
     */
    public function getThreshold(string $key): ?float
    {
        return $this->thresholds[$key] ?? null;
    }

    /**
     * 获取所有阈值
     */
    public function getThresholds(): array
    {
        return $this->thresholds;
    }
}