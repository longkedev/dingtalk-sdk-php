# 钉钉SDK异常类使用指南

## 概述

钉钉SDK提供了完整的异常处理体系，包含8个专门的异常类，用于处理不同类型的错误情况。所有异常类都继承自基础异常类`DingTalkException`。

## 异常类层次结构

```
DingTalkException (基础异常类)
├── ApiException (API异常)
├── AuthException (认证异常)
├── ConfigException (配置异常)
├── ContainerException (容器异常)
├── NetworkException (网络异常)
├── ValidationException (验证异常)
└── RateLimitException (限流异常)
```

## 基础异常类 - DingTalkException

### 特性
- 扩展的错误信息（错误代码、错误详情）
- 数组和JSON格式输出
- 链式异常支持

### 使用示例

```php
use DingTalk\Exceptions\DingTalkException;

try {
    // 某些操作
} catch (DingTalkException $e) {
    echo "错误消息: " . $e->getMessage() . "\n";
    echo "错误代码: " . $e->getErrorCode() . "\n";
    echo "错误详情: " . json_encode($e->getErrorDetails()) . "\n";
    
    // 输出为数组
    $errorArray = $e->toArray();
    
    // 输出为JSON
    $errorJson = $e->toJson();
}
```

## API异常 - ApiException

用于处理API调用相关的异常。

### 特性
- 请求ID跟踪
- 响应数据保存
- 扩展的错误信息

### 使用示例

```php
use DingTalk\Exceptions\ApiException;

$exception = new ApiException('API调用失败', 'API_ERROR', ['endpoint' => '/user/get'], 400);
$exception->setRequestId('req-12345');
$exception->setResponseData(['error' => 'User not found']);

echo "请求ID: " . $exception->getRequestId() . "\n";
echo "响应数据: " . json_encode($exception->getResponseData()) . "\n";
```

## 认证异常 - AuthException

用于处理身份认证和授权相关的异常。

### 常用静态方法

```php
use DingTalk\Exceptions\AuthException;

// 无效访问令牌
$exception = AuthException::invalidAccessToken('invalid_token');

// 访问令牌过期
$exception = AuthException::accessTokenExpired(time() - 3600);

// 权限不足
$exception = AuthException::insufficientPermissions(
    ['admin', 'write'], // 所需权限
    ['read']            // 当前权限
);

// 签名验证失败
$exception = AuthException::signatureVerificationFailed('invalid_signature');

// 应用未授权
$exception = AuthException::appNotAuthorized('app_key', 'user_scope');

// 用户未授权
$exception = AuthException::userNotAuthorized('user_123', 'resource_456');
```

### 权限检查示例

```php
try {
    // 权限检查逻辑
    if (!hasPermission($user, 'admin')) {
        throw AuthException::insufficientPermissions(['admin'], $user->getPermissions());
    }
} catch (AuthException $e) {
    echo "缺失权限: " . implode(', ', $e->getMissingPermissions()) . "\n";
}
```

## 配置异常 - ConfigException

用于处理配置文件和配置项相关的异常。

### 常用静态方法

```php
use DingTalk\Exceptions\ConfigException;

// 配置文件不存在
$exception = ConfigException::configFileNotFound('/path/to/config.php');

// 配置文件格式错误
$exception = ConfigException::invalidConfigFormat('/path/to/config.json', 'JSON');

// 配置项缺失
$exception = ConfigException::missingConfigKey('database.host', '/path/to/config.php');

// 配置值无效
$exception = ConfigException::invalidConfigValue(
    'timeout',
    'invalid_value',
    'integer',
    [30, 60, 120]
);

// 环境变量缺失
$exception = ConfigException::missingEnvVar('DATABASE_URL');

// 配置权限错误
$exception = ConfigException::configPermissionDenied('/etc/config.php', 'write');
```

## 容器异常 - ContainerException

用于处理依赖注入容器相关的异常。

### 常用静态方法

```php
use DingTalk\Exceptions\ContainerException;

// 服务未找到
$exception = ContainerException::serviceNotFound('UserService');

// 服务绑定失败
$exception = ContainerException::serviceBindingFailed('UserService', '类不存在');

// 循环依赖
$exception = ContainerException::circularDependency(['ServiceA', 'ServiceB', 'ServiceA']);

// 服务实例化失败
$exception = ContainerException::serviceInstantiationFailed(
    'UserService',
    'App\\Services\\UserService',
    '构造函数参数错误'
);

// 无效服务定义
$exception = ContainerException::invalidServiceDefinition('UserService', '缺少类名');

// 容器锁定
$exception = ContainerException::containerLocked('bind');
```

## 网络异常 - NetworkException

用于处理网络连接相关的异常。

### 常用静态方法

```php
use DingTalk\Exceptions\NetworkException;

// 连接超时
$exception = NetworkException::connectionTimeout(30);

// 读取超时
$exception = NetworkException::readTimeout(60);

// DNS解析失败
$exception = NetworkException::dnsResolutionFailed('api.dingtalk.com');

// 连接被拒绝
$exception = NetworkException::connectionRefused('localhost', 8080);

// SSL错误
$exception = NetworkException::sslError('证书验证失败');

// 网络不可达
$exception = NetworkException::networkUnreachable('192.168.1.100');
```

