<?php

declare(strict_types=1);

namespace DingTalk\Exceptions;

/**
 * 错误上下文收集器
 * 
 * 收集和管理错误发生时的上下文信息，包括系统环境、
 * 请求信息、用户信息等，用于错误诊断和调试
 */
class ErrorContextCollector
{
    /**
     * 收集的上下文信息
     */
    private array $context = [];

    /**
     * 是否启用敏感信息收集
     */
    private bool $collectSensitiveInfo = false;

    /**
     * 敏感信息字段列表
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'secret',
        'token',
        'key',
        'authorization',
        'cookie',
        'session',
        'credential'
    ];

    /**
     * 构造函数
     */
    public function __construct(bool $collectSensitiveInfo = false)
    {
        $this->collectSensitiveInfo = $collectSensitiveInfo;
        $this->initializeBaseContext();
    }

    /**
     * 初始化基础上下文信息
     */
    private function initializeBaseContext(): void
    {
        $this->context = [
            'timestamp' => date('Y-m-d H:i:s'),
            'timezone' => date_default_timezone_get(),
            'system' => $this->collectSystemInfo(),
            'php' => $this->collectPhpInfo(),
            'sdk' => $this->collectSdkInfo(),
        ];
    }

    /**
     * 收集系统信息
     */
    private function collectSystemInfo(): array
    {
        return [
            'os' => PHP_OS,
            'os_family' => PHP_OS_FAMILY,
            'hostname' => gethostname() ?: 'unknown',
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }

    /**
     * 收集PHP信息
     */
    private function collectPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'version_id' => PHP_VERSION_ID,
            'sapi' => PHP_SAPI,
            'extensions' => $this->getRelevantExtensions(),
            'settings' => $this->getRelevantSettings(),
        ];
    }

    /**
     * 收集SDK信息
     */
    private function collectSdkInfo(): array
    {
        return [
            'version' => $this->getSdkVersion(),
            'name' => 'DingTalk PHP SDK',
            'user_agent' => $this->generateUserAgent(),
        ];
    }

    /**
     * 获取相关的PHP扩展
     */
    private function getRelevantExtensions(): array
    {
        $relevantExtensions = ['curl', 'json', 'openssl', 'mbstring', 'fileinfo'];
        $loadedExtensions = [];
        
        foreach ($relevantExtensions as $extension) {
            $loadedExtensions[$extension] = extension_loaded($extension);
        }
        
        return $loadedExtensions;
    }

    /**
     * 获取相关的PHP设置
     */
    private function getRelevantSettings(): array
    {
        return [
            'max_execution_time' => ini_get('max_execution_time'),
            'default_socket_timeout' => ini_get('default_socket_timeout'),
            'user_agent' => ini_get('user_agent'),
            'auto_detect_line_endings' => ini_get('auto_detect_line_endings'),
        ];
    }

    /**
     * 添加HTTP请求上下文
     */
    public function addHttpContext(array $requestInfo): self
    {
        $httpContext = [
            'method' => $requestInfo['method'] ?? 'unknown',
            'url' => $this->sanitizeUrl($requestInfo['url'] ?? ''),
            'headers' => $this->sanitizeHeaders($requestInfo['headers'] ?? []),
            'user_agent' => $requestInfo['user_agent'] ?? '',
            'content_type' => $requestInfo['content_type'] ?? '',
            'content_length' => $requestInfo['content_length'] ?? 0,
        ];

        if (isset($requestInfo['body']) && $this->collectSensitiveInfo) {
            $httpContext['body'] = $this->sanitizeRequestBody($requestInfo['body']);
        }

        $this->context['http'] = $httpContext;
        return $this;
    }

    /**
     * 添加API调用上下文
     */
    public function addApiContext(array $apiInfo): self
    {
        $this->context['api'] = [
            'endpoint' => $apiInfo['endpoint'] ?? '',
            'version' => $apiInfo['version'] ?? 'unknown',
            'method' => $apiInfo['method'] ?? '',
            'parameters' => $this->sanitizeParameters($apiInfo['parameters'] ?? []),
            'response_time' => $apiInfo['response_time'] ?? 0,
            'response_code' => $apiInfo['response_code'] ?? 0,
        ];
        
        return $this;
    }

    /**
     * 添加认证上下文
     */
    public function addAuthContext(array $authInfo): self
    {
        $authContext = [
            'app_type' => $authInfo['app_type'] ?? 'unknown',
            'corp_id' => $authInfo['corp_id'] ?? '',
            'app_key' => $this->collectSensitiveInfo ? ($authInfo['app_key'] ?? '') : '[HIDDEN]',
            'token_type' => $authInfo['token_type'] ?? 'access_token',
            'token_expires_at' => $authInfo['token_expires_at'] ?? null,
        ];

        if (!$this->collectSensitiveInfo) {
            unset($authContext['app_secret'], $authContext['access_token']);
        }

        $this->context['auth'] = $authContext;
        return $this;
    }

    /**
     * 添加用户上下文
     */
    public function addUserContext(array $userInfo): self
    {
        $this->context['user'] = [
            'user_id' => $userInfo['user_id'] ?? '',
            'union_id' => $userInfo['union_id'] ?? '',
            'department_id' => $userInfo['department_id'] ?? '',
            'role' => $userInfo['role'] ?? '',
            'locale' => $userInfo['locale'] ?? '',
        ];
        
        return $this;
    }

    /**
     * 添加错误上下文
     */
    public function addErrorContext(array $errorInfo): self
    {
        $this->context['error'] = [
            'code' => $errorInfo['code'] ?? '',
            'message' => $errorInfo['message'] ?? '',
            'type' => $errorInfo['type'] ?? 'unknown',
            'file' => $errorInfo['file'] ?? '',
            'line' => $errorInfo['line'] ?? 0,
            'trace' => $this->sanitizeStackTrace($errorInfo['trace'] ?? []),
        ];
        
        return $this;
    }

    /**
     * 添加自定义上下文
     */
    public function addCustomContext(string $key, $value): self
    {
        $this->context['custom'][$key] = $this->sanitizeValue($value);
        return $this;
    }

    /**
     * 获取所有上下文信息
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * 获取特定类型的上下文信息
     */
    public function getContextByType(string $type): array
    {
        return $this->context[$type] ?? [];
    }

    /**
     * 清除上下文信息
     */
    public function clearContext(): self
    {
        $this->initializeBaseContext();
        return $this;
    }

    /**
     * 设置是否收集敏感信息
     */
    public function setCollectSensitiveInfo(bool $collect): self
    {
        $this->collectSensitiveInfo = $collect;
        return $this;
    }

    /**
     * 导出上下文为JSON
     */
    public function toJson(): string
    {
        return json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 导出上下文为数组
     */
    public function toArray(): array
    {
        return $this->context;
    }

    /**
     * 清理URL中的敏感信息
     */
    private function sanitizeUrl(string $url): string
    {
        if (!$this->collectSensitiveInfo) {
            // 移除查询参数中的敏感信息
            $parsedUrl = parse_url($url);
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                $queryParams = $this->sanitizeParameters($queryParams);
                $parsedUrl['query'] = http_build_query($queryParams);
                $url = $this->buildUrl($parsedUrl);
            }
        }
        
        return $url;
    }

    /**
     * 清理HTTP头中的敏感信息
     */
    private function sanitizeHeaders(array $headers): array
    {
        if (!$this->collectSensitiveInfo) {
            foreach ($headers as $key => $value) {
                if ($this->isSensitiveField($key)) {
                    $headers[$key] = '[HIDDEN]';
                }
            }
        }
        
        return $headers;
    }

    /**
     * 清理请求体中的敏感信息
     */
    private function sanitizeRequestBody($body): string
    {
        if (is_string($body)) {
            if (!$this->collectSensitiveInfo) {
                // 尝试解析JSON并清理敏感字段
                $decoded = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $decoded = $this->sanitizeParameters($decoded);
                    return json_encode($decoded);
                }
            }
            return $body;
        }
        
        return $this->sanitizeValue($body);
    }

    /**
     * 清理参数中的敏感信息
     */
    private function sanitizeParameters(array $parameters): array
    {
        if (!$this->collectSensitiveInfo) {
            foreach ($parameters as $key => $value) {
                if ($this->isSensitiveField($key)) {
                    $parameters[$key] = '[HIDDEN]';
                } elseif (is_array($value)) {
                    $parameters[$key] = $this->sanitizeParameters($value);
                }
            }
        }
        
        return $parameters;
    }

    /**
     * 清理堆栈跟踪信息
     */
    private function sanitizeStackTrace(array $trace): array
    {
        $sanitizedTrace = [];
        
        foreach ($trace as $frame) {
            $sanitizedFrame = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
                'type' => $frame['type'] ?? null,
            ];
            
            // 不包含参数信息以避免敏感数据泄露
            if ($this->collectSensitiveInfo && isset($frame['args'])) {
                $sanitizedFrame['args'] = $this->sanitizeValue($frame['args']);
            }
            
            $sanitizedTrace[] = $sanitizedFrame;
        }
        
        return $sanitizedTrace;
    }

    /**
     * 清理任意值
     */
    private function sanitizeValue($value): string
    {
        if (is_object($value)) {
            return get_class($value);
        }
        
        if (is_array($value)) {
            return '[Array(' . count($value) . ')]';
        }
        
        if (is_resource($value)) {
            return '[Resource]';
        }
        
        return (string) $value;
    }

    /**
     * 检查字段是否为敏感字段
     */
    private function isSensitiveField(string $fieldName): bool
    {
        $fieldName = strtolower($fieldName);
        
        foreach (self::SENSITIVE_FIELDS as $sensitiveField) {
            if (strpos($fieldName, $sensitiveField) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 构建URL
     */
    private function buildUrl(array $parsedUrl): string
    {
        $url = '';
        
        if (isset($parsedUrl['scheme'])) {
            $url .= $parsedUrl['scheme'] . '://';
        }
        
        if (isset($parsedUrl['host'])) {
            $url .= $parsedUrl['host'];
        }
        
        if (isset($parsedUrl['port'])) {
            $url .= ':' . $parsedUrl['port'];
        }
        
        if (isset($parsedUrl['path'])) {
            $url .= $parsedUrl['path'];
        }
        
        if (isset($parsedUrl['query'])) {
            $url .= '?' . $parsedUrl['query'];
        }
        
        if (isset($parsedUrl['fragment'])) {
            $url .= '#' . $parsedUrl['fragment'];
        }
        
        return $url;
    }

    /**
     * 获取SDK版本
     */
    private function getSdkVersion(): string
    {
        // 这里可以从composer.json或其他地方读取版本信息
        return '1.0.0';
    }

    /**
     * 生成User-Agent
     */
    private function generateUserAgent(): string
    {
        return sprintf(
            'DingTalk-PHP-SDK/%s (PHP/%s; %s)',
            $this->getSdkVersion(),
            PHP_VERSION,
            PHP_OS
        );
    }
}