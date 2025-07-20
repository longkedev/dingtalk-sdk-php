<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DingTalk\Log\LogManagerFactory;

echo "=== 日志管理器工厂使用示例 ===\n\n";

// 1. 创建开发环境日志管理器
echo "1. 创建开发环境日志管理器\n";
echo "------------------------\n";
$devLogger = LogManagerFactory::createForDevelopment(__DIR__ . '/logs/dev.log');
$devLogger->info('开发环境日志管理器创建成功');
$devLogger->debug('这是调试信息', ['environment' => 'development']);
echo "\n";

// 2. 创建生产环境日志管理器
echo "2. 创建生产环境日志管理器\n";
echo "------------------------\n";
$prodLogger = LogManagerFactory::createForProduction(__DIR__ . '/logs', [
    'level' => 'info',
    'performance_enabled' => true,
    'performance_thresholds' => [
        'duration' => 1.0,
        'memory' => 10 * 1024 * 1024
    ]
]);
$prodLogger->info('生产环境日志管理器创建成功');
$prodLogger->warning('这是生产环境警告', ['environment' => 'production']);
echo "\n";

// 3. 创建测试环境日志管理器
echo "3. 创建测试环境日志管理器\n";
echo "------------------------\n";
$testLogger = LogManagerFactory::createForTesting(__DIR__ . '/logs/test.log');
$testLogger->info('测试环境日志管理器创建成功');
$testLogger->notice('测试通知', ['environment' => 'testing']);
echo "\n";

// 4. 使用自定义配置创建日志管理器
echo "4. 使用自定义配置创建日志管理器\n";
echo "------------------------\n";
$customConfig = [
    'level' => 'debug',
    'handlers' => [
        'console' => [
            'class' => 'DingTalk\Log\Handlers\ConsoleHandler',
            'level' => 'info',
            'colored' => true,
            'formatter' => [
                'class' => 'DingTalk\Log\Formatters\LineFormatter',
                'format' => "[%datetime%] [%level_name%] %message%\n"
            ]
        ],
        'file' => [
            'class' => 'DingTalk\Log\Handlers\FileHandler',
            'level' => 'debug',
            'path' => __DIR__ . '/logs/custom.log',
            'formatter' => [
                'class' => 'DingTalk\Log\Formatters\JsonFormatter',
                'pretty_print' => true,
                'include_caller' => true
            ]
        ]
    ],
    'sanitization' => [
        'enabled' => true,
        'mode' => 'custom',
        'custom_fields' => ['secret_key', 'internal_token'],
        'custom_patterns' => ['/\bCUSTOM_\w+/'],
        'replacement' => '[MASKED]'
    ]
];

$customLogger = LogManagerFactory::create($customConfig);
$customLogger->info('自定义配置日志管理器创建成功');
$customLogger->debug('包含敏感信息的日志', [
    'username' => 'testuser',
    'secret_key' => 'this-should-be-masked',
    'internal_token' => 'CUSTOM_SECRET_123',
    'normal_data' => 'this-is-safe'
]);
echo "\n";

// 5. 从配置文件创建日志管理器
echo "5. 从配置文件创建日志管理器\n";
echo "------------------------\n";
try {
    $configLogger = LogManagerFactory::createFromConfigFile(
        __DIR__ . '/../config/log.php',
        'development'
    );
    $configLogger->info('从配置文件创建的日志管理器');
    echo "✓ 从配置文件创建成功\n";
} catch (Exception $e) {
    echo "✗ 从配置文件创建失败: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. 演示不同环境的日志级别差异
echo "6. 演示不同环境的日志级别差异\n";
echo "------------------------\n";

$environments = [
    'development' => LogManagerFactory::createForDevelopment(),
    'testing' => LogManagerFactory::createForTesting(),
    'production' => LogManagerFactory::createForProduction(__DIR__ . '/logs')
];

foreach ($environments as $env => $logger) {
    echo "环境: {$env}\n";
    $logger->debug("调试信息 - {$env}");
    $logger->info("信息日志 - {$env}");
    $logger->warning("警告日志 - {$env}");
    $logger->error("错误日志 - {$env}");
    echo "\n";
}

echo "=== 工厂示例完成 ===\n";