## 验证异常 - ValidationException

用于处理参数验证相关的异常。

### 常用静态方法

```php
use DingTalk\Exceptions\ValidationException;

// 参数缺失
$exception = ValidationException::missingParameter('user_id');

// 格式错误
$exception = ValidationException::invalidFormat('email', 'email格式', 'invalid-email');

// 值无效
$exception = ValidationException::invalidValue('status', 'invalid', ['active', 'inactive']);

// 长度错误
$exception = ValidationException::invalidLength('password', 3, 8, 20);

// 类型错误
$exception = ValidationException::invalidType('age', 'integer', 'string');
```

### 批量验证示例

```php
$exception = new ValidationException('验证失败');
$exception->addFailedField('name', 'required');
$exception->addFailedField('email', 'format:email');
$exception->addFailedField('age', 'type:integer');
$exception->setValidationRules([
    'name' => 'required|string',
    'email' => 'required|email',
    'age' => 'required|integer|min:18'
]);

echo "验证失败的字段: " . json_encode($exception->getFailedFields()) . "\n";
```

## 限流异常 - RateLimitException

用于处理API调用频率限制相关的异常。

### 常用静态方法

```php
use DingTalk\Exceptions\RateLimitException;

// 请求频率超限
$resetTime = time() + 3600;
$exception = RateLimitException::rateLimitExceeded(100, 100, $resetTime, 60);

// 配额超限
$exception = RateLimitException::quotaExceeded(1000, 1000, $resetTime);

// 并发限制
$exception = RateLimitException::concurrentLimit(5, 3, 30);

// QPS限制
$exception = RateLimitException::qpsLimit(10, 5, 1);

// QPM限制
$exception = RateLimitException::qpmLimit(100, 60, $resetTime);
```

### 限流处理示例

```php
try {
    // API调用
    $result = $api->call();
} catch (RateLimitException $e) {
    echo "限流类型: " . $e->getLimitType() . "\n";
    echo "当前请求数: " . $e->getCurrentRequests() . "\n";
    echo "最大请求数: " . $e->getMaxRequests() . "\n";
    echo "剩余请求数: " . $e->getRemainingRequests() . "\n";
    echo "重置时间: " . date('Y-m-d H:i:s', $e->getResetTime()) . "\n";
    echo "距离重置: " . $e->getTimeToReset() . " 秒\n";
    
    if ($e->canRetry()) {
        echo "可以重试，建议等待: " . $e->getRetryAfter() . " 秒\n";
        sleep($e->getRetryAfter());
        // 重试逻辑
    }
}
```

## 异常处理最佳实践

### 1. 分层异常处理

```php
try {
    // 业务逻辑
    $result = $service->processData($data);
} catch (ValidationException $e) {
    // 处理验证错误
    return ['error' => '参数验证失败', 'details' => $e->getFailedFields()];
} catch (AuthException $e) {
    // 处理认证错误
    return ['error' => '认证失败', 'code' => $e->getErrorCode()];
} catch (RateLimitException $e) {
    // 处理限流错误
    return ['error' => '请求过于频繁', 'retry_after' => $e->getRetryAfter()];
} catch (NetworkException $e) {
    // 处理网络错误
    return ['error' => '网络连接失败', 'message' => $e->getMessage()];
} catch (DingTalkException $e) {
    // 处理其他钉钉SDK异常
    return ['error' => 'SDK错误', 'details' => $e->toArray()];
}
```

### 2. 日志记录

```php
use DingTalk\Exceptions\DingTalkException;

try {
    // 业务逻辑
} catch (DingTalkException $e) {
    // 记录详细的异常信息
    $logger->error('钉钉SDK异常', [
        'exception_class' => get_class($e),
        'message' => $e->getMessage(),
        'error_code' => $e->getErrorCode(),
        'error_details' => $e->getErrorDetails(),
        'trace' => $e->getTraceAsString()
    ]);
    
    throw $e; // 重新抛出或处理
}
```

### 3. 异常转换

```php
try {
    // 调用外部服务
    $response = $httpClient->request('GET', $url);
} catch (\GuzzleHttp\Exception\ConnectException $e) {
    // 转换为SDK异常
    throw NetworkException::connectionTimeout(30, $e);
} catch (\GuzzleHttp\Exception\RequestException $e) {
    // 转换为API异常
    $apiException = new ApiException('HTTP请求失败', 'HTTP_ERROR', [], $e->getCode(), $e);
    if ($e->hasResponse()) {
        $apiException->setResponseData(json_decode($e->getResponse()->getBody(), true));
    }
    throw $apiException;
}
```

## 总结

钉钉SDK的异常体系提供了：

1. **完整的异常分类** - 8种专门的异常类型
2. **丰富的错误信息** - 错误代码、详情、上下文信息
3. **便捷的静态方法** - 快速创建常见异常
4. **灵活的输出格式** - 数组、JSON格式支持
5. **良好的可扩展性** - 基于继承的设计

通过合理使用这些异常类，可以实现精确的错误处理和用户友好的错误提示。