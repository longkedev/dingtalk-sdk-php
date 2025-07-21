# 错误码映射器使用指南

## 概述

错误码映射器（ErrorCodeMapper）是钉钉SDK中用于统一处理新旧API错误码的核心组件。它提供了错误码映射、消息国际化、上下文收集和错误恢复建议等功能。

## 主要功能

### 1. 新旧API错误码统一映射

错误码映射器支持V1和V2版本API的错误码统一处理：

```php
use DingTalk\Exceptions\ErrorCodeMapper;

$mapper = new ErrorCodeMapper('zh');

// V2 API错误码映射
$v2Error = $mapper->mapErrorCode('40001', 'v2');
echo $v2Error['message']; // 输出: 访问令牌无效

// V1 API错误码映射
$v1Error = $mapper->mapErrorCode('40001', 'v1');
echo $v1Error['message']; // 输出: 凭证无效

// 自动检测API版本
$autoError = $mapper->mapErrorCode('40001');
echo $autoError['api_version']; // 输出: v2 (默认)
```

### 2. 自定义错误码定义

支持SDK自定义错误码：

```php
// 自定义错误码映射
$customError = $mapper->mapErrorCode('SDK_001', 'custom');
echo $customError['type']; // 输出: config
echo $customError['message']; // 输出: 配置文件未找到
```

### 3. 错误消息国际化

支持多语言错误消息：

```php
// 中文消息
$mapper->setLanguage('zh');
$error = $mapper->mapErrorCode('40001', 'v2');
echo $error['message']; // 输出: 访问令牌无效

// 英文消息
$mapper->setLanguage('en');
$error = $mapper->mapErrorCode('40001', 'v2');
echo $error['message']; // 输出: Invalid access token

// 日文消息
$mapper->setLanguage('ja');
$error = $mapper->mapErrorCode('40001', 'v2');
echo $error['message']; // 输出: アクセストークンが無効です
```

### 4. 错误上下文信息收集

收集错误发生时的详细上下文：

```php
// 添加HTTP上下文
$mapper->addHttpContext([
    'method' => 'POST',
    'url' => 'https://api.dingtalk.com/v1.0/oauth2/accessToken',
    'headers' => ['Content-Type' => 'application/json'],
    'status_code' => 401
]);

// 添加API上下文
$mapper->addApiContext([
    'endpoint' => '/v1.0/oauth2/accessToken',
    'version' => 'v2',
    'method' => 'POST',
    'response_code' => 401
]);

// 添加认证上下文
$mapper->addAuthContext([
    'app_type' => 'internal',
    'corp_id' => 'your_corp_id',
    'app_key' => 'your_app_key'
]);

// 收集完整上下文
$context = $mapper->collectErrorContext('40001', [
    'custom_field' => 'custom_value'
]);

print_r($context);
```

### 5. 错误恢复建议

获取针对性的错误恢复建议：

```php
// 获取恢复建议
$suggestion = $mapper->getRecoverySuggestion('40001', 'v2');
echo $suggestion; // 输出: 请刷新您的访问令牌

// 不同语言的建议
$mapper->setLanguage('en');
$suggestion = $mapper->getRecoverySuggestion('40001', 'v2');
echo $suggestion; // 输出: Please refresh your access token
```

### 6. 异常实例创建

直接创建对应的异常实例：

```php
// 创建认证异常
$authException = $mapper->createException('40001', 'v2');
echo get_class($authException); // 输出: DingTalk\Exceptions\AuthException

// 创建API异常
$apiException = $mapper->createException('40015', 'v2');
echo get_class($apiException); // 输出: DingTalk\Exceptions\ApiException

// 创建限流异常
$rateLimitException = $mapper->createException('90018', 'v2');
echo get_class($rateLimitException); // 输出: DingTalk\Exceptions\RateLimitException
```

## 错误类型判断

### 快速判断错误类型

```php
// 判断是否为认证错误
if ($mapper->isAuthError('40001', 'v2')) {
    echo "这是认证错误";
}

// 判断是否为限流错误
if ($mapper->isRateLimitError('90018', 'v2')) {
    echo "这是限流错误";
}

// 判断是否为网络错误
if ($mapper->isNetworkError('50001', 'v2')) {
    echo "这是网络错误";
}
```

### 使用ErrorCodes类进行判断

```php
use DingTalk\Exceptions\ErrorCodes;

// 检查错误码类型
if (ErrorCodes::isAuthError('40001')) {
    echo "认证错误";
}

if (ErrorCodes::isApiError('40015')) {
    echo "API调用错误";
}

if (ErrorCodes::isRateLimitError('90018')) {
    echo "限流错误";
}

// 获取错误码的API版本
$version = ErrorCodes::getApiVersion('40001'); // 返回: v2

// 获取错误码的类型
$type = ErrorCodes::getErrorType('40001'); // 返回: auth
```

