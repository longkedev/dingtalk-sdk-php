<?php

// 简化的版本检测器测试
// 避免复杂的依赖，专注于核心功能测试

// 加载必要的接口
require_once __DIR__ . '/src/Contracts/ConfigInterface.php';
require_once __DIR__ . '/src/Contracts/HttpClientInterface.php';

use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;

/**
 * 模拟配置接口
 */
class MockConfig implements ConfigInterface
{
    private $config;
    
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $current = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }
        
        $current = $value;
    }
    
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
    
    public function all(): array
    {
        return $this->config;
    }
    
    public function merge(array $config): void
    {
        $this->config = array_merge_recursive($this->config, $config);
    }
}

/**
 * 模拟HTTP客户端
 */
class MockHttpClient implements HttpClientInterface
{
    private $lastStatusCode = 200;
    private $lastHeaders = [];
    
    public function get(string $url, array $query = [], array $headers = []): array
    {
        return ['status' => 'ok', 'data' => []];
    }
    
    public function post(string $url, array $data = [], array $headers = []): array
    {
        return ['status' => 'ok', 'data' => []];
    }
    
    public function put(string $url, array $data = [], array $headers = []): array
    {
        return ['status' => 'ok', 'data' => []];
    }
    
    public function delete(string $url, array $query = [], array $headers = []): array
    {
        return ['status' => 'ok', 'data' => []];
    }
    
    public function patch(string $url, array $data = [], array $headers = []): array
    {
        return ['status' => 'ok', 'data' => []];
    }
    
    public function upload(string $url, array $files, array $data = [], array $headers = []): array
    {
        return ['status' => 'ok', 'data' => []];
    }
    
    public function setTimeout(int $timeout): void
    {
        // Mock implementation
    }
    
    public function setConnectTimeout(int $timeout): void
    {
        // Mock implementation
    }
    
    public function setRetries(int $retries): void
    {
        // Mock implementation
    }
    
    public function setUserAgent(string $userAgent): void
    {
        // Mock implementation
    }
    
    public function getLastResponseHeaders(): array
    {
        return $this->lastHeaders;
    }
    
    public function getLastStatusCode(): int
    {
        return $this->lastStatusCode;
    }
    
    public function addMiddleware(callable $middleware, string $name = ''): void
    {
        // Mock implementation
    }
    
    public function removeMiddleware(string $name): void
    {
        // Mock implementation
    }
    
    public function setPoolConfig(array $config): void
    {
        // Mock implementation
    }
    
    public function batchRequest(array $requests, int $concurrency = null): array
    {
        return [];
    }
    
    public function setRetryDelay(int $delay): void
    {
        // Mock implementation
    }
}

/**
 * 模拟HTTP响应
 */
class MockResponse
{
    private $statusCode;
    
