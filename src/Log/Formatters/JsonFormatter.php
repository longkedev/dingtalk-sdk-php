<?php

declare(strict_types=1);

namespace DingTalk\Log\Formatters;

use DingTalk\Log\LogRecord;

/**
 * JSON格式化器
 * 
 * 将日志记录格式化为JSON格式
 */
class JsonFormatter implements FormatterInterface
{
    /**
     * 是否美化输出
     */
    private bool $prettyPrint;

    /**
     * 是否包含调用者信息
     */
    private bool $includeCaller;

    /**
     * 是否包含额外信息
     */
    private bool $includeExtra;

    /**
     * JSON编码选项
     */
    private int $jsonOptions;

    /**
     * 构造函数
     */
    public function __construct(
        bool $prettyPrint = false,
        bool $includeCaller = true,
        bool $includeExtra = true,
        int $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) {
        $this->prettyPrint = $prettyPrint;
        $this->includeCaller = $includeCaller;
        $this->includeExtra = $includeExtra;
        $this->jsonOptions = $jsonOptions;
        
        if ($prettyPrint) {
            $this->jsonOptions |= JSON_PRETTY_PRINT;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function format(LogRecord $record): string
    {
        $data = [
            'timestamp' => $record->getDatetime()->format(\DateTime::ATOM),
            'level' => $record->getLevel(),
            'message' => $record->getMessage(),
            'context' => $record->getContext(),
        ];

        if ($this->includeCaller) {
            $data['caller'] = $record->getCaller();
        }

        if ($this->includeExtra) {
            $data['extra'] = $record->getExtra();
        }

        return json_encode($data, $this->jsonOptions) . PHP_EOL;
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records): string
    {
        $formatted = [];
        
        foreach ($records as $record) {
            $formatted[] = rtrim($this->format($record));
        }

        return '[' . implode(',', $formatted) . ']' . PHP_EOL;
    }

    /**
     * 设置是否美化输出
     */
    public function setPrettyPrint(bool $prettyPrint): void
    {
        $this->prettyPrint = $prettyPrint;
        
        if ($prettyPrint) {
            $this->jsonOptions |= JSON_PRETTY_PRINT;
        } else {
            $this->jsonOptions &= ~JSON_PRETTY_PRINT;
        }
    }

    /**
     * 设置是否包含调用者信息
     */
    public function setIncludeCaller(bool $includeCaller): void
    {
        $this->includeCaller = $includeCaller;
    }

    /**
     * 设置是否包含额外信息
     */
    public function setIncludeExtra(bool $includeExtra): void
    {
        $this->includeExtra = $includeExtra;
    }

    /**
     * 设置JSON编码选项
     */
    public function setJsonOptions(int $options): void
    {
        $this->jsonOptions = $options;
        
        if ($this->prettyPrint) {
            $this->jsonOptions |= JSON_PRETTY_PRINT;
        }
    }
}