## 支持的错误码

### 获取所有支持的错误码

```php
// 获取V2 API错误码
$v2Codes = $mapper->getSupportedErrorCodes('v2');

// 获取V1 API错误码
$v1Codes = $mapper->getSupportedErrorCodes('v1');

// 获取自定义错误码
$customCodes = $mapper->getSupportedErrorCodes('custom');

// 获取所有错误码
$allCodes = $mapper->getSupportedErrorCodes('all');
```

## 完整使用示例

```php
<?php

use DingTalk\Exceptions\ErrorCodeMapper;

// 创建错误码映射器实例
$mapper = new ErrorCodeMapper('zh', false); // 中文，不收集敏感信息

try {
    // 模拟API调用失败
    throw new Exception('API调用失败', 40001);
    
} catch (Exception $e) {
    // 添加上下文信息
    $mapper->addHttpContext([
        'method' => 'POST',
        'url' => 'https://api.dingtalk.com/v1.0/oauth2/accessToken',
        'status_code' => 401
    ]);
    
    $mapper->addApiContext([
        'endpoint' => '/v1.0/oauth2/accessToken',
        'version' => 'v2'
    ]);
    
    // 映射错误码
    $errorInfo = $mapper->mapErrorCode((string)$e->getCode(), 'v2');
    
    // 收集上下文
    $context = $mapper->collectErrorContext((string)$e->getCode(), [
        'original_message' => $e->getMessage()
    ]);
    
    // 获取恢复建议
    $recovery = $mapper->getRecoverySuggestion((string)$e->getCode(), 'v2');
    
    // 创建具体的异常实例
    $specificException = $mapper->createException((string)$e->getCode(), 'v2');
    
    // 输出错误信息
    echo "错误类型: " . $errorInfo['type'] . "\n";
    echo "错误消息: " . $errorInfo['message'] . "\n";
    echo "恢复建议: " . $recovery . "\n";
    echo "异常类型: " . get_class($specificException) . "\n";
    
    // 输出上下文（用于调试）
    echo "错误上下文: " . json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}
```

## 最佳实践

### 1. 统一错误处理

```php
class ApiClient
{
    private ErrorCodeMapper $errorMapper;
    
    public function __construct()
    {
        $this->errorMapper = new ErrorCodeMapper('zh');
    }
    
    public function handleApiError(string $errorCode, string $apiVersion = 'v2'): void
    {
        // 映射错误码
        $errorInfo = $this->errorMapper->mapErrorCode($errorCode, $apiVersion);
        
        // 创建具体异常
        $exception = $this->errorMapper->createException($errorCode, $apiVersion);
        
        // 抛出异常
        throw $exception;
    }
}
```

### 2. 错误日志记录

```php
class ErrorLogger
{
    private ErrorCodeMapper $errorMapper;
    
    public function logError(string $errorCode, array $context = []): void
    {
        // 收集完整上下文
        $fullContext = $this->errorMapper->collectErrorContext($errorCode, $context);
        
        // 记录到日志
        error_log(json_encode($fullContext, JSON_UNESCAPED_UNICODE));
    }
}
```

### 3. 用户友好的错误提示

```php
class UserErrorHandler
{
    private ErrorCodeMapper $errorMapper;
    
    public function getUserFriendlyMessage(string $errorCode): array
    {
        $errorInfo = $this->errorMapper->mapErrorCode($errorCode);
        
        return [
            'message' => $errorInfo['message'],
            'suggestion' => $this->errorMapper->getRecoverySuggestion($errorCode),
            'type' => $errorInfo['type']
        ];
    }
}
```

## 注意事项

1. **线程安全**: ErrorCodeMapper实例不是线程安全的，建议每个请求创建独立实例
2. **内存使用**: 上下文收集器会保存所有添加的上下文信息，注意内存使用
3. **敏感信息**: 默认会过滤敏感信息，如需完整信息可设置`collectSensitiveInfo`为true
4. **语言设置**: 语言设置会影响所有后续的消息翻译
5. **错误码冲突**: V1和V2可能存在相同的错误码，务必指定正确的API版本

## 扩展开发

如需添加新的错误码或语言支持，可以：

1. 在`ErrorCodes`类中添加新的错误码常量
2. 在`ErrorCodeMapper`中添加对应的映射关系
3. 在`ErrorMessageTranslator`中添加新语言的翻译
4. 更新相应的错误码分组常量

这样可以确保新的错误码能够被正确处理和显示。