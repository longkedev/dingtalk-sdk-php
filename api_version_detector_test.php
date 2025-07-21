<?php

require_once __DIR__ . '/src/Version/ApiVersionDetector.php';
require_once __DIR__ . '/src/Contracts/ConfigInterface.php';
require_once __DIR__ . '/src/Contracts/HttpClientInterface.php';
require_once __DIR__ . '/src/Config/ConfigManager.php';
require_once __DIR__ . '/src/Http/HttpClient.php';
require_once __DIR__ . '/src/Log/LogManager.php';
require_once __DIR__ . '/src/Log/LogManagerFactory.php';
require_once __DIR__ . '/src/Log/LogRecord.php';
require_once __DIR__ . '/src/Log/LogHandlerInterface.php';
require_once __DIR__ . '/src/Log/Handlers/ConsoleHandler.php';
require_once __DIR__ . '/src/Log/Formatters/FormatterInterface.php';
require_once __DIR__ . '/src/Log/Formatters/LineFormatter.php';
require_once __DIR__ . '/src/Exceptions/DingTalkException.php';
require_once __DIR__ . '/src/Exceptions/ApiException.php';
require_once __DIR__ . '/src/Exceptions/NetworkException.php';

use DingTalk\Version\ApiVersionDetector;
use DingTalk\Config\ConfigManager;
use DingTalk\Http\HttpClient;
use DingTalk\Log\LogManagerFactory;

/**
 * API版本检测器测试
 */
class ApiVersionDetectorTest
{
    private $detector;
    private $config;
    private $httpClient;
    private $logger;
    
    public function __construct()
    {
        // 初始化配置
        $this->config = new ConfigManager([
            'app' => [
                'key' => 'test_app_key',
                'secret' => 'test_app_secret'
            ],
            'api' => [
                'version' => 'auto',
                'base_url' => 'https://oapi.dingtalk.com'
            ]
        ]);
        
        // 初始化HTTP客户端
        $this->httpClient = new HttpClient();
        
        // 初始化日志管理器
        $this->logger = LogManagerFactory::create([
            'handlers' => [
                [
                    'type' => 'console',
                    'level' => 'debug'
                ]
            ]
        ]);
        
        // 初始化版本检测器
        $this->detector = new ApiVersionDetector(
            $this->config,
            $this->httpClient,
            $this->logger
        );
    }
    
    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "=== API版本检测器测试开始 ===\n\n";
        
        $tests = [
            'testConfigDetection',
            'testAppTimeDetection',
            'testConnectivityDetection',
            'testFeatureDetection',
            'testCompatibilityDetection',
            'testFeatureSupport',
            'testVersionValidation',
            'testCacheManagement',
            'testDetectionStats'
        ];
        
        $passed = 0;
        $total = count($tests);
        
        foreach ($tests as $test) {
            try {
                echo "运行测试: {$test}\n";
                $this->$test();
                echo "✅ {$test} 通过\n\n";
                $passed++;
            } catch (Exception $e) {
                echo "❌ {$test} 失败: " . $e->getMessage() . "\n\n";
            }
        }
        
