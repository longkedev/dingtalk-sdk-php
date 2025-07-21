<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Contracts/VersionAdapterInterface.php';
require_once __DIR__ . '/src/Contracts/ConfigInterface.php';
require_once __DIR__ . '/src/Version/VersionAdapter.php';

use DingTalk\Version\VersionAdapter;
use DingTalk\Contracts\ConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * 版本适配器测试示例
 * 
 * 演示版本适配器的六项核心功能：
 * 1. 请求参数格式转换
 * 2. 响应数据格式统一
 * 3. 字段名称映射
 * 4. 数据类型转换
 * 5. 默认值处理
 * 6. 向下兼容保证
 */

// 简单的日志接口实现
interface SimpleLoggerInterface
{
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
}

// 模拟配置类
class MockConfig implements ConfigInterface
{
    private array $config = [];

    public function get(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->config[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->config[$key]);
    }

    public function all(): array
    {
        return $this->config;
    }

    public function merge(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}

// 模拟日志类
class MockLogger implements SimpleLoggerInterface, LoggerInterface
{
    private array $logs = [];

    public function emergency($message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        echo "[{$level}] {$message}\n";
    }

    public function getLogs(): array
    {
        return $this->logs;
    }

    public function clearLogs(): void
    {
        $this->logs = [];
    }
}

// 创建测试实例
$config = new MockConfig();
$logger = new MockLogger();
$adapter = new VersionAdapter($config, $logger);

echo "=== 版本适配器 (VersionAdapter) 功能测试 ===\n\n";

// 1. 测试请求参数格式转换
echo "1. 测试请求参数格式转换\n";
echo "-----------------------------------\n";

$v1Params = [
    'userid' => 'user123',
    'lang' => 'en_US',
    'department' => [1, 2, 3]
];

echo "V1 请求参数: " . json_encode($v1Params, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $v2Params = $adapter->adaptRequestParams($v1Params, 'v1', 'v2', 'user.get');
    echo "转换为 V2 参数: " . json_encode($v2Params, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "转换失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 2. 测试响应数据格式统一
echo "2. 测试响应数据格式统一\n";
echo "-----------------------------------\n";

$v1Response = [
    'userid' => 'user123',
    'name' => '张三',
    'mobile' => '13800138000',
    'department' => [1, 2]
];

echo "V1 响应数据: " . json_encode($v1Response, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $v2Response = $adapter->adaptResponseData($v1Response, 'v1', 'v2', 'user.get');
    echo "转换为 V2 响应: " . json_encode($v2Response, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "转换失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. 测试字段名称映射
echo "3. 测试字段名称映射\n";
echo "-----------------------------------\n";

$requestMapping = $adapter->getFieldMapping('v1', 'v2', 'user.get', 'request');
echo "V1->V2 请求字段映射: " . json_encode($requestMapping, JSON_UNESCAPED_UNICODE) . "\n";

$responseMapping = $adapter->getFieldMapping('v1', 'v2', 'user.get', 'response');
echo "V1->V2 响应字段映射: " . json_encode($responseMapping, JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";

// 4. 测试数据类型转换
echo "4. 测试数据类型转换\n";
echo "-----------------------------------\n";

$testValues = [
    ['123', 'string', 'integer'],
    ['123.45', 'string', 'float'],
    ['true', 'string', 'boolean'],
    [1, 'integer', 'string'],
    [null, 'null', 'string']
];

foreach ($testValues as [$value, $fromType, $toType]) {
    $converted = $adapter->convertDataType($value, $fromType, $toType);
    echo "转换 '{$value}' 从 {$fromType} 到 {$toType}: " . var_export($converted, true) . "\n";
}

echo "\n";

// 5. 测试默认值处理
echo "5. 测试默认值处理\n";
echo "-----------------------------------\n";

$incompleteData = [
    'userid' => 'user123'
];

echo "原始数据: " . json_encode($incompleteData, JSON_UNESCAPED_UNICODE) . "\n";

$dataWithDefaults = $adapter->applyDefaultValues($incompleteData, 'v1', 'user.get', 'request');
echo "应用默认值后: " . json_encode($dataWithDefaults, JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";

// 6. 测试向下兼容保证
echo "6. 测试向下兼容保证\n";
echo "-----------------------------------\n";

$compatibilityTests = [
    ['v1', 'v2', 'user.get'],
    ['v1', 'v2', 'user.create'],
    ['v1', 'v2', 'message.recall'],
    ['v2', 'v1', 'user.get']
];

foreach ($compatibilityTests as [$from, $to, $method]) {
    $isCompatible = $adapter->isCompatible($from, $to, $method);
    $status = $isCompatible ? '✓ 兼容' : '✗ 不兼容';
    echo "{$from} -> {$to} ({$method}): {$status}\n";
}

echo "\n";

// 7. 测试自定义适配器注册
echo "7. 测试自定义适配器注册\n";
echo "-----------------------------------\n";

$adapter->registerCustomAdapter('custom.method', 'v1', 'v2', function($data, $fromVersion, $toVersion, $method) {
    echo "执行自定义适配器: {$method} ({$fromVersion} -> {$toVersion})\n";
    $data['custom_field'] = 'added_by_custom_adapter';
    return $data;
});

$customData = ['original' => 'data'];
echo "原始数据: " . json_encode($customData, JSON_UNESCAPED_UNICODE) . "\n";

try {
    $adaptedData = $adapter->adaptRequestParams($customData, 'v1', 'v2', 'custom.method');
    echo "自定义适配后: " . json_encode($adaptedData, JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "自定义适配失败: " . $e->getMessage() . "\n";
}

echo "\n";

// 8. 测试支持的版本列表
echo "8. 测试支持的版本列表\n";
echo "-----------------------------------\n";

$supportedVersions = $adapter->getSupportedVersions();
echo "支持的版本: " . implode(', ', $supportedVersions) . "\n";

echo "\n";

// 9. 测试适配统计信息
echo "9. 测试适配统计信息\n";
echo "-----------------------------------\n";

$stats = $adapter->getAdaptationStats();
echo "适配统计信息:\n";
echo "- 总适配次数: " . $stats['total_adaptations'] . "\n";
echo "- 成功次数: " . $stats['successful_adaptations'] . "\n";
echo "- 失败次数: " . $stats['failed_adaptations'] . "\n";
echo "- 方法统计: " . json_encode($stats['method_stats'], JSON_UNESCAPED_UNICODE) . "\n";

echo "\n";

// 10. 测试错误处理
echo "10. 测试错误处理\n";
echo "-----------------------------------\n";

try {
    $adapter->adaptRequestParams([], 'v3', 'v2', 'test.method');
} catch (Exception $e) {
    echo "预期错误 (不支持的版本): " . $e->getMessage() . "\n";
}

try {
    $adapter->adaptRequestParams([], 'v1', 'v3', 'test.method');
} catch (Exception $e) {
    echo "预期错误 (不支持的目标版本): " . $e->getMessage() . "\n";
}

echo "\n=== 版本适配器测试完成 ===\n";

echo "\n=== 日志记录 ===\n";
$logs = $logger->getLogs();
echo "共记录 " . count($logs) . " 条日志\n";