<?php

declare(strict_types=1);

if (!function_exists('dingtalk_config')) {
    /**
     * 获取钉钉配置值
     *
     * @param string $key 配置键名，支持点号分隔的嵌套键
     * @param mixed $default 默认值
     * @return mixed
     */
    function dingtalk_config(string $key, $default = null)
    {
        static $config = null;
        
        if ($config === null) {
            $config = new \DingTalk\Config\ConfigManager();
        }
        
        return $config->get($key, $default);
    }
}

if (!function_exists('dingtalk_cache_key')) {
    /**
     * 生成钉钉缓存键
     *
     * @param string $prefix 前缀
     * @param array $params 参数
     * @return string
     */
    function dingtalk_cache_key(string $prefix, array $params = []): string
    {
        $key = $prefix;
        
        if (!empty($params)) {
            ksort($params);
            $key .= ':' . md5(serialize($params));
        }
        
        return $key;
    }
}

if (!function_exists('dingtalk_timestamp')) {
    /**
     * 获取钉钉时间戳（毫秒）
     *
     * @param int|null $timestamp Unix时间戳（秒），为null时使用当前时间
     * @return int
     */
    function dingtalk_timestamp(?int $timestamp = null): int
    {
        return ($timestamp ?? time()) * 1000;
    }
}

if (!function_exists('dingtalk_format_date')) {
    /**
     * 格式化日期为钉钉API格式
     *
     * @param string|int|\DateTime $date 日期
     * @param string $format 格式
     * @return string
     */
    function dingtalk_format_date($date, string $format = 'Y-m-d H:i:s'): string
    {
        if ($date instanceof \DateTime) {
            return $date->format($format);
        }
        
        if (is_numeric($date)) {
            // 如果是毫秒时间戳，转换为秒
            if ($date > 9999999999) {
                $date = intval($date / 1000);
            }
            return date($format, (int)$date);
        }
        
        return date($format, strtotime((string)$date));
    }
}

if (!function_exists('dingtalk_filter_empty')) {
    /**
     * 过滤数组中的空值
     *
     * @param array $data 数据数组
     * @param bool $removeNull 是否移除null值
     * @param bool $removeEmptyString 是否移除空字符串
     * @param bool $removeEmptyArray 是否移除空数组
     * @return array
     */
    function dingtalk_filter_empty(
        array $data,
        bool $removeNull = true,
        bool $removeEmptyString = true,
        bool $removeEmptyArray = true
    ): array {
        return array_filter($data, function ($value) use ($removeNull, $removeEmptyString, $removeEmptyArray) {
            if ($removeNull && $value === null) {
                return false;
            }
            
            if ($removeEmptyString && $value === '') {
                return false;
            }
            
            if ($removeEmptyArray && is_array($value) && empty($value)) {
                return false;
            }
            
            return true;
        });
    }
}

if (!function_exists('dingtalk_validate_mobile')) {
    /**
     * 验证手机号格式
     *
     * @param string $mobile 手机号
     * @return bool
     */
    function dingtalk_validate_mobile(string $mobile): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $mobile) === 1;
    }
}

if (!function_exists('dingtalk_validate_email')) {
    /**
     * 验证邮箱格式
     *
     * @param string $email 邮箱
     * @return bool
     */
    function dingtalk_validate_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('dingtalk_sign')) {
    /**
     * 生成钉钉签名
     *
     * @param string $secret 密钥
     * @param int|null $timestamp 时间戳（毫秒）
     * @return array ['timestamp' => int, 'sign' => string]
     */
    function dingtalk_sign(string $secret, ?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? dingtalk_timestamp();
        $stringToSign = $timestamp . "\n" . $secret;
        $sign = base64_encode(hash_hmac('sha256', $stringToSign, $secret, true));
        
        return [
            'timestamp' => $timestamp,
            'sign' => $sign,
        ];
    }
}