        echo "=== 测试结果 ===\n";
        echo "通过: {$passed}/{$total}\n";
        echo "成功率: " . round(($passed / $total) * 100, 2) . "%\n";
    }
    
    /**
     * 测试配置检测
     */
    private function testConfigDetection(): void
    {
        echo "  测试用户配置版本检测...\n";
        
        // 测试选项中的版本配置
        $version = $this->detector->detectVersion(['version' => 'v2']);
        if ($version !== 'v2') {
            throw new Exception("配置检测失败，期望v2，实际{$version}");
        }
        
        // 测试V1配置
        $version = $this->detector->detectVersion(['version' => 'v1']);
        if ($version !== 'v1') {
            throw new Exception("配置检测失败，期望v1，实际{$version}");
        }
        
        echo "  ✓ 配置检测功能正常\n";
    }
    
    /**
     * 测试应用创建时间检测
     */
    private function testAppTimeDetection(): void
    {
        echo "  测试应用创建时间判断...\n";
        
        // 由于getAppInfo是私有方法且返回模拟数据，这里主要测试逻辑
        $version = $this->detector->detectVersion([
            'strategies' => ['app_time']
        ]);
        
        // 应该返回v2（因为模拟的创建时间是2023-06-01）
        if (!in_array($version, ['v1', 'v2'])) {
            throw new Exception("应用时间检测失败，返回了无效版本: {$version}");
        }
        
        echo "  ✓ 应用创建时间检测功能正常\n";
    }
    
    /**
     * 测试连通性检测
     */
    private function testConnectivityDetection(): void
    {
        echo "  测试API连通性测试...\n";
        
        // 测试连通性检测（会失败，但不应该抛出异常）
        $version = $this->detector->detectVersion([
            'strategies' => ['connectivity'],
            'connectivity_timeout' => 1
        ]);
        
        // 应该返回有效版本或降级到v1
        if (!in_array($version, ['v1', 'v2'])) {
            throw new Exception("连通性检测失败，返回了无效版本: {$version}");
        }
        
        echo "  ✓ API连通性测试功能正常\n";
    }
    
    /**
     * 测试功能支持度检测
     */
    private function testFeatureDetection(): void
    {
        echo "  测试功能支持度检测...\n";
        
        // 测试需要V2特有功能
        $version = $this->detector->detectVersion([
            'strategies' => ['feature'],
            'required_features' => ['advanced_search', 'batch_operations']
        ]);
        
        if ($version !== 'v2') {
            throw new Exception("功能检测失败，期望v2，实际{$version}");
        }
        
        // 测试只需要V1功能
        $version = $this->detector->detectVersion([
            'strategies' => ['feature'],
            'required_features' => ['user_management', 'message_send']
        ]);
        
        if (!in_array($version, ['v1', 'v2'])) {
            throw new Exception("功能检测失败，返回了无效版本: {$version}");
        }
        
        echo "  ✓ 功能支持度检测功能正常\n";
    }
    
    /**
     * 测试兼容性检测
     */
    private function testCompatibilityDetection(): void
    {
        echo "  测试版本兼容性验证...\n";
        
        $version = $this->detector->detectVersion([
            'strategies' => ['compatibility']
        ]);
        
        if (!in_array($version, ['v1', 'v2'])) {
            throw new Exception("兼容性检测失败，返回了无效版本: {$version}");
        }
        
        echo "  ✓ 版本兼容性验证功能正常\n";
    }
    
    /**
     * 测试功能支持查询
     */
    private function testFeatureSupport(): void
    {
        echo "  测试功能支持查询...\n";
        
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
        
        // 测试获取支持的功能列表
        $v1Features = $this->detector->getSupportedFeatures('v1');
        $v2Features = $this->detector->getSupportedFeatures('v2');
        
        if (empty($v1Features) || empty($v2Features)) {
            throw new Exception("功能列表不应该为空");
        }
        
        if (count($v2Features) <= count($v1Features)) {
            throw new Exception("V2应该支持更多功能");
        }
        
        echo "  ✓ 功能支持查询功能正常\n";
    }
    
    /**
     * 测试版本验证
     */
    private function testVersionValidation(): void
    {
        echo "  测试版本验证...\n";
        
        // 测试支持的版本列表
        $versions = $this->detector->getSupportedVersions();
        if (!in_array('v1', $versions) || !in_array('v2', $versions)) {
            throw new Exception("支持的版本列表不正确");
        }
        
        // 测试兼容性验证
        $result = $this->detector->validateCompatibility('v1');
        if (!isset($result['compatible']) || !isset($result['issues']) || !isset($result['recommendations'])) {
            throw new Exception("兼容性验证结果格式不正确");
        }
        
        $result = $this->detector->validateCompatibility('v2');
        if (!isset($result['compatible'])) {
            throw new Exception("V2兼容性验证结果格式不正确");
        }
        
        echo "  ✓ 版本验证功能正常\n";
    }
    
    /**
     * 测试缓存管理
     */
    private function testCacheManagement(): void
    {
        echo "  测试缓存管理...\n";
        
        // 执行检测以生成缓存
        $version1 = $this->detector->detectVersion(['version' => 'v1']);
        $version2 = $this->detector->detectVersion(['version' => 'v1']); // 相同参数
        
        if ($version1 !== $version2) {
            throw new Exception("缓存功能异常，相同参数应返回相同结果");
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
        echo "  测试统计信息...\n";
        
        $stats = $this->detector->getDetectionStats();
        
        $requiredKeys = ['cache_size', 'supported_versions', 'feature_support', 'detection_strategies'];
        foreach ($requiredKeys as $key) {
            if (!isset($stats[$key])) {
                throw new Exception("统计信息缺少必需字段: {$key}");
            }
        }
        
        if (!is_array($stats['supported_versions']) || empty($stats['supported_versions'])) {
            throw new Exception("支持的版本列表格式不正确");
        }
        
        if (!is_array($stats['feature_support']) || empty($stats['feature_support'])) {
            throw new Exception("功能支持信息格式不正确");
        }
        
        if (!is_array($stats['detection_strategies']) || empty($stats['detection_strategies'])) {
            throw new Exception("检测策略列表格式不正确");
        }
        
        echo "  ✓ 统计信息功能正常\n";
    }
}

// 运行测试
try {
    $test = new ApiVersionDetectorTest();
    $test->runAllTests();
} catch (Exception $e) {
    echo "测试初始化失败: " . $e->getMessage() . "\n";
    echo "错误详情: " . $e->getTraceAsString() . "\n";
}