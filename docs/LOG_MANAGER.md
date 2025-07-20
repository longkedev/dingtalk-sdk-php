# 钉钉SDK日志管理器

一个功能强大、灵活且符合PSR-3标准的PHP日志管理器，专为钉钉SDK设计。

## 特性

- ✅ **PSR-3兼容**: 完全符合PSR-3日志接口标准
- ✅ **多级别日志**: 支持所有PSR-3定义的日志级别
- ✅ **多处理器支持**: 文件、控制台、远程、轮转文件、性能监控等
- ✅ **灵活格式化**: JSON、行格式等多种格式化器
- ✅ **敏感信息脱敏**: 自动识别并脱敏敏感数据
- ✅ **日志轮转**: 支持按时间、大小进行日志轮转
- ✅ **性能监控**: 内置性能指标收集和监控
- ✅ **批量处理**: 支持批量日志记录
- ✅ **异常处理**: 专门的异常日志记录方法

## 安装

```bash
composer require dingtalk/sdk
```

## 快速开始

### 基础使用

```php
<?php
use DingTalk\Log\LogManager;
use DingTalk\Log\Handlers\FileHandler;
use DingTalk\Log\Handlers\ConsoleHandler;
use Psr\Log\LogLevel;

// 创建日志管理器
$logger = new LogManager(LogLevel::DEBUG);

// 添加文件处理器
$fileHandler = new FileHandler('/path/to/app.log', LogLevel::INFO);
$logger->addHandler($fileHandler);

// 添加控制台处理器
$consoleHandler = new ConsoleHandler(LogLevel::DEBUG, true); // 启用颜色
$logger->addHandler($consoleHandler);

// 记录日志
$logger->info('应用启动', ['version' => '1.0.0']);
$logger->error('发生错误', ['error_code' => 'E001']);
```

## 处理器 (Handlers)

### 文件处理器 (FileHandler)

将日志写入文件：

```php
use DingTalk\Log\Handlers\FileHandler;

$handler = new FileHandler(
    '/path/to/app.log',    // 文件路径
    LogLevel::INFO,        // 最低日志级别
    0644,                  // 文件权限
    true                   // 是否锁定文件
);
$logger->addHandler($handler);
```

### 控制台处理器 (ConsoleHandler)

将日志输出到控制台：

```php
use DingTalk\Log\Handlers\ConsoleHandler;

$handler = new ConsoleHandler(
    LogLevel::DEBUG,       // 最低日志级别
    true,                  // 是否启用颜色
    STDOUT                 // 输出流
);
$logger->addHandler($handler);
```

### 轮转文件处理器 (RotatingFileHandler)

支持日志轮转的文件处理器：

```php
use DingTalk\Log\Handlers\RotatingFileHandler;

$handler = new RotatingFileHandler(
    '/path/to/app.log',    // 基础文件路径
    LogLevel::INFO,        // 最低日志级别
    'daily',               // 轮转类型: daily, weekly, monthly, size
    7,                     // 保留文件数量
    1024 * 1024           // 最大文件大小 (仅size类型)
);
$logger->addHandler($handler);
```

### 远程处理器 (RemoteHandler)

将日志发送到远程服务器：

```php
use DingTalk\Log\Handlers\RemoteHandler;

$handler = new RemoteHandler(
    'https://logs.example.com/api',  // 远程URL
    LogLevel::ERROR,                 // 最低日志级别
    ['Content-Type' => 'application/json'], // 请求头
    ['timeout' => 5]                 // HTTP选项
);
$handler->setApiKey('your-api-key');
$handler->setBatchSize(10);          // 批量发送大小
$logger->addHandler($handler);
```

### 性能处理器 (PerformanceHandler)

监控和记录性能指标：

```php
use DingTalk\Log\Handlers\PerformanceHandler;

$handler = new PerformanceHandler($logger);
$handler->setThresholds([
    'duration' => 1.0,     // 请求时长阈值(秒)
    'memory' => 10485760,  // 内存使用阈值(字节)
    'cpu' => 80.0          // CPU使用率阈值(%)
]);

// 记录请求性能
$handler->recordRequest('GET', '/api/users', 0.5, 5242880, 45.0);
```

## 格式化器 (Formatters)

### JSON格式化器

```php
use DingTalk\Log\Formatters\JsonFormatter;

$formatter = new JsonFormatter(
    true,  // 美化输出
    true,  // 包含调用者信息
    true   // 包含额外信息
);
$handler->setFormatter($formatter);
```

### 行格式化器

```php
use DingTalk\Log\Formatters\LineFormatter;

$formatter = new LineFormatter(
    "[%datetime%] %level_name%: %message% %context%\n", // 格式模板
    'Y-m-d H:i:s',  // 日期格式
    true,           // 允许内联换行
    true            // 忽略空上下文
);
$handler->setFormatter($formatter);
```

## 敏感信息脱敏

### 启用默认脱敏

```php
// 启用默认脱敏规则
$logger->enableDefaultSanitization();

// 启用严格脱敏规则
$logger->enableStrictSanitization();

// 禁用脱敏
$logger->disableSanitization();
```

### 自定义脱敏规则

```php
use DingTalk\Log\SensitiveDataSanitizer;

$sanitizer = new SensitiveDataSanitizer(['custom_field']);
$sanitizer->addSensitiveField('secret_key');
$sanitizer->addSensitivePattern('/\bTOKEN_\w+/');

$logger->setSanitizer($sanitizer);
```

