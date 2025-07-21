<?php

/**
 * 版本检测器功能演示
 * 
 * 演示ApiVersionDetector的六项核心功能：
 * 1. 用户配置版本检测
 * 2. 应用创建时间判断
 * 3. API连通性测试
 * 4. 功能支持度检测
 * 5. 版本兼容性验证
 * 6. 自动降级策略
 */

require_once __DIR__ . '/src/Contracts/ConfigInterface.php';
require_once __DIR__ . '/src/Contracts/HttpClientInterface.php';

// 简单的LoggerInterface实现
interface SimpleLoggerInterface
{
    public function debug($message, array $context = []): void;
    public function info($message, array $context = []): void;
    public function warning($message, array $context = []): void;
    public function error($message, array $context = []): void;
    public function log($level, $message, array $context = []): void;
}

// 简化版本的ApiVersionDetector
class SimpleApiVersionDetector
{
    public const VERSION_V1 = 'v1';
    public const VERSION_V2 = 'v2';
    public const VERSION_AUTO = 'auto';
    
    public const STRATEGY_CONFIG = 'config';
    public const STRATEGY_APP_TIME = 'app_time';
    public const STRATEGY_CONNECTIVITY = 'connectivity';
    public const STRATEGY_FEATURE = 'feature';
    public const STRATEGY_COMPATIBILITY = 'compatibility';
    
    private $config;
    private $httpClient;
    private $logger;
    private array $detectionCache = [];
    
    private array $featureSupport = [
        'v1' => [
            'user_management' => true,
            'department_management' => true,
            'message_send' => true,
            'file_upload' => true,
            'callback_management' => true,
            'attendance_management' => false,
            'approval_management' => false,
            'calendar_management' => false,
            'live_management' => false,
            'ai_assistant' => false,
        ],
        'v2' => [
            'user_management' => true,
            'department_management' => true,
            'message_send' => true,
            'file_upload' => true,
            'callback_management' => true,
            'attendance_management' => true,
            'approval_management' => true,
            'calendar_management' => true,
            'live_management' => true,
            'ai_assistant' => true,
        ]
    ];
    
    public function __construct($config, $httpClient, $logger)
    {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }
    
    /**
     * 检测最适合的API版本
     */
    public function detectVersion(array $options = []): string
    {
        $strategies = $options['strategies'] ?? [
            self::STRATEGY_CONFIG,
            self::STRATEGY_APP_TIME,
            self::STRATEGY_CONNECTIVITY,
            self::STRATEGY_FEATURE,
            self::STRATEGY_COMPATIBILITY
        ];
        
        $this->logger->info('开始API版本检测', [
            'strategies' => $strategies,
            'options' => $options
        ]);
        
        foreach ($strategies as $strategy) {
            $version = $this->executeStrategy($strategy, $options);
            if ($version && $version !== self::VERSION_AUTO) {
                $this->logger->info('版本检测完成', [
                    'strategy' => $strategy,
                    'detected_version' => $version
                ]);
                return $version;
            }
        }
        
        // 默认返回V1版本
        $this->logger->warning('所有策略都未能确定版本，使用默认V1版本');
        return self::VERSION_V1;
    }
    
    /**
     * 执行检测策略
     */
    private function executeStrategy(string $strategy, array $options): ?string
    {
        switch ($strategy) {
            case self::STRATEGY_CONFIG:
                return $this->detectByConfig($options);
            case self::STRATEGY_APP_TIME:
                return $this->detectByAppTime($options);
            case self::STRATEGY_CONNECTIVITY:
                return $this->detectByConnectivity($options);
            case self::STRATEGY_FEATURE:
                return $this->detectByFeature($options);
            case self::STRATEGY_COMPATIBILITY:
                return $this->detectByCompatibility($options);
            default:
                return null;
        }
    }
    
    /**
     * 基于用户配置检测版本
     */
    private function detectByConfig(array $options): ?string
    {
        $configuredVersion = $this->config->get('api.version', self::VERSION_AUTO);
        
        if ($configuredVersion !== self::VERSION_AUTO) {
            $this->logger->debug('使用配置指定的API版本', [
                'configured_version' => $configuredVersion
            ]);
            return $configuredVersion;
        }
        
        return null;
    }
    