if (!function_exists('dingtalk_array_get')) {
    /**
     * 从数组中获取值，支持点号分隔的键
     *
     * @param array $array 数组
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    function dingtalk_array_get(array $array, string $key, $default = null)
    {
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        if (strpos($key, '.') === false) {
            return $default;
        }
        
        $keys = explode('.', $key);
        $value = $array;
        
        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        
        return $value;
    }
}

if (!function_exists('dingtalk_array_set')) {
    /**
     * 设置数组值，支持点号分隔的键
     *
     * @param array $array 数组
     * @param string $key 键名
     * @param mixed $value 值
     * @return array
     */
    function dingtalk_array_set(array $array, string $key, $value): array
    {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return $array;
        }
        
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }
            $current = &$current[$segment];
        }
        
        $current = $value;
        
        return $array;
    }
}

if (!function_exists('dingtalk_uuid')) {
    /**
     * 生成UUID
     *
     * @return string
     */
    function dingtalk_uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('dingtalk_is_json')) {
    /**
     * 检查字符串是否为有效的JSON
     *
     * @param string $string 字符串
     * @return bool
     */
    function dingtalk_is_json(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}

if (!function_exists('dingtalk_safe_json_decode')) {
    /**
     * 安全的JSON解码
     *
     * @param string $json JSON字符串
     * @param bool $assoc 是否返回关联数组
     * @param mixed $default 默认值
     * @return mixed
     */
    function dingtalk_safe_json_decode(string $json, bool $assoc = true, $default = null)
    {
        $result = json_decode($json, $assoc);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $default;
        }
        
        return $result;
    }
}

if (!function_exists('dingtalk_safe_json_encode')) {
    /**
     * 安全的JSON编码
     *
     * @param mixed $data 数据
     * @param int $flags JSON编码选项
     * @return string
     */
    function dingtalk_safe_json_encode($data, int $flags = JSON_UNESCAPED_UNICODE): string
    {
        $json = json_encode($data, $flags);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('JSON encoding failed: ' . json_last_error_msg());
        }
        
        return $json;
    }
}

if (!function_exists('dingtalk_mask_sensitive')) {
    /**
     * 掩码敏感信息
     *
     * @param string $value 原始值
     * @param int $start 开始位置
     * @param int $length 掩码长度
     * @param string $mask 掩码字符
     * @return string
     */
    function dingtalk_mask_sensitive(string $value, int $start = 3, int $length = 4, string $mask = '*'): string
    {
        $valueLength = mb_strlen($value);
        
        if ($valueLength <= $start) {
            return str_repeat($mask, $valueLength);
        }
        
        if ($start + $length >= $valueLength) {
            $length = $valueLength - $start;
        }
        
        return mb_substr($value, 0, $start) . str_repeat($mask, $length) . mb_substr($value, $start + $length);
    }
}

if (!function_exists('dingtalk_retry')) {
    /**
     * 重试执行函数
     *
     * @param callable $callback 回调函数
     * @param int $maxAttempts 最大重试次数
     * @param int $delay 延迟时间（毫秒）
     * @return mixed
     * @throws \Exception
     */
    function dingtalk_retry(callable $callback, int $maxAttempts = 3, int $delay = 1000)
    {
        $attempts = 0;
        $lastException = null;
        
        while ($attempts < $maxAttempts) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempts++;
                
                if ($attempts < $maxAttempts && $delay > 0) {
                    usleep($delay * 1000);
                    $delay *= 2; // 指数退避
                }
            }
        }
        
        throw $lastException;
    }
}

if (!function_exists('dingtalk_memory_usage')) {
    /**
     * 获取内存使用情况
     *
     * @param bool $realUsage 是否获取真实内存使用量
     * @return array
     */
    function dingtalk_memory_usage(bool $realUsage = false): array
    {
        return [
            'current' => memory_get_usage($realUsage),
            'peak' => memory_get_peak_usage($realUsage),
            'limit' => ini_get('memory_limit'),
        ];
    }
}

if (!function_exists('dingtalk_format_bytes')) {
    /**
     * 格式化字节数
     *
     * @param int $bytes 字节数
     * @param int $precision 精度
     * @return string
     */
    function dingtalk_format_bytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}