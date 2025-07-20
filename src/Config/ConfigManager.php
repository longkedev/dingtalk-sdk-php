<?php

declare(strict_types=1);

namespace DingTalk\Config;

use DingTalk\Contracts\ConfigInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * 配置管理器
 * 
 * 负责管理SDK的所有配置项
 */
class ConfigManager implements ConfigInterface
{
    /**
     * 配置数据
     */
    private array $config = [];

    /**
     * 默认配置
     */
    private array $defaults = [];

    /**
     * 配置缓存
     */
    private array $configCache = [];

    /**
     * 当前环境
     */
    private string $environment;

    /**
     * 加密密钥
     */
    private ?string $encryptionKey = null;

    /**
     * 敏感配置键列表
     */
    private array $sensitiveKeys = [
        'app_secret',
        'cache.redis.password',
        'security.encrypt_key',
    ];

    /**
     * 获取默认配置
     */
    private function getDefaults(): array
    {
        return [
            'app_key' => '',
            'app_secret' => '',
            'corp_id' => '',
            'agent_id' => '',
            'api_version' => 'auto', // auto, v1, v2
            'timeout' => 30,
            'connect_timeout' => 10,
            'retries' => 3,
            'user_agent' => 'DingTalk-PHP-SDK/1.0.0',
            'debug' => false,
            'cache' => [
                'driver' => 'file',
                'prefix' => 'dingtalk_',
                'ttl' => 7200,
                'path' => sys_get_temp_dir() . '/dingtalk_cache',
                'redis' => [
                    'host' => '127.0.0.1',
                    'port' => 6379,
                    'password' => '',
                    'database' => 0,
                    'timeout' => 5,
                ],
            ],
            'log' => [
                'enabled' => true,
                'level' => 'info',
                'file' => '',
                'max_files' => 30,
                'max_size' => 10485760, // 10MB
            ],
            'rate_limit' => [
                'enabled' => true,
                'requests_per_minute' => 1000,
                'burst_limit' => 100,
            ],
            'circuit_breaker' => [
                'enabled' => true,
                'failure_threshold' => 5,
                'recovery_timeout' => 60,
                'expected_exception_types' => [],
            ],
            'security' => [
                'encrypt_sensitive_data' => true,
                'validate_ssl' => true,
                'allowed_hosts' => [
                    'oapi.dingtalk.com',
                    'api.dingtalk.com',
                ],
            ],
            'monitoring' => [
                'enabled' => false,
                'metrics_endpoint' => '',
                'alert_webhook' => '',
            ],
        ];
    }

