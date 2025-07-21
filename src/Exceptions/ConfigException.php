<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 配置异常类
 * 
 * 用于处理配置文件、配置项、环境变量等配置相关的异常
 */
class ConfigException extends DingTalkException
{
    /**
     * 配置文件不存在
     */
    public const CONFIG_FILE_NOT_FOUND = 'CONFIG_FILE_NOT_FOUND';
    
    /**
     * 配置文件格式错误
     */
    public const INVALID_CONFIG_FORMAT = 'INVALID_CONFIG_FORMAT';
    
    /**
     * 配置项缺失
     */
    public const MISSING_CONFIG_KEY = 'MISSING_CONFIG_KEY';
    
    /**
     * 配置值无效
     */
    public const INVALID_CONFIG_VALUE = 'INVALID_CONFIG_VALUE';
    
    /**
     * 环境变量缺失
     */
    public const MISSING_ENV_VAR = 'MISSING_ENV_VAR';
    
    /**
     * 配置权限错误
     */
    public const CONFIG_PERMISSION_DENIED = 'CONFIG_PERMISSION_DENIED';
    
    /**
     * 配置文件路径
     */
    private string $configFile = '';
    
    /**
     * 配置键名
     */
    private string $configKey = '';
    
    /**
     * 配置值
     */
    private $configValue = null;
    
    /**
     * 创建配置文件不存在异常
     */
    public static function configFileNotFound(string $filePath, ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Configuration file not found: {$filePath}",
            self::CONFIG_FILE_NOT_FOUND,
            ['file_path' => $filePath],
            0,
            $previous
        );
        
        $exception->setConfigFile($filePath);
        
        return $exception;
    }
    
    /**
     * 创建配置文件格式错误异常
     */
    public static function invalidConfigFormat(string $filePath, string $expectedFormat = '', ?\Throwable $previous = null): self
    {
        $message = "Invalid configuration file format: {$filePath}";
        if ($expectedFormat) {
            $message .= ". Expected format: {$expectedFormat}";
        }
        
        $exception = new self(
            $message,
            self::INVALID_CONFIG_FORMAT,
            [
                'file_path' => $filePath,
                'expected_format' => $expectedFormat
            ],
            0,
            $previous
        );
        
        $exception->setConfigFile($filePath);
        
        return $exception;
    }
    
    /**
     * 创建配置项缺失异常
     */
    public static function missingConfigKey(string $key, string $filePath = '', ?\Throwable $previous = null): self
    {
        $message = "Missing required configuration key: {$key}";
        if ($filePath) {
            $message .= " in file: {$filePath}";
        }
        
        $exception = new self(
            $message,
            self::MISSING_CONFIG_KEY,
            [
                'config_key' => $key,
                'file_path' => $filePath
            ],
            0,
            $previous
        );
        
        $exception->setConfigKey($key);
        $exception->setConfigFile($filePath);
        
        return $exception;
    }
    
    /**
     * 创建配置值无效异常
     */
    public static function invalidConfigValue(
        string $key,
        $value,
        string $expectedType = '',
        array $allowedValues = [],
        ?\Throwable $previous = null
    ): self {
        $message = "Invalid configuration value for key '{$key}': " . json_encode($value);
        
        if ($expectedType) {
            $message .= ". Expected type: {$expectedType}";
        }
        
        if (!empty($allowedValues)) {
            $message .= ". Allowed values: " . implode(', ', $allowedValues);
        }
        
        $exception = new self(
            $message,
            self::INVALID_CONFIG_VALUE,
            [
                'config_key' => $key,
                'config_value' => $value,
                'expected_type' => $expectedType,
                'allowed_values' => $allowedValues
            ],
            0,
            $previous
        );
        
        $exception->setConfigKey($key);
        $exception->setConfigValue($value);
        
        return $exception;
    }
    
    /**
     * 创建环境变量缺失异常
     */
    public static function missingEnvVar(string $varName, ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Missing required environment variable: {$varName}",
            self::MISSING_ENV_VAR,
            ['env_var' => $varName],
            0,
            $previous
        );
        
        $exception->setConfigKey($varName);
        
        return $exception;
    }
    
    /**
     * 创建配置权限错误异常
     */
    public static function configPermissionDenied(string $filePath, string $operation = 'read', ?\Throwable $previous = null): self
    {
        $exception = new self(
            "Permission denied to {$operation} configuration file: {$filePath}",
            self::CONFIG_PERMISSION_DENIED,
            [
                'file_path' => $filePath,
                'operation' => $operation
            ],
            0,
            $previous
        );
        
        $exception->setConfigFile($filePath);
        
        return $exception;
    }
    
    /**
     * 设置配置文件路径
     */
    public function setConfigFile(string $filePath): void
    {
        $this->configFile = $filePath;
    }
    
    /**
     * 获取配置文件路径
     */
    public function getConfigFile(): string
    {
        return $this->configFile;
    }
    
    /**
     * 设置配置键名
     */
    public function setConfigKey(string $key): void
    {
        $this->configKey = $key;
    }
    
    /**
     * 获取配置键名
     */
    public function getConfigKey(): string
    {
        return $this->configKey;
    }
    
    /**
     * 设置配置值
     */
    public function setConfigValue($value): void
    {
        $this->configValue = $value;
    }
    
    /**
     * 获取配置值
     */
    public function getConfigValue()
    {
        return $this->configValue;
    }
    
    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'config_file' => $this->configFile,
            'config_key' => $this->configKey,
            'config_value' => $this->configValue,
        ]);
    }
}