<?php

declare(strict_types=1);

namespace DingTalk\Log\Handlers;

use DingTalk\Log\LogHandlerInterface;
use DingTalk\Log\LogRecord;
use DingTalk\Log\Formatters\FormatterInterface;
use DingTalk\Log\Formatters\LineFormatter;
use Psr\Log\LogLevel;

/**
 * 控制台日志处理器
 * 
 * 将日志输出到控制台
 */
class ConsoleHandler implements LogHandlerInterface
{
    /**
     * 最小日志级别
     */
    private int $minLevel;

    /**
     * 是否使用颜色
     */
    private bool $useColors;

    /**
     * 输出流
     */
    private $stream;

    /**
     * 格式化器
     */
    private FormatterInterface $formatter;

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
     * 颜色映射
     */
    private const COLOR_MAP = [
        LogLevel::EMERGENCY => "\033[1;37;41m", // 白字红底
        LogLevel::ALERT => "\033[1;33;41m",     // 黄字红底
        LogLevel::CRITICAL => "\033[1;31m",     // 红字
        LogLevel::ERROR => "\033[0;31m",        // 红字
        LogLevel::WARNING => "\033[0;33m",      // 黄字
        LogLevel::NOTICE => "\033[0;36m",       // 青字
        LogLevel::INFO => "\033[0;32m",         // 绿字
        LogLevel::DEBUG => "\033[0;37m",        // 白字
    ];

    /**
     * 重置颜色
     */
    private const COLOR_RESET = "\033[0m";

    /**
     * 构造函数
     */
    public function __construct(
        string $minLevel = LogLevel::DEBUG,
        bool $useColors = null,
        $stream = null,
        FormatterInterface $formatter = null
    ) {
        $this->minLevel = self::LEVEL_MAP[$minLevel] ?? 100;
        $this->useColors = $useColors ?? $this->shouldUseColors();
        $this->stream = $stream ?: STDERR;
        $this->formatter = $formatter ?: new LineFormatter();
    }

    /**
     * {@inheritdoc}
     */
    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record->getLevel())) {
            return false;
        }

        $formatted = $this->format($record);
        
        return $this->write($formatted);
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
        $success = true;
        
        foreach ($records as $record) {
            if (!$this->handle($record)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        // 控制台处理器不需要关闭操作
    }

    /**
     * 格式化日志记录
     */
    protected function format(LogRecord $record): string
    {
        $formatted = $this->formatter->format($record);
        
        // 如果使用颜色，为日志级别添加颜色
        if ($this->useColors) {
            $level = $record->getLevel();
            $levelText = strtoupper($level);
            $color = self::COLOR_MAP[$level] ?? '';
            $coloredLevel = $color . $levelText . self::COLOR_RESET;
            
            // 替换格式化文本中的级别文本为带颜色的版本
            $formatted = str_replace($levelText, $coloredLevel, $formatted);
        }
        
        return $formatted;
    }

    /**
     * 格式化上下文信息
     */
    private function formatContext(array $context): string
    {
        // 对于控制台输出，简化上下文显示
        $simplified = [];
        
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $simplified[$key] = $value;
            } elseif (is_array($value) && count($value) <= 3) {
                $simplified[$key] = $value;
            } else {
                $simplified[$key] = '[' . gettype($value) . ']';
            }
        }
        
        if (empty($simplified)) {
            return '';
        }
        
        return json_encode($simplified, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 写入输出
     */
    protected function write(string $content): bool
    {
        return fwrite($this->stream, $content) !== false;
    }

    /**
     * 检查是否应该使用颜色
     */
    private function shouldUseColors(): bool
    {
        // 检查是否在终端环境
        if (!defined('STDOUT') || !is_resource(STDOUT)) {
            return false;
        }
        
        // 检查环境变量
        if (getenv('NO_COLOR') !== false) {
            return false;
        }
        
        if (getenv('FORCE_COLOR') !== false) {
            return true;
        }
        
        // 检查是否支持颜色
        if (function_exists('posix_isatty')) {
            return posix_isatty(STDOUT);
        }
        
        // Windows 检查
        if (DIRECTORY_SEPARATOR === '\\') {
            return getenv('ANSICON') !== false || 
                   getenv('ConEmuANSI') === 'ON' ||
                   getenv('TERM') === 'xterm';
        }
        
        return true;
    }

    /**
     * 设置是否使用颜色
     */
    public function setUseColors(bool $useColors): void
    {
        $this->useColors = $useColors;
    }

    /**
     * 获取是否使用颜色
     */
    public function getUseColors(): bool
    {
        return $this->useColors;
    }

    /**
     * 设置输出流
     */
    public function setStream($stream): void
    {
        $this->stream = $stream;
    }

    /**
     * 获取输出流
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * 获取格式化器
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    /**
     * 设置格式化器
     */
    public function setFormatter(FormatterInterface $formatter): void
    {
        $this->formatter = $formatter;
    }

    /**
     * 输出分隔线
     */
    public function writeSeparator(string $char = '-', int $length = 50): void
    {
        $line = str_repeat($char, $length) . PHP_EOL;
        $this->write($line);
    }

    /**
     * 输出标题
     */
    public function writeTitle(string $title): void
    {
        $length = strlen($title) + 4;
        $border = str_repeat('=', $length);
        
        $this->write($border . PHP_EOL);
        $this->write('  ' . $title . '  ' . PHP_EOL);
        $this->write($border . PHP_EOL);
    }

    /**
     * 输出表格
     */
    public function writeTable(array $headers, array $rows): void
    {
        if (empty($headers) || empty($rows)) {
            return;
        }
        
        // 计算列宽
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen((string) $cell));
            }
        }
        
        // 输出表头
        $this->writeTableRow($headers, $widths);
        $this->writeTableSeparator($widths);
        
        // 输出数据行
        foreach ($rows as $row) {
            $this->writeTableRow($row, $widths);
        }
    }

    /**
     * 输出表格行
     */
    private function writeTableRow(array $cells, array $widths): void
    {
        $line = '| ';
        foreach ($cells as $i => $cell) {
            $line .= str_pad((string) $cell, $widths[$i]) . ' | ';
        }
        $this->write($line . PHP_EOL);
    }

    /**
     * 输出表格分隔线
     */
    private function writeTableSeparator(array $widths): void
    {
        $line = '|-';
        foreach ($widths as $width) {
            $line .= str_repeat('-', $width) . '-|-';
        }
        $this->write(rtrim($line, '-|') . PHP_EOL);
    }
}