    public function __construct(int $statusCode)
    {
        $this->statusCode = $statusCode;
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

/**
 * 模拟日志管理器
 */
class MockLogger implements \Psr\Log\LoggerInterface
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

// 加载版本检测器
require_once __DIR__ . '/src/Version/ApiVersionDetector.php';

use DingTalk\Version\ApiVersionDetector;

/**
 * 简化的版本检测器测试
 */
class SimpleApiVersionDetectorTest
{
    private $detector;
    
    public function __construct()
    {
        $config = new MockConfig([
            'app' => [
                'key' => 'test_app_key',
                'secret' => 'test_app_secret'
            ],
            'api' => [
                'version' => 'auto',
                'base_url' => 'https://oapi.dingtalk.com'
            ]
        ]);
        
        $httpClient = new MockHttpClient();
        $logger = new MockLogger();
        
        $this->detector = new ApiVersionDetector($config, $httpClient, $logger);
    }
    
    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "=== API版本检测器功能测试 ===\n\n";
        
        $tests = [
            'testConfigDetection' => '用户配置版本检测',
            'testFeatureSupport' => '功能支持度检测',
            'testVersionValidation' => '版本兼容性验证',
            'testAutoDetection' => '自动版本检测',
            'testCacheManagement' => '缓存管理',
            'testDetectionStats' => '统计信息'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $method => $description) {
            try {
                echo "测试: {$description}\n";
                $this->$method();
                echo "✅ 通过\n\n";
                $passed++;
            } catch (Exception $e) {
                echo "❌ 失败: " . $e->getMessage() . "\n\n";
            }
        }
        
        echo "=== 测试结果 ===\n";
        echo "通过: {$passed}/{$total}\n";
        echo "成功率: " . round(($passed / $total) * 100, 2) . "%\n\n";
        
        if ($passed === $total) {
            echo "🎉 所有测试通过！版本检测器功能正常。\n";
        }
    }
    
    /**
     * 测试配置检测
     */
    private function testConfigDetection(): void
    {
        // 测试V2配置
        $version = $this->detector->detectVersion(['version' => 'v2']);
        if ($version !== 'v2') {
            throw new Exception("配置检测失败，期望v2，实际{$version}");
        }
        
        // 测试V1配置
        $version = $this->detector->detectVersion(['version' => 'v1']);
        if ($version !== 'v1') {
            throw new Exception("配置检测失败，期望v1，实际{$version}");
        }
        
        echo "  ✓ 用户配置版本检测功能正常\n";
    }
    
    /**
     * 测试功能支持
     */
    private function testFeatureSupport(): void
    {
        // 测试V1功能支持
        $supported = $this->detector->isFeatureSupported('user_management', 'v1');
        if (!$supported) {
            throw new Exception("V1应该支持用户管理功能");
        }
        
        // 测试V2特有功能
        $supported = $this->detector->isFeatureSupported('advanced_search', 'v2');
        if (!$supported) {
            throw new Exception("V2应该支持高级搜索功能");
        }
        
        // 测试V1不支持的功能
        $supported = $this->detector->isFeatureSupported('advanced_search', 'v1');
        if ($supported) {
            throw new Exception("V1不应该支持高级搜索功能");
        }
        
        // 测试功能列表
        $v1Features = $this->detector->getSupportedFeatures('v1');
        $v2Features = $this->detector->getSupportedFeatures('v2');
        
        if (empty($v1Features) || empty($v2Features)) {
            throw new Exception("功能列表不应该为空");
        }
        
        if (count($v2Features) <= count($v1Features)) {
            throw new Exception("V2应该支持更多功能");
        }
        
        echo "  ✓ 功能支持度检测功能正常\n";
    }
    
    /**
     * 测试版本验证
     */
    private function testVersionValidation(): void
    {
        // 测试支持的版本
        $versions = $this->detector->getSupportedVersions();
        if (!in_array('v1', $versions) || !in_array('v2', $versions)) {
            throw new Exception("支持的版本列表不正确");
        }
        
        // 测试兼容性验证
        $result = $this->detector->validateCompatibility('v1');
        if (!isset($result['compatible']) || !isset($result['issues']) || !isset($result['recommendations'])) {
            throw new Exception("兼容性验证结果格式不正确");
        }
        
        echo "  ✓ 版本兼容性验证功能正常\n";
    }
    
    /**
     * 测试自动检测
     */
    private function testAutoDetection(): void
    {
        // 测试需要V2特有功能的自动检测
        $version = $this->detector->detectVersion([
            'strategies' => ['feature'],
            'required_features' => ['advanced_search', 'batch_operations']
        ]);
        
        if ($version !== 'v2') {
            throw new Exception("需要V2特有功能时应该选择V2");
        }
        
        // 测试兼容性检测
        $version = $this->detector->detectVersion([
            'strategies' => ['compatibility']
        ]);
        
        if (!in_array($version, ['v1', 'v2'])) {
            throw new Exception("兼容性检测应该返回有效版本");
        }
        
        echo "  ✓ 自动版本检测功能正常\n";
    }
    
    /**
     * 测试缓存管理
     */
    private function testCacheManagement(): void
    {
        // 执行相同的检测
        $version1 = $this->detector->detectVersion(['version' => 'v1']);
        $version2 = $this->detector->detectVersion(['version' => 'v1']);
        
        if ($version1 !== $version2) {
            throw new Exception("缓存功能异常");
        }
        
        // 清除缓存
        $this->detector->clearCache();
        
        // 再次检测
        $version3 = $this->detector->detectVersion(['version' => 'v1']);
        if ($version1 !== $version3) {
            throw new Exception("清除缓存后结果不一致");
        }
        
        echo "  ✓ 缓存管理功能正常\n";
    }
    
    /**
     * 测试统计信息
     */
    private function testDetectionStats(): void
    {
        $stats = $this->detector->getDetectionStats();
        
        $requiredKeys = ['cache_size', 'supported_versions', 'feature_support', 'detection_strategies'];
        foreach ($requiredKeys as $key) {
            if (!isset($stats[$key])) {
                throw new Exception("统计信息缺少字段: {$key}");
            }
        }
        
        echo "  ✓ 统计信息功能正常\n";
    }
}

// 运行测试
try {
    $test = new SimpleApiVersionDetectorTest();
    $test->runAllTests();
    
    echo "\n=== 版本检测器功能演示 ===\n";
    
    $detector = $test->detector ?? new ApiVersionDetector(
        new MockConfig(),
        new MockHttpClient(),
        new MockLogger()
    );
    
    // 演示各种检测场景
    echo "\n1. 配置优先检测:\n";
    echo "   指定V2: " . $detector->detectVersion(['version' => 'v2']) . "\n";
    echo "   指定V1: " . $detector->detectVersion(['version' => 'v1']) . "\n";
    
    echo "\n2. 功能需求检测:\n";
    echo "   需要高级功能: " . $detector->detectVersion([
        'strategies' => ['feature'],
        'required_features' => ['advanced_search', 'batch_operations']
    ]) . "\n";
    echo "   基础功能即可: " . $detector->detectVersion([
        'strategies' => ['feature'],
        'required_features' => ['user_management', 'message_send']
    ]) . "\n";
    
    echo "\n3. 功能支持查询:\n";
    echo "   V1支持的功能: " . implode(', ', $detector->getSupportedFeatures('v1')) . "\n";
    echo "   V2支持的功能: " . implode(', ', $detector->getSupportedFeatures('v2')) . "\n";
    
    echo "\n4. 兼容性验证:\n";
    $compatibility = $detector->validateCompatibility('v2');
    echo "   V2兼容性: " . ($compatibility['compatible'] ? '兼容' : '不兼容') . "\n";
    if (!empty($compatibility['issues'])) {
        echo "   问题: " . implode(', ', $compatibility['issues']) . "\n";
    }
    
    echo "\n5. 统计信息:\n";
    $stats = $detector->getDetectionStats();
    echo "   支持版本: " . implode(', ', $stats['supported_versions']) . "\n";
    echo "   检测策略: " . implode(', ', $stats['detection_strategies']) . "\n";
    echo "   缓存大小: " . $stats['cache_size'] . "\n";
    
} catch (Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}