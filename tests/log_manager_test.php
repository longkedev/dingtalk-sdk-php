<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DingTalk\Log\LogManager;
use DingTalk\Log\SensitiveDataSanitizer;
use DingTalk\Log\Handlers\FileHandler;
use DingTalk\Log\Handlers\ConsoleHandler;
use DingTalk\Log\Formatters\JsonFormatter;
use DingTalk\Log\Formatters\LineFormatter;
use Psr\Log\LogLevel;

/**
 * 日志管理器测试脚本
 */
class LogManagerTest
{
    private LogManager $logger;
    private string $testLogDir;

    public function __construct()
    {
        $this->testLogDir = __DIR__ . '/test_logs';
        if (!is_dir($this->testLogDir)) {
            mkdir($this->testLogDir, 0755, true);
        }
        
        $this->logger = new LogManager(LogLevel::DEBUG);
    }

    public function runAllTests(): void
    {
        echo "开始运行日志管理器测试...\n\n";

        $this->testBasicLogging();
        $this->testFormatters();
        $this->testSensitiveDataSanitization();
        $this->testHandlerManagement();
        $this->testLogLevels();
        $this->testApiLogging();
        $this->testExceptionLogging();

        echo "\n所有测试完成!\n";
        $this->cleanup();
    }

    private function testBasicLogging(): void
    {
        echo "测试 1: 基础日志记录\n";
        echo "------------------------\n";

        // 添加控制台处理器
        $consoleHandler = new ConsoleHandler(LogLevel::INFO, true);
        $this->logger->addHandler($consoleHandler);

        // 添加文件处理器
        $fileHandler = new FileHandler($this->testLogDir . '/basic.log', LogLevel::DEBUG);
        $this->logger->addHandler($fileHandler);

        $this->logger->info('测试基础日志记录', ['test' => 'basic_logging']);
        $this->logger->warning('这是一个警告', ['level' => 'warning']);
        $this->logger->error('这是一个错误', ['level' => 'error']);

        echo "✓ 基础日志记录测试完成\n\n";
    }

    private function testFormatters(): void
    {
        echo "测试 2: 格式化器\n";
        echo "------------------------\n";

        // 清理现有处理器
        $this->logger->clearHandlers();

        // 测试 JSON 格式化器
        $jsonFormatter = new JsonFormatter(true, true, true);
        $jsonHandler = new FileHandler($this->testLogDir . '/json.log', LogLevel::DEBUG, 0644, true, $jsonFormatter);
        $this->logger->addHandler($jsonHandler);

        $this->logger->info('JSON格式化器测试', [
            'formatter' => 'json',
            'pretty' => true,
            'include_context' => true
        ]);

        // 测试 Line 格式化器
        $lineFormatter = new LineFormatter('[%datetime%] %level_name%: %message% %context%');
        $lineHandler = new FileHandler($this->testLogDir . '/line.log', LogLevel::DEBUG, 0644, true, $lineFormatter);
        $this->logger->addHandler($lineHandler);

        $this->logger->info('Line格式化器测试', [
            'formatter' => 'line',
            'custom_format' => true
        ]);

        echo "✓ 格式化器测试完成\n\n";
    }

    private function testSensitiveDataSanitization(): void
    {
        echo "测试 3: 敏感信息脱敏\n";
        echo "------------------------\n";

        // 启用默认脱敏
        $this->logger->enableDefaultSanitization();

        $this->logger->info('敏感信息脱敏测试', [
            'username' => 'testuser',
            'password' => 'secret123',
            'email' => 'test@example.com',
            'phone' => '13812345678',
            'credit_card' => '4111-1111-1111-1111',
            'api_key' => 'sk_test_1234567890abcdef',
            'normal_data' => 'this should not be masked'
        ]);

        // 测试严格模式
        $this->logger->enableStrictSanitization();
        $this->logger->warning('严格模式脱敏测试', [
            'token' => 'bearer_token_12345',
            'secret' => 'top_secret_data'
        ]);

        echo "✓ 敏感信息脱敏测试完成\n\n";
    }

    private function testHandlerManagement(): void
    {
        echo "测试 4: 处理器管理\n";
        echo "------------------------\n";

        $initialCount = count($this->logger->getHandlers());
        echo "初始处理器数量: {$initialCount}\n";

        // 添加新处理器
        $newHandler = new ConsoleHandler(LogLevel::ERROR, false);
        $this->logger->addHandler($newHandler);

        $afterAddCount = count($this->logger->getHandlers());
        echo "添加后处理器数量: {$afterAddCount}\n";

        // 移除处理器
        $this->logger->removeHandler($newHandler);
        $afterRemoveCount = count($this->logger->getHandlers());
        echo "移除后处理器数量: {$afterRemoveCount}\n";

        // 清理所有处理器
        $this->logger->clearHandlers();
        $afterClearCount = count($this->logger->getHandlers());
        echo "清理后处理器数量: {$afterClearCount}\n";

        echo "✓ 处理器管理测试完成\n\n";
    }

    private function testLogLevels(): void
    {
        echo "测试 5: 日志级别\n";
        echo "------------------------\n";

        // 重新添加处理器用于测试
        $consoleHandler = new ConsoleHandler(LogLevel::DEBUG, false);
        $this->logger->addHandler($consoleHandler);

        $levels = [
            'emergency' => '紧急情况',
            'alert' => '警报',
            'critical' => '严重错误',
            'error' => '错误',
            'warning' => '警告',
            'notice' => '注意',
            'info' => '信息',
            'debug' => '调试'
        ];

        foreach ($levels as $level => $message) {
            $this->logger->$level($message, ['level' => $level]);
        }

        echo "✓ 日志级别测试完成\n\n";
    }

    private function testApiLogging(): void
    {
        echo "测试 6: API日志记录\n";
        echo "------------------------\n";

        // 测试API请求日志
        $this->logger->logApiRequest(
            'POST',
            'https://oapi.dingtalk.com/robot/send',
            ['msgtype' => 'text', 'text' => ['content' => 'Hello']],
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer token123']
        );

        // 测试API响应日志
        $this->logger->logApiResponse(
            200,
            ['errcode' => 0, 'errmsg' => 'ok'],
            0.156
        );

        echo "✓ API日志记录测试完成\n\n";
    }

    private function testExceptionLogging(): void
    {
        echo "测试 7: 异常日志记录\n";
        echo "------------------------\n";

        try {
            throw new \RuntimeException('测试异常', 500);
        } catch (\Exception $e) {
            $this->logger->logException($e, [
                'context' => 'test',
                'additional_info' => 'This is a test exception'
            ]);
        }

        echo "✓ 异常日志记录测试完成\n\n";
    }

    private function cleanup(): void
    {
        echo "清理测试文件...\n";
        
        // 关闭所有处理器
        $this->logger->close();
        
        // 删除测试日志文件
        $files = glob($this->testLogDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        if (is_dir($this->testLogDir)) {
            rmdir($this->testLogDir);
        }
        
        echo "✓ 清理完成\n";
    }
}

// 运行测试
try {
    $test = new LogManagerTest();
    $test->runAllTests();
} catch (\Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";
}