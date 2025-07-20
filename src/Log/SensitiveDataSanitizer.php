<?php

declare(strict_types=1);

namespace DingTalk\Log;

/**
 * 敏感信息脱敏工具类
 * 
 * 用于清理日志中的敏感信息
 */
class SensitiveDataSanitizer
{
    /**
     * 敏感字段名称模式
     */
    private const SENSITIVE_FIELD_PATTERNS = [
        '/password/i',
        '/passwd/i',
        '/secret/i',
        '/token/i',
        '/key/i',
        '/auth/i',
        '/credential/i',
        '/signature/i',
        '/sign/i',
        '/access_token/i',
        '/refresh_token/i',
        '/api_key/i',
        '/private_key/i',
        '/public_key/i',
        '/cert/i',
        '/certificate/i',
        '/ssn/i',
        '/social_security/i',
        '/credit_card/i',
        '/card_number/i',
        '/cvv/i',
        '/pin/i',
        '/phone/i',
        '/mobile/i',
        '/email/i',
        '/address/i',
        '/id_card/i',
        '/passport/i',
        '/license/i',
    ];

    /**
     * 敏感值模式
     */
    private const SENSITIVE_VALUE_PATTERNS = [
        // 信用卡号
        '/\b\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4}\b/',
        // 身份证号
        '/\b\d{15}|\d{18}\b/',
        // 手机号
        '/\b1[3-9]\d{9}\b/',
        // 邮箱
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        // IP地址
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/',
        // JWT Token
        '/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/',
        // API Key (32位十六进制)
        '/\b[a-fA-F0-9]{32}\b/',
        // UUID
        '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/',
    ];

    /**
     * 自定义敏感字段
     */
    private array $customSensitiveFields = [];

    /**
     * 自定义敏感值模式
     */
    private array $customSensitivePatterns = [];

    /**
     * 脱敏字符
     */
    private string $maskChar = '*';

    /**
     * 保留字符数量（前后各保留的字符数）
     */
    private int $keepChars = 2;

    /**
     * 构造函数
     */
    public function __construct(
        array $customSensitiveFields = [],
        array $customSensitivePatterns = [],
        string $maskChar = '*',
        int $keepChars = 2
    ) {
        $this->customSensitiveFields = $customSensitiveFields;
        $this->customSensitivePatterns = $customSensitivePatterns;
        $this->maskChar = $maskChar;
        $this->keepChars = $keepChars;
    }

    /**
     * 清理敏感数据
     */
    public function sanitize($data)
    {
        if (is_array($data)) {
            return $this->sanitizeArray($data);
        }
        
        if (is_object($data)) {
            return $this->sanitizeObject($data);
        }
        
        if (is_string($data)) {
            return $this->sanitizeString($data);
        }
        
        return $data;
    }