    /**
     * 构造函数
     * 
     * @param array|string $config 配置数组或配置文件路径
     * @param string $environment 环境名称
     * @param string|null $encryptionKey 加密密钥
     */
    public function __construct($config = [], string $environment = 'production', ?string $encryptionKey = null)
    {
        $this->environment = $environment;
        $this->encryptionKey = $encryptionKey;
        $this->defaults = $this->getDefaults();
        
        // 加载配置
        if (is_string($config)) {
            $this->config = $this->loadConfigFromFile($config);
        } else {
            $this->config = $config;
        }
        
        // 合并默认配置
        $this->config = array_merge($this->defaults, $this->config);
        
        // 加载环境变量
        $this->loadEnvironmentVariables();
        
        // 加载环境特定配置
        $this->loadEnvironmentConfig();
        
        // 解密敏感信息
        $this->decryptSensitiveData();
        
        $this->validateConfig();
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        return $this->getNestedValue($this->config, $key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->setNestedValue($this->config, $key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->hasNestedKey($this->config, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->config;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(array $config): void
    {
        $this->config = array_merge_recursive($this->config, $config);
        $this->validateConfig();
    }

    /**
     * 获取API版本
     */
    public function getApiVersion(): string
    {
        $version = $this->get('api_version', 'auto');
        
        if ($version === 'auto') {
            return $this->detectApiVersion();
        }
        
        return $version;
    }

    /**
     * 获取API基础URL
     */
    public function getApiBaseUrl(): string
    {
        $version = $this->getApiVersion();
        
        return $version === 'v2' 
            ? 'https://api.dingtalk.com'
            : 'https://oapi.dingtalk.com';
    }

    /**
     * 检测API版本
     */
    private function detectApiVersion(): string
    {
        // 这里可以实现版本检测逻辑
        // 例如：检查应用类型、功能需求等
        // 暂时默认返回v1以确保兼容性
        return 'v1';
    }

    /**
     * 验证配置
     */
    private function validateConfig(): void
    {
        $required = ['app_key', 'app_secret'];
        
        foreach ($required as $key) {
            if (empty($this->get($key))) {
                throw new InvalidArgumentException("Configuration key '{$key}' is required");
            }
        }

        // 验证API版本
        $apiVersion = $this->get('api_version');
        if (!in_array($apiVersion, ['auto', 'v1', 'v2'])) {
            throw new InvalidArgumentException("Invalid api_version: {$apiVersion}");
        }

        // 验证缓存驱动
        $cacheDriver = $this->get('cache.driver');
        if (!in_array($cacheDriver, ['file', 'redis', 'memory'])) {
            throw new InvalidArgumentException("Invalid cache driver: {$cacheDriver}");
        }

        // 验证日志级别
        $logLevel = $this->get('log.level');
        if (!in_array($logLevel, ['debug', 'info', 'warning', 'error'])) {
            throw new InvalidArgumentException("Invalid log level: {$logLevel}");
        }
    }

    /**
     * 获取嵌套值
     */
    private function getNestedValue(array $array, string $key, $default = null)
    {
        if (strpos($key, '.') === false) {
            return $array[$key] ?? $default;
        }

        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置嵌套值
     */
    private function setNestedValue(array &$array, string $key, $value): void
    {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    /**
     * 检查嵌套键是否存在
     */
    private function hasNestedKey(array $array, string $key): bool
    {
        if (strpos($key, '.') === false) {
            return array_key_exists($key, $array);
        }

        $keys = explode('.', $key);
        $current = $array;

        foreach ($keys as $k) {
            if (!is_array($current) || !array_key_exists($k, $current)) {
                return false;
            }
            $current = $current[$k];
        }

        return true;
    }
    
    /**
     * 从文件加载配置
     * 
     * @param string $filePath 配置文件路径
     * @return array
     * @throws RuntimeException
     */
    private function loadConfigFromFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("配置文件不存在: {$filePath}");
        }
        
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        switch ($extension) {
            case 'php':
                $config = require $filePath;
                if (!is_array($config)) {
                    throw new RuntimeException("配置文件必须返回数组: {$filePath}");
                }
                return $config;
                
            case 'json':
                $content = file_get_contents($filePath);
                $config = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException("JSON配置文件解析失败: " . json_last_error_msg());
                }
                return $config;
                
            default:
                throw new RuntimeException("不支持的配置文件格式: {$extension}");
        }
    }
    
    /**
     * 加载环境变量
     */
    private function loadEnvironmentVariables(): void
    {
        $envMappings = [
            'DINGTALK_APP_KEY' => 'app_key',
            'DINGTALK_APP_SECRET' => 'app_secret',
            'DINGTALK_API_VERSION' => 'api.version',
            'DINGTALK_API_TIMEOUT' => 'api.timeout',
            'DINGTALK_CACHE_DRIVER' => 'cache.driver',
            'DINGTALK_CACHE_TTL' => 'cache.ttl',
            'DINGTALK_REDIS_HOST' => 'cache.redis.host',
            'DINGTALK_REDIS_PORT' => 'cache.redis.port',
            'DINGTALK_REDIS_PASSWORD' => 'cache.redis.password',
            'DINGTALK_LOG_LEVEL' => 'log.level',
            'DINGTALK_LOG_PATH' => 'log.path',
            'DINGTALK_ENCRYPT_KEY' => 'security.encrypt_key',
        ];
        
        foreach ($envMappings as $envKey => $configKey) {
            $value = getenv($envKey);
            if ($value !== false) {
                // 类型转换
                if (is_numeric($value)) {
                    $value = is_float($value) ? (float)$value : (int)$value;
                } elseif (in_array(strtolower($value), ['true', 'false'])) {
                    $value = strtolower($value) === 'true';
                }
                
                $this->setNestedValue($this->config, $configKey, $value);
            }
        }
    }
    
    /**
     * 加载环境特定配置
     */
    private function loadEnvironmentConfig(): void
    {
        $configDir = dirname(__DIR__, 2) . '/config';
        $envConfigFile = $configDir . "/dingtalk.{$this->environment}.php";
        
        if (file_exists($envConfigFile)) {
            $envConfig = require $envConfigFile;
            if (is_array($envConfig)) {
                $this->config = array_merge($this->config, $envConfig);
            }
        }
    }
    
    /**
     * 解密敏感数据
     */
    private function decryptSensitiveData(): void
    {
        if (!$this->encryptionKey) {
            return;
        }
        
        foreach ($this->sensitiveKeys as $key) {
            if ($this->has($key)) {
                $value = $this->get($key);
                if (is_string($value) && $this->isEncrypted($value)) {
                    $decrypted = $this->decrypt($value);
                    $this->set($key, $decrypted);
                }
            }
        }
    }
    
    /**
     * 检查值是否已加密
     * 
     * @param string $value
     * @return bool
     */
    private function isEncrypted(string $value): bool
    {
        return strpos($value, 'encrypted:') === 0;
    }
    
    /**
     * 解密值
     * 
     * @param string $encryptedValue
     * @return string
     */
    private function decrypt(string $encryptedValue): string
    {
        $data = substr($encryptedValue, 10); // 移除 'encrypted:' 前缀
        $decoded = base64_decode($data);
        
        if ($decoded === false) {
            throw new RuntimeException("解密失败：无效的base64数据");
        }
        
        // 简单的XOR加密（生产环境应使用更安全的加密方法）
        $result = '';
        $keyLength = strlen($this->encryptionKey);
        for ($i = 0; $i < strlen($decoded); $i++) {
            $result .= $decoded[$i] ^ $this->encryptionKey[$i % $keyLength];
        }
        
        return $result;
    }
    
    /**
     * 加密敏感值
     * 
     * @param string $value
     * @return string
     */
    public function encrypt(string $value): string
    {
        if (!$this->encryptionKey) {
            throw new RuntimeException("未设置加密密钥");
        }
        
        // 简单的XOR加密（生产环境应使用更安全的加密方法）
        $result = '';
        $keyLength = strlen($this->encryptionKey);
        for ($i = 0; $i < strlen($value); $i++) {
            $result .= $value[$i] ^ $this->encryptionKey[$i % $keyLength];
        }
        
        return 'encrypted:' . base64_encode($result);
    }
    
    /**
     * 获取配置缓存
     * 
     * @param string $key
     * @return mixed
     */
    private function getFromCache(string $key)
    {
        return $this->configCache[$key] ?? null;
    }
    
    /**
     * 设置配置缓存
     * 
     * @param string $key
     * @param mixed $value
     */
    private function setToCache(string $key, $value): void
    {
        $this->configCache[$key] = $value;
    }
    
    /**
     * 清除配置缓存
     */
    public function clearCache(): void
    {
        $this->configCache = [];
    }
    
    /**
     * 获取当前环境
     * 
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }
    
    /**
     * 检查是否为开发环境
     * 
     * @return bool
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }
    
    /**
     * 检查是否为测试环境
     * 
     * @return bool
     */
    public function isTesting(): bool
    {
        return $this->environment === 'testing';
    }
    
    /**
     * 检查是否为生产环境
     * 
     * @return bool
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }
}