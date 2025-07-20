<?php

declare(strict_types=1);

namespace DingTalk\Log\Handlers;

use DingTalk\Log\LogHandlerInterface;
use DingTalk\Log\LogRecord;
use DingTalk\Log\Formatters\FormatterInterface;
use DingTalk\Log\Formatters\LineFormatter;
use Psr\Log\LogLevel;

/**
 * 文件日志处理器
 * 
 * 将日志写入文件
 */
class FileHandler implements LogHandlerInterface
{
    /**
     * 日志文件路径
     */
    private string $filePath;

    /**
     * 最小日志级别
     */
    private int $minLevel;

    /**
     * 文件权限
     */
    private int $filePermission;

    /**
     * 是否使用锁
     */
    private bool $useLocking;

    /**
     * 文件句柄
     */
    private $fileHandle;

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
     * 构造函数
     */
    public function __construct(
        string $filePath,
        string $minLevel = LogLevel::DEBUG,
        int $filePermission = 0644,
        bool $useLocking = true,
        FormatterInterface $formatter = null
    ) {
        $this->filePath = $filePath;
        $this->minLevel = self::LEVEL_MAP[$minLevel] ?? 100;
        $this->filePermission = $filePermission;
        $this->useLocking = $useLocking;
        $this->formatter = $formatter ?: new LineFormatter();
        
        $this->ensureDirectoryExists();
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
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    /**
     * 格式化日志记录
     */
    protected function format(LogRecord $record): string
    {
        return $this->formatter->format($record);
    }

    /**
     * 写入日志
     */
    protected function write(string $content): bool
    {
        if (!$this->openFile()) {
            return false;
        }

        $flags = $this->useLocking ? LOCK_EX : 0;
        $result = fwrite($this->fileHandle, $content);
        
        if ($this->useLocking) {
            fflush($this->fileHandle);
        }

        return $result !== false;
    }

    /**
     * 打开文件
     */
    private function openFile(): bool
    {
        if (is_resource($this->fileHandle)) {
            return true;
        }

        $this->fileHandle = fopen($this->filePath, 'a');
        
        if (!is_resource($this->fileHandle)) {
            return false;
        }

        // 设置文件权限
        if (file_exists($this->filePath)) {
            chmod($this->filePath, $this->filePermission);
        }

        return true;
    }

    /**
     * 确保目录存在
     */
    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->filePath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * 获取文件大小
     */
    public function getFileSize(): int
    {
        return file_exists($this->filePath) ? filesize($this->filePath) : 0;
    }

    /**
     * 轮转日志文件
     */
    public function rotate(int $maxFiles = 5): bool
    {
        if (!file_exists($this->filePath)) {
            return true;
        }

        $this->close();

        // 轮转现有文件
        for ($i = $maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $this->filePath . '.' . $i;
            $newFile = $this->filePath . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === $maxFiles - 1) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // 重命名当前文件
        if (file_exists($this->filePath)) {
            rename($this->filePath, $this->filePath . '.1');
        }

        return true;
    }

    /**
     * 清理旧日志文件
     */
    public function cleanup(int $days = 30): int
    {
        $dir = dirname($this->filePath);
        $basename = basename($this->filePath);
        $pattern = $dir . '/' . $basename . '.*';
        
        $files = glob($pattern);
        $deleted = 0;
        $cutoff = time() - ($days * 24 * 3600);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }

    /**
     * 获取文件路径
     */
    public function getFilePath(): string
    {
        return $this->filePath;
    }

    /**
     * 设置文件路径
     */
    public function setFilePath(string $filePath): void
    {
        $this->close();
        $this->filePath = $filePath;
        $this->ensureDirectoryExists();
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
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }
}