### 脱敏示例

```php
$logger->info('用户登录', [
    'username' => 'john_doe',
    'password' => 'secret123',      // 将被脱敏为 [FILTERED]
    'email' => 'john@example.com',  // 将被脱敏为 [FILTERED]
    'phone' => '13812345678'        // 将被脱敏为 [FILTERED]
]);
```

## 专用日志方法

### API请求/响应日志

```php
// 记录API请求
$logger->logApiRequest(
    'POST',
    'https://oapi.dingtalk.com/robot/send',
    ['msgtype' => 'text', 'text' => ['content' => 'Hello']],
    ['Content-Type' => 'application/json']
);

// 记录API响应
$logger->logApiResponse(200, ['errcode' => 0, 'errmsg' => 'ok'], 0.245);
```

### 异常日志

```php
try {
    // 一些可能抛出异常的代码
} catch (\Exception $e) {
    $logger->logException($e, ['context' => 'user_action']);
}
```

## 批量处理

```php
use DingTalk\Log\LogRecord;

$records = [
    new LogRecord(LogLevel::INFO, '消息1', ['key' => 'value1']),
    new LogRecord(LogLevel::WARNING, '消息2', ['key' => 'value2']),
    new LogRecord(LogLevel::ERROR, '消息3', ['key' => 'value3'])
];

$logger->handleBatch($records);
```

## 配置示例

### 完整配置示例

```php
<?php
use DingTalk\Log\LogManager;
use DingTalk\Log\Handlers\FileHandler;
use DingTalk\Log\Handlers\ConsoleHandler;
use DingTalk\Log\Handlers\RotatingFileHandler;
use DingTalk\Log\Formatters\JsonFormatter;
use DingTalk\Log\Formatters\LineFormatter;
use Psr\Log\LogLevel;

// 创建日志管理器
$logger = new LogManager(LogLevel::DEBUG);

// 控制台处理器 - 开发环境
if (getenv('APP_ENV') === 'development') {
    $consoleHandler = new ConsoleHandler(LogLevel::DEBUG, true);
    $consoleHandler->setFormatter(new LineFormatter());
    $logger->addHandler($consoleHandler);
}

// 应用日志文件处理器
$appHandler = new RotatingFileHandler(
    '/var/log/dingtalk/app.log',
    LogLevel::INFO,
    'daily',
    30  // 保留30天
);
$appHandler->setFormatter(new JsonFormatter(false, true, true));
$logger->addHandler($appHandler);

// 错误日志文件处理器
$errorHandler = new FileHandler('/var/log/dingtalk/error.log', LogLevel::ERROR);
$errorHandler->setFormatter(new JsonFormatter(true, true, true));
$logger->addHandler($errorHandler);

// 启用敏感信息脱敏
$logger->enableDefaultSanitization();

// 使用日志管理器
$logger->info('应用启动', ['version' => '1.0.0', 'environment' => getenv('APP_ENV')]);
```

## 最佳实践

### 1. 日志级别使用指南

- **EMERGENCY**: 系统不可用
- **ALERT**: 必须立即采取行动
- **CRITICAL**: 严重错误条件
- **ERROR**: 运行时错误，不需要立即处理
- **WARNING**: 警告信息，不是错误
- **NOTICE**: 正常但重要的事件
- **INFO**: 一般信息性消息
- **DEBUG**: 详细的调试信息

### 2. 上下文数据

```php
// 好的实践
$logger->info('用户登录成功', [
    'user_id' => $userId,
    'ip_address' => $request->getClientIp(),
    'user_agent' => $request->headers->get('User-Agent'),
    'timestamp' => time()
]);

// 避免记录敏感信息
$logger->info('用户登录', [
    'username' => $username,
    'password' => $password  // ❌ 不要这样做
]);
```

### 3. 性能考虑

```php
// 在生产环境中避免DEBUG级别日志
if (getenv('APP_ENV') === 'production') {
    $logger = new LogManager(LogLevel::INFO);
} else {
    $logger = new LogManager(LogLevel::DEBUG);
}

// 使用轮转处理器避免日志文件过大
$handler = new RotatingFileHandler('/path/to/app.log', LogLevel::INFO, 'daily', 7);
```

### 4. 错误处理

```php
// 捕获并记录异常
try {
    $result = $api->call();
} catch (\Exception $e) {
    $logger->logException($e, [
        'operation' => 'api_call',
        'parameters' => $parameters
    ]);
    throw $e; // 重新抛出异常
}
```

## 故障排除

### 常见问题

1. **文件权限问题**
   ```bash
   chmod 755 /path/to/log/directory
   chmod 644 /path/to/log/file.log
   ```

2. **磁盘空间不足**
   - 使用轮转处理器限制日志文件大小
   - 定期清理旧日志文件

3. **性能问题**
   - 在生产环境中避免DEBUG级别
   - 使用异步处理器处理大量日志

### 调试

启用调试模式查看详细信息：

```php
$logger = new LogManager(LogLevel::DEBUG);
$consoleHandler = new ConsoleHandler(LogLevel::DEBUG, true);
$logger->addHandler($consoleHandler);
```

## 许可证

MIT License

## 贡献

欢迎提交Issue和Pull Request来改进这个日志管理器。