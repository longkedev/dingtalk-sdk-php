<?php

declare(strict_types=1);

namespace DingTalk\Log\Handlers;

use DingTalk\Log\LogRecord;
use Psr\Log\LogLevel;

/**
 * 轮转文件日志处理器
 * 
 * 支持日志文件轮转和归档
 */
class RotatingFileHandler extends FileHandler
{
    /**
     * 最大文件数量
     */
    private int $maxFiles;

    /**
     * 最大文件大小（字节）
     */
    private int $maxFileSize;

    /**
     * 轮转类型
     */
    private string $rotationType;

    /**
     * 日期格式
     */
    private string $dateFormat;

    /**
     * 轮转类型常量
     */
    public const ROTATION_SIZE = 'size';
    public const ROTATION_DAILY = 'daily';
    public const ROTATION_WEEKLY = 'weekly';
    public const ROTATION_MONTHLY = 'monthly';

    /**
     * 构造函数
     */
    public function __construct(
        string $filePath,
        string $minLevel = LogLevel::DEBUG,
        int $maxFiles = 5,
        int $maxFileSize = 10 * 1024 * 1024, // 10MB
        string $rotationType = self::ROTATION_SIZE,
        int $filePermission = 0644,
        bool $useLocking = true
    ) {
        parent::__construct($filePath, $minLevel, $filePermission, $useLocking);
        
        $this->maxFiles = max(1, $maxFiles);
        $this->maxFileSize = max(1024, $maxFileSize); // 最小1KB
        $this->rotationType = $rotationType;
        $this->dateFormat = $this->getDateFormat($rotationType);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record->getLevel())) {
            return false;
        }

        // 检查是否需要轮转
        if ($this->shouldRotate()) {
            $this->rotate();
        }

        return parent::handle($record);
    }

    /**
     * 检查是否需要轮转
     */
    private function shouldRotate(): bool
    {
        if (!file_exists($this->filePath)) {
            return false;
        }

        switch ($this->rotationType) {
            case self::ROTATION_SIZE:
                return filesize($this->filePath) >= $this->maxFileSize;
                
            case self::ROTATION_DAILY:
                return $this->shouldRotateByDate('Y-m-d');
                
            case self::ROTATION_WEEKLY:
                return $this->shouldRotateByDate('Y-W');
                
            case self::ROTATION_MONTHLY:
                return $this->shouldRotateByDate('Y-m');
                
            default:
                return false;
        }
    }

    /**
     * 检查是否需要按日期轮转
     */
    private function shouldRotateByDate(string $format): bool
    {
        $currentDate = date($format);
        $fileDate = date($format, filemtime($this->filePath));
        
        return $currentDate !== $fileDate;
    }

    /**
     * 执行轮转
     */
    private function rotate(): void
    {
        // 关闭当前文件句柄
        $this->close();

        // 生成轮转文件名
        $rotatedFile = $this->generateRotatedFileName();

        // 移动当前文件
        if (file_exists($this->filePath)) {
            rename($this->filePath, $rotatedFile);
        }

        // 清理旧文件
        $this->cleanupOldFiles();

        // 压缩旧文件（如果支持）
        if (function_exists('gzopen') && $this->shouldCompress()) {
            $this->compressFile($rotatedFile);
        }
    }

    /**
     * 生成轮转文件名
     */
    private function generateRotatedFileName(): string
    {
        $pathInfo = pathinfo($this->filePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        $timestamp = $this->rotationType === self::ROTATION_SIZE 
            ? date('Y-m-d_H-i-s')
            : date($this->dateFormat);

        $rotatedName = $filename . '_' . $timestamp;
        
        if ($extension) {
            $rotatedName .= '.' . $extension;
        }

        return $directory . DIRECTORY_SEPARATOR . $rotatedName;
    }

    /**
     * 清理旧文件
     */
    private function cleanupOldFiles(): void
    {
        $pathInfo = pathinfo($this->filePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        $pattern = $filename . '_*';
        if ($extension) {
            $pattern .= '.' . $extension;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . $pattern);
        
        if (count($files) <= $this->maxFiles) {
            return;
        }

        // 按修改时间排序
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });

        // 删除最旧的文件
        $filesToDelete = array_slice($files, 0, count($files) - $this->maxFiles);
        
        foreach ($filesToDelete as $file) {
            @unlink($file);
            
            // 同时删除对应的压缩文件
            $compressedFile = $file . '.gz';
            if (file_exists($compressedFile)) {
                @unlink($compressedFile);
            }
        }
    }

    /**
     * 压缩文件
     */
    private function compressFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $compressedPath = $filePath . '.gz';
        
        $source = fopen($filePath, 'rb');
        $dest = gzopen($compressedPath, 'wb9');
        
        if (!$source || !$dest) {
            return false;
        }

        while (!feof($source)) {
            gzwrite($dest, fread($source, 8192));
        }

        fclose($source);
        gzclose($dest);

        // 删除原文件
        unlink($filePath);

        return true;
    }

    /**
     * 检查是否应该压缩
     */
    private function shouldCompress(): bool
    {
        // 只有当文件大于1MB时才压缩
        return $this->maxFileSize > 1024 * 1024;
    }

    /**
     * 获取日期格式
     */
    private function getDateFormat(string $rotationType): string
    {
        switch ($rotationType) {
            case self::ROTATION_DAILY:
                return 'Y-m-d';
            case self::ROTATION_WEEKLY:
                return 'Y-W';
            case self::ROTATION_MONTHLY:
                return 'Y-m';
            default:
                return 'Y-m-d_H-i-s';
        }
    }

    /**
     * 获取最大文件数量
     */
    public function getMaxFiles(): int
    {
        return $this->maxFiles;
    }

    /**
     * 设置最大文件数量
     */
    public function setMaxFiles(int $maxFiles): void
    {
        $this->maxFiles = max(1, $maxFiles);
    }

    /**
     * 获取最大文件大小
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * 设置最大文件大小
     */
    public function setMaxFileSize(int $maxFileSize): void
    {
        $this->maxFileSize = max(1024, $maxFileSize);
    }

    /**
     * 获取轮转类型
     */
    public function getRotationType(): string
    {
        return $this->rotationType;
    }

    /**
     * 设置轮转类型
     */
    public function setRotationType(string $rotationType): void
    {
        $this->rotationType = $rotationType;
        $this->dateFormat = $this->getDateFormat($rotationType);
    }

    /**
     * 强制轮转
     */
    public function forceRotate(): void
    {
        $this->rotate();
    }

    /**
     * 获取当前日志文件大小
     */
    public function getCurrentFileSize(): int
    {
        return file_exists($this->filePath) ? filesize($this->filePath) : 0;
    }

    /**
     * 获取轮转历史文件列表
     */
    public function getRotatedFiles(): array
    {
        $pathInfo = pathinfo($this->filePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';

        $pattern = $filename . '_*';
        if ($extension) {
            $pattern .= '.' . $extension;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . $pattern);
        
        // 按修改时间倒序排序
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        return $files;
    }
}