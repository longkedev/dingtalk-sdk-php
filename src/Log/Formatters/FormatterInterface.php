<?php

declare(strict_types=1);

namespace DingTalk\Log\Formatters;

use DingTalk\Log\LogRecord;

/**
 * 日志格式化器接口
 * 
 * 定义日志格式化的标准方法
 */
interface FormatterInterface
{
    /**
     * 格式化日志记录
     * 
     * @param LogRecord $record 日志记录
     * @return string 格式化后的字符串
     */
    public function format(LogRecord $record): string;

    /**
     * 批量格式化日志记录
     * 
     * @param LogRecord[] $records 日志记录数组
     * @return string 格式化后的字符串
     */
    public function formatBatch(array $records): string;
}