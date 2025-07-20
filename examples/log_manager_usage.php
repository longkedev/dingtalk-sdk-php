<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DingTalk\Log\LogManager;
use DingTalk\Log\LogRecord;
use DingTalk\Log\SensitiveDataSanitizer;
use DingTalk\Log\Handlers\FileHandler;
use DingTalk\Log\Handlers\ConsoleHandler;
use DingTalk\Log\Handlers\RemoteHandler;
use DingTalk\Log\Handlers\RotatingFileHandler;
use DingTalk\Log\Handlers\PerformanceHandler;
use DingTalk\Log\Formatters\JsonFormatter;
use DingTalk\Log\Formatters\LineFormatter;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

echo "=== 钉钉SDK日志管理器使用示例 ===\n\n";

// 1. 基础日志管理器
echo "1. 创建基础日志管理器\n";
$logger = new LogManager(LogLevel::DEBUG);

// 添加控制台处理器
$consoleHandler = new ConsoleHandler(LogLevel::INFO, true);
$logger->addHandler($consoleHandler);

// 添加文件处理器
$fileHandler = new FileHandler(__DIR__ . '/logs/app.log', LogLevel::DEBUG);
$logger->addHandler($fileHandler);

// 基础日志记录
$logger->info('应用启动', ['version' => '1.0.0', 'environment' => 'development']);
$logger->warning('这是一个警告消息', ['user_id' => 12345]);
$logger->error('发生了一个错误', ['error_code' => 'E001', 'details' => '数据库连接失败']);

echo "\n";

// 2. 使用JSON格式化器
echo "2. 使用JSON格式化器\n";
$jsonFormatter = new JsonFormatter(true, true, true);
$jsonFileHandler = new FileHandler(__DIR__ . '/logs/app.json', LogLevel::DEBUG, 0644, true, $jsonFormatter);
$logger->addHandler($jsonFileHandler);

$logger->debug('调试信息', [
    'request_id' => 'req_123456',
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'ip_address' => '192.168.1.100'
]);

echo "\n";

// 3. 敏感信息脱敏
echo "3. 敏感信息脱敏示例\n";
$sanitizer = SensitiveDataSanitizer::createDefault();
$logger->setSanitizer($sanitizer);

// 记录包含敏感信息的日志
$logger->info('用户登录', [
    'username' => 'john_doe',
    'password' => 'secret123456',
    'email' => 'john@example.com',
    'phone' => '13812345678',
    'credit_card' => '4111-1111-1111-1111',
    'api_key' => 'sk_test_1234567890abcdef1234567890abcdef'
]);

echo "\n";

// 4. 轮转文件处理器
echo "4. 轮转文件处理器示例\n";
$rotatingHandler = new RotatingFileHandler(
    __DIR__ . '/logs/rotating.log',
    LogLevel::INFO,
    'daily',
    7,
    1024 * 1024 // 1MB
);
$logger->addHandler($rotatingHandler);

for ($i = 1; $i <= 5; $i++) {
    $logger->info("轮转日志测试 #{$i}", ['iteration' => $i, 'timestamp' => time()]);
}

echo "\n";

// 5. 性能监控处理器
echo "5. 性能监控处理器示例\n";
$performanceHandler = new PerformanceHandler(new NullLogger());
$performanceHandler->setThresholds([
    'duration' => 1.0,  // 1秒
    'memory' => 10 * 1024 * 1024,  // 10MB
    'cpu' => 80.0  // 80%
]);
$logger->addHandler($performanceHandler);

// 模拟一些性能数据
$performanceHandler->recordRequest('GET', '/api/users', 0.5, 5 * 1024 * 1024, 45.0);
$performanceHandler->recordRequest('POST', '/api/orders', 1.2, 15 * 1024 * 1024, 85.0);
$performanceHandler->recordRequest('GET', '/api/products', 0.3, 3 * 1024 * 1024, 30.0);

echo "\n";