    /**
     * 清理数组
     */
    private function sanitizeArray(array $data): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if ($this->isSensitiveField($key)) {
                $sanitized[$key] = $this->maskValue($value);
            } else {
                $sanitized[$key] = $this->sanitize($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * 清理对象
     */
    private function sanitizeObject($data)
    {
        if ($data instanceof \stdClass) {
            $sanitized = new \stdClass();
            foreach (get_object_vars($data) as $key => $value) {
                if ($this->isSensitiveField($key)) {
                    $sanitized->$key = $this->maskValue($value);
                } else {
                    $sanitized->$key = $this->sanitize($value);
                }
            }
            return $sanitized;
        }
        
        // 对于其他对象，转换为数组处理
        return $this->sanitizeArray((array) $data);
    }

    /**
     * 清理字符串
     */
    private function sanitizeString(string $data): string
    {
        $sanitized = $data;
        
        // 应用敏感值模式
        $patterns = array_merge(self::SENSITIVE_VALUE_PATTERNS, $this->customSensitivePatterns);
        
        foreach ($patterns as $pattern) {
            $sanitized = preg_replace_callback($pattern, function ($matches) {
                return $this->maskValue($matches[0]);
            }, $sanitized);
        }
        
        return $sanitized;
    }

    /**
     * 检查是否为敏感字段
     */
    private function isSensitiveField(string $fieldName): bool
    {
        // 检查自定义敏感字段
        if (in_array(strtolower($fieldName), array_map('strtolower', $this->customSensitiveFields))) {
            return true;
        }
        
        // 检查预定义模式
        foreach (self::SENSITIVE_FIELD_PATTERNS as $pattern) {
            if (preg_match($pattern, $fieldName)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 脱敏值
     */
    private function maskValue($value): string
    {
        if (!is_string($value)) {
            $value = (string) $value;
        }
        
        $length = strlen($value);
        
        // 如果值太短，完全脱敏
        if ($length <= $this->keepChars * 2) {
            return str_repeat($this->maskChar, $length);
        }
        
        // 保留前后字符，中间脱敏
        $prefix = substr($value, 0, $this->keepChars);
        $suffix = substr($value, -$this->keepChars);
        $maskLength = $length - ($this->keepChars * 2);
        
        return $prefix . str_repeat($this->maskChar, $maskLength) . $suffix;
    }

    /**
     * 添加自定义敏感字段
     */
    public function addSensitiveField(string $fieldName): void
    {
        if (!in_array($fieldName, $this->customSensitiveFields)) {
            $this->customSensitiveFields[] = $fieldName;
        }
    }

    /**
     * 添加自定义敏感值模式
     */
    public function addSensitivePattern(string $pattern): void
    {
        if (!in_array($pattern, $this->customSensitivePatterns)) {
            $this->customSensitivePatterns[] = $pattern;
        }
    }

    /**
     * 设置脱敏字符
     */
    public function setMaskChar(string $maskChar): void
    {
        $this->maskChar = $maskChar;
    }

    /**
     * 设置保留字符数量
     */
    public function setKeepChars(int $keepChars): void
    {
        $this->keepChars = max(0, $keepChars);
    }

    /**
     * 获取自定义敏感字段
     */
    public function getCustomSensitiveFields(): array
    {
        return $this->customSensitiveFields;
    }

    /**
     * 获取自定义敏感值模式
     */
    public function getCustomSensitivePatterns(): array
    {
        return $this->customSensitivePatterns;
    }

    /**
     * 清理HTTP请求头
     */
    public function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        
        foreach ($headers as $name => $value) {
            if ($this->isSensitiveField($name)) {
                $sanitized[$name] = $this->maskValue(is_array($value) ? implode(', ', $value) : $value);
            } else {
                $sanitized[$name] = $value;
            }
        }
        
        return $sanitized;
    }

    /**
     * 清理URL参数
     */
    public function sanitizeUrlParams(string $url): string
    {
        $parts = parse_url($url);
        
        if (!isset($parts['query'])) {
            return $url;
        }
        
        parse_str($parts['query'], $params);
        $sanitizedParams = $this->sanitizeArray($params);
        
        $parts['query'] = http_build_query($sanitizedParams);
        
        return $this->buildUrl($parts);
    }

    /**
     * 构建URL
     */
    private function buildUrl(array $parts): string
    {
        $url = '';
        
        if (isset($parts['scheme'])) {
            $url .= $parts['scheme'] . '://';
        }
        
        if (isset($parts['user'])) {
            $url .= $parts['user'];
            if (isset($parts['pass'])) {
                $url .= ':' . $this->maskValue($parts['pass']);
            }
            $url .= '@';
        }
        
        if (isset($parts['host'])) {
            $url .= $parts['host'];
        }
        
        if (isset($parts['port'])) {
            $url .= ':' . $parts['port'];
        }
        
        if (isset($parts['path'])) {
            $url .= $parts['path'];
        }
        
        if (isset($parts['query'])) {
            $url .= '?' . $parts['query'];
        }
        
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        
        return $url;
    }

    /**
     * 创建默认实例
     */
    public static function createDefault(): self
    {
        return new self();
    }

    /**
     * 创建严格模式实例（更多敏感字段）
     */
    public static function createStrict(): self
    {
        $strictFields = [
            'username', 'user_name', 'login', 'account',
            'name', 'real_name', 'full_name', 'first_name', 'last_name',
            'birthday', 'birth_date', 'age',
            'company', 'organization', 'department',
            'salary', 'income', 'payment',
            'bank_account', 'account_number',
            'ip_address', 'mac_address',
            'device_id', 'imei', 'uuid',
            'session_id', 'csrf_token',
        ];
        
        return new self($strictFields);
    }

    /**
     * 创建宽松模式实例（较少敏感字段）
     */
    public static function createLoose(): self
    {
        $looseFields = [
            'password', 'secret', 'token', 'key',
            'credit_card', 'ssn', 'id_card',
        ];
        
        return new self($looseFields);
    }
}