    /**
     * 基于应用创建时间检测版本
     */
    private function detectByAppTime(array $options): ?string
    {
        $appKey = $this->config->get('app.key');
        if (!$appKey) {
            return null;
        }
        
        // 模拟应用信息获取
        $appInfo = [
            'app_key' => $appKey,
            'create_time' => '2023-06-01 10:00:00'
        ];
        
        $createTime = strtotime($appInfo['create_time']);
        $v2ReleaseTime = strtotime('2023-01-01 00:00:00');
        
        if ($createTime >= $v2ReleaseTime) {
            $this->logger->debug('应用创建时间支持V2 API', [
                'app_create_time' => $appInfo['create_time'],
                'v2_release_time' => '2023-01-01 00:00:00'
            ]);
            return self::VERSION_V2;
        }
        
        return self::VERSION_V1;
    }
    
    /**
     * 基于API连通性检测版本
     */
    private function detectByConnectivity(array $options): ?string
    {
        $timeout = $options['timeout'] ?? 5;
        
        $v1Available = $this->testApiConnectivity(self::VERSION_V1, $timeout);
        $v2Available = $this->testApiConnectivity(self::VERSION_V2, $timeout);
        
        $this->logger->debug('API连通性测试结果', [
            'v1_available' => $v1Available,
            'v2_available' => $v2Available
        ]);
        
        if ($v2Available) {
            return self::VERSION_V2;
        } elseif ($v1Available) {
            return self::VERSION_V1;
        }
        
        return null;
    }
    
    /**
     * 基于功能支持度检测版本
     */
    private function detectByFeature(array $options): ?string
    {
        $requiredFeatures = $options['required_features'] ?? [];
        
        if (empty($requiredFeatures)) {
            return null;
        }
        
        $v1Score = $this->calculateFeatureScore(self::VERSION_V1, $requiredFeatures);
        $v2Score = $this->calculateFeatureScore(self::VERSION_V2, $requiredFeatures);
        
        $this->logger->debug('功能支持度评分', [
            'required_features' => $requiredFeatures,
            'v1_score' => $v1Score,
            'v2_score' => $v2Score
        ]);
        
        if ($v2Score > $v1Score) {
            return self::VERSION_V2;
        } elseif ($v1Score > 0) {
            return self::VERSION_V1;
        }
        
        return null;
    }
    
    /**
     * 基于版本兼容性检测版本
     */
    private function detectByCompatibility(array $options): ?string
    {
        $phpVersion = PHP_VERSION;
        $minV2PhpVersion = '7.4.0';
        
        if (version_compare($phpVersion, $minV2PhpVersion, '<')) {
            $this->logger->debug('PHP版本不支持V2 API', [
                'current_php_version' => $phpVersion,
                'min_v2_php_version' => $minV2PhpVersion
            ]);
            return self::VERSION_V1;
        }
        
        return self::VERSION_V2;
    }
    