// 6. 远程日志处理器
echo "6. 远程日志处理器示例\n";
try {
    $remoteHandler = new RemoteHandler(
        'https://logs.example.com/api/logs',
        LogLevel::ERROR,
        ['Content-Type' => 'application/json'],
        ['timeout' => 5]
    );
    $remoteHandler->setApiKey('your-api-key-here');
    $logger->addHandler($remoteHandler);
    
    $logger->error('严重错误需要远程记录', [
        'service' => 'dingtalk-sdk',
        'severity' => 'critical',
        'affected_users' => 1500
    ]);
} catch (Exception $e) {
    echo "远程处理器配置失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 7. API请求/响应日志
echo "7. API请求/响应日志示例\n";
$logger->logApiRequest('POST', 'https://oapi.dingtalk.com/robot/send', [
    'msgtype' => 'text',
    'text' => ['content' => 'Hello World']
], [
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer your-token-here'
]);

$logger->logApiResponse(200, [
    'errcode' => 0,
    'errmsg' => 'ok'
], 0.245);

echo "\n";

// 8. 异常日志
echo "8. 异常日志示例\n";
try {
    throw new \RuntimeException('这是一个测试异常', 500);
} catch (\Exception $e) {
    $logger->logException($e, [
        'context' => 'testing',
        'user_id' => 12345
    ]);
}

echo "\n";

// 9. 批量日志处理
echo "9. 批量日志处理示例\n";
$records = [];
for ($i = 1; $i <= 3; $i++) {
    $records[] = new LogRecord(
        LogLevel::INFO,
        "批量日志消息 #{$i}",
        ['batch_id' => 'batch_001', 'item' => $i],
        new \DateTimeImmutable(),
        ['file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__, 'class' => null]
    );
}
$logger->handleBatch($records);

echo "\n";

// 10. 自定义敏感字段
echo "10. 自定义敏感字段示例\n";
$customSanitizer = new SensitiveDataSanitizer(['custom_secret', 'internal_id']);
$customSanitizer->addSensitiveField('company_code');
$customSanitizer->addSensitivePattern('/\bCOMP_\d{6}\b/'); // 公司代码模式

$logger->setSanitizer($customSanitizer);
$logger->info('自定义敏感信息测试', [
    'custom_secret' => 'this-should-be-masked',
    'internal_id' => 'ID123456789',
    'company_code' => 'COMP_001234',
    'normal_field' => 'this-is-safe'
]);

echo "\n";

// 11. 不同日志级别测试
echo "11. 不同日志级别测试\n";
$logger->emergency('系统紧急情况');
$logger->alert('需要立即处理的警报');
$logger->critical('严重错误');
$logger->error('一般错误');
$logger->warning('警告信息');
$logger->notice('注意信息');
$logger->info('一般信息');
$logger->debug('调试信息');

echo "\n";

// 12. 处理器管理
echo "12. 处理器管理示例\n";
echo "当前处理器数量: " . count($logger->getHandlers()) . "\n";

// 清理所有处理器
$logger->clearHandlers();
echo "清理后处理器数量: " . count($logger->getHandlers()) . "\n";

// 重新添加一个简单的控制台处理器
$simpleConsole = new ConsoleHandler(LogLevel::INFO, false);
$logger->addHandler($simpleConsole);
$logger->info('处理器管理测试完成');

echo "\n";

// 13. 格式化器比较
echo "13. 格式化器比较\n";

// Line格式化器
$lineFormatter = new LineFormatter();
$lineHandler = new ConsoleHandler(LogLevel::INFO, false, STDOUT, $lineFormatter);
$lineLogger = new LogManager();
$lineLogger->addHandler($lineHandler);

echo "Line格式化器输出:\n";
$lineLogger->info('测试消息', ['key' => 'value', 'number' => 123]);

// JSON格式化器
$jsonFormatter = new JsonFormatter(false, false, false);
$jsonHandler = new ConsoleHandler(LogLevel::INFO, false, STDOUT, $jsonFormatter);
$jsonLogger = new LogManager();
$jsonLogger->addHandler($jsonHandler);

echo "JSON格式化器输出:\n";
$jsonLogger->info('测试消息', ['key' => 'value', 'number' => 123]);

echo "\n=== 日志管理器示例完成 ===\n";

// 确保所有处理器正确关闭
$logger->close();