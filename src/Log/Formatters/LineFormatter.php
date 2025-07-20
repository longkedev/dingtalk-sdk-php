<?php

declare(strict_types=1);

namespace DingTalk\Log\Formatters;

use DingTalk\Log\LogRecord;

/**
 * 行格式化器
 * 
 * 将日志记录格式化为单行文本格式
 */
class LineFormatter implements FormatterInterface
{
    /**
     * 默认格式模板
     */
    public const DEFAULT_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    /**
     * 简单格式模板
     */
    public const SIMPLE_FORMAT = "[%datetime%] %level_name%: %message%\n";

    /**
     * 详细格式模板
     */
    public const DETAILED_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra% %caller%\n";

    /**
     * 格式模板
     */
    private string $format;

    /**
     * 日期格式
     */
    private string $dateFormat;

    /**
     * 是否允许内联换行
     */
    private bool $allowInlineLineBreaks;

    /**
     * 是否忽略空上下文
     */
    private bool $ignoreEmptyContextAndExtra;

    /**
     * 最大行长度
     */
    private int $maxLineLength;

    /**
     * 构造函数
     */
    public function __construct(
        string $format = self::DEFAULT_FORMAT,
        string $dateFormat = 'Y-m-d H:i:s',
        bool $allowInlineLineBreaks = false,
        bool $ignoreEmptyContextAndExtra = false,
        int $maxLineLength = 0
    ) {
        $this->format = $format;
        $this->dateFormat = $dateFormat;
        $this->allowInlineLineBreaks = $allowInlineLineBreaks;
        $this->ignoreEmptyContextAndExtra = $ignoreEmptyContextAndExtra;
        $this->maxLineLength = $maxLineLength;
    }

    /**
     * {@inheritdoc}
     */
    public function format(LogRecord $record): string
    {
        $vars = $this->normalize($record);
        
        $output = $this->format;
        
        foreach ($vars as $var => $val) {
            if (strpos($output, '%' . $var . '%') !== false) {
                $output = str_replace('%' . $var . '%', $this->stringify($val), $output);
            }
        }

        // 处理换行符
        if (!$this->allowInlineLineBreaks) {
            $output = str_replace(["\r\n", "\r", "\n"], ' ', $output);
        }

        // 限制行长度
        if ($this->maxLineLength > 0 && strlen($output) > $this->maxLineLength) {
            $output = substr($output, 0, $this->maxLineLength - 3) . '...';
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records): string
    {
        $message = '';
        
        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

    /**
     * 标准化日志记录
     */
    protected function normalize(LogRecord $record): array
    {
        $vars = [
            'datetime' => $record->getFormattedDatetime($this->dateFormat),
            'channel' => 'dingtalk',
            'level_name' => strtoupper($record->getLevel()),
            'level' => $record->getLevel(),
            'message' => $record->getMessage(),
            'context' => $this->normalizeContext($record->getContext()),
            'extra' => $this->normalizeExtra($record->getExtra()),
            'caller' => $this->normalizeCaller($record->getCaller()),
        ];

        return $vars;
    }

    /**
     * 标准化上下文
     */
    protected function normalizeContext(array $context): string
    {
        if (empty($context)) {
            return $this->ignoreEmptyContextAndExtra ? '' : '[]';
        }

        return $this->toJson($context);
    }

    /**
     * 标准化额外信息
     */
    protected function normalizeExtra(array $extra): string
    {
        if (empty($extra)) {
            return $this->ignoreEmptyContextAndExtra ? '' : '[]';
        }

        return $this->toJson($extra);
    }

    /**
     * 标准化调用者信息
     */
    protected function normalizeCaller(array $caller): string
    {
        if (empty($caller)) {
            return '';
        }

        $file = basename($caller['file'] ?? 'unknown');
        $line = $caller['line'] ?? 0;
        $function = $caller['function'] ?? 'unknown';

        return "({$file}:{$line} in {$function})";
    }

    /**
     * 转换为JSON字符串
     */
    protected function toJson($data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 转换为字符串
     */
    protected function stringify($value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $this->toJson($value);
    }

    /**
     * 设置格式模板
     */
    public function setFormat(string $format): void
    {
        $this->format = $format;
    }

    /**
     * 获取格式模板
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * 设置日期格式
     */
    public function setDateFormat(string $dateFormat): void
    {
        $this->dateFormat = $dateFormat;
    }

    /**
     * 获取日期格式
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    /**
     * 设置是否允许内联换行
     */
    public function setAllowInlineLineBreaks(bool $allowInlineLineBreaks): void
    {
        $this->allowInlineLineBreaks = $allowInlineLineBreaks;
    }

    /**
     * 设置是否忽略空上下文
     */
    public function setIgnoreEmptyContextAndExtra(bool $ignoreEmptyContextAndExtra): void
    {
        $this->ignoreEmptyContextAndExtra = $ignoreEmptyContextAndExtra;
    }

    /**
     * 设置最大行长度
     */
    public function setMaxLineLength(int $maxLineLength): void
    {
        $this->maxLineLength = $maxLineLength;
    }
}