    /**
     * 测试API连通性
     */
    private function testApiConnectivity(string $version, int $timeout = 5): bool
    {
        try {
            // 模拟连通性测试
            return true; // 简化实现，总是返回true
        } catch (Exception $e) {
            $this->logger->debug('API连通性测试失败', [
                'version' => $version,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 计算功能支持度评分
     */
    private function calculateFeatureScore(string $version, array $requiredFeatures): int
    {
        $supportedFeatures = $this->featureSupport[$version] ?? [];
        $score = 0;
        
        foreach ($requiredFeatures as $feature) {
            if (isset($supportedFeatures[$feature]) && $supportedFeatures[$feature]) {
                $score++;
            }
        }
        
        return $score;
    }
    
    /**
     * 检查功能是否支持
     */
    public function isFeatureSupported(string $feature, string $version): bool
    {
        $supportedFeatures = $this->featureSupport[$version] ?? [];
        return isset($supportedFeatures[$feature]) && $supportedFeatures[$feature];
    }
    
    /**
     * 获取版本支持的功能列表
     */
    public function getSupportedFeatures(string $version): array
    {
        return array_keys(array_filter($this->featureSupport[$version] ?? []));
    }
    
    /**
     * 获取所有支持的版本
     */
    public function getSupportedVersions(): array
    {
        return [self::VERSION_V1, self::VERSION_V2];
    }
    
    /**
     * 验证版本兼容性
     */
    public function validateCompatibility(string $version): array
    {
        $issues = [];
        
        if ($version === self::VERSION_V2) {
            $phpVersion = PHP_VERSION;
            $minPhpVersion = '7.4.0';
            
            if (version_compare($phpVersion, $minPhpVersion, '<')) {
                $issues[] = "PHP版本 {$phpVersion} 不支持V2 API，最低要求 {$minPhpVersion}";
            }
            
            $requiredExtensions = ['curl', 'json', 'openssl'];
            foreach ($requiredExtensions as $extension) {
                if (!extension_loaded($extension)) {
                    $issues[] = "缺少必需的PHP扩展: {$extension}";
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * 清除检测缓存
     */
    public function clearCache(): void
    {
        $this->detectionCache = [];
        $this->logger->debug('版本检测缓存已清除');
    }
    
    /**
     * 获取检测统计信息
     */
    public function getDetectionStats(): array
    {
        return [
            'supported_versions' => $this->getSupportedVersions(),
            'v1_features' => count(array_filter($this->featureSupport['v1'])),
            'v2_features' => count(array_filter($this->featureSupport['v2'])),
            'cache_size' => count($this->detectionCache),
            'php_version' => PHP_VERSION,
            'loaded_extensions' => get_loaded_extensions()
        ];
    }
}

// 简单的Mock类
class SimpleConfig
{
    private array $config = [];
    
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
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
}

class SimpleHttpClient
{
    public function get(string $url, array $options = []): array
    {
        return ['status_code' => 200, 'body' => '{"status": "ok"}'];
    }
}

class SimpleLogger implements SimpleLoggerInterface
{
    private array $logs = [];
    
    public function debug($message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
    
    public function info($message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
    
    public function warning($message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
    
    public function error($message, array $context = []): void
    {
        $this->log('error', $message, $context);
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
        if (!empty($context)) {
            echo "  Context: " . json_encode($context, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    public function getLogs(): array
    {
        return $this->logs;
    }
}

// 演示功能
echo "=== 版本检测器功能演示 ===\n\n";

// 创建实例
$config = new SimpleConfig();
$httpClient = new SimpleHttpClient();
$logger = new SimpleLogger();

$detector = new SimpleApiVersionDetector($config, $httpClient, $logger);

echo "1. 配置检测演示\n";
echo "----------------\n";
$config->set('api.version', 'v2');
$config->set('app.key', 'test_app_key');
$version = $detector->detectVersion(['strategies' => ['config']]);
echo "检测结果: {$version}\n\n";

echo "2. 应用时间检测演示\n";
echo "-------------------\n";
$config->set('api.version', 'auto'); // 重置配置
$version = $detector->detectVersion(['strategies' => ['app_time']]);
echo "检测结果: {$version}\n\n";

echo "3. 连通性检测演示\n";
echo "-----------------\n";
$version = $detector->detectVersion(['strategies' => ['connectivity']]);
echo "检测结果: {$version}\n\n";

echo "4. 功能支持度检测演示\n";
echo "---------------------\n";
$requiredFeatures = ['ai_assistant', 'live_management', 'approval_management'];
$version = $detector->detectVersion([
    'strategies' => ['feature'],
    'required_features' => $requiredFeatures
]);
echo "检测结果: {$version}\n\n";

echo "5. 兼容性检测演示\n";
echo "-----------------\n";
$version = $detector->detectVersion(['strategies' => ['compatibility']]);
echo "检测结果: {$version}\n\n";

echo "6. 功能支持查询演示\n";
echo "-------------------\n";
$features = ['user_management', 'ai_assistant', 'live_management'];
foreach ($features as $feature) {
    $v1Support = $detector->isFeatureSupported($feature, 'v1') ? '✓' : '✗';
    $v2Support = $detector->isFeatureSupported($feature, 'v2') ? '✓' : '✗';
    echo "功能: {$feature} - V1: {$v1Support}, V2: {$v2Support}\n";
}
echo "\n";

echo "7. 版本验证演示\n";
echo "---------------\n";
$v2Issues = $detector->validateCompatibility('v2');
if (empty($v2Issues)) {
    echo "V2版本兼容性: ✓ 通过\n";
} else {
    echo "V2版本兼容性问题:\n";
    foreach ($v2Issues as $issue) {
        echo "  - {$issue}\n";
    }
}
echo "\n";

echo "8. 统计信息演示\n";
echo "---------------\n";
$stats = $detector->getDetectionStats();
echo "支持的版本: " . implode(', ', $stats['supported_versions']) . "\n";
echo "V1功能数量: {$stats['v1_features']}\n";
echo "V2功能数量: {$stats['v2_features']}\n";
echo "PHP版本: {$stats['php_version']}\n";
echo "已加载扩展数量: " . count($stats['loaded_extensions']) . "\n\n";

echo "9. 自动检测演示\n";
echo "---------------\n";
$config->set('api.version', 'auto');
$version = $detector->detectVersion();
echo "自动检测结果: {$version}\n\n";

echo "=== 版本检测器演示完成 ===\n";