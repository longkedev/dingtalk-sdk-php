<?php

declare(strict_types=1);

namespace DingTalk\Log;

/**
 * 日志处理器接口
 * 
 * 定义日志处理器的标准方法
 */
interface LogHandlerInterface
{
    /**
     * 处理日志记录
     * 
     * @param LogRecord $record 日志记录
     * @return bool 是否处理成功
     */
    public function handle(LogRecord $record): bool;

    /**
     * 检查是否可以处理指定级别的日志
     * 
     * @param string $level 日志级别
     * @return bool
     */
    public function isHandling(string $level): bool;

    /**
     * 批量处理日志记录
     * 
     * @param LogRecord[] $records 日志记录数组
     * @return bool 是否全部处理成功
     */
    public function handleBatch(array $records): bool;

    /**
     * 关闭处理器
     * 
     * @return void
     */
    public function close(): void;
}