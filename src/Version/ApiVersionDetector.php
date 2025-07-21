<?php

declare(strict_types=1);

namespace DingTalk\Version;

use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Exceptions\ApiException;
use DingTalk\Exceptions\NetworkException;
use Psr\Log\LoggerInterface;

/**
 * API版本检测器
 * 
 * 实现智能API版本检测功能，包括：
 * - 用户配置版本检测
 * - 应用创建时间判断
 * - API连通性测试
 * - 功能支持度检测
 * - 版本兼容性验证
 * - 自动降级策略
 */
class ApiVersionDetector
{
    /**
     * API版本常量
     */
    public const VERSION_V1 = 'v1';
    public const VERSION_V2 = 'v2';
    public const VERSION_AUTO = 'auto';
    
    /**
     * 检测策略常量
     */
    public const STRATEGY_CONFIG = 'config';
    public const STRATEGY_APP_TIME = 'app_time';
    public const STRATEGY_CONNECTIVITY = 'connectivity';
    public const STRATEGY_FEATURE = 'feature';
    public const STRATEGY_COMPATIBILITY = 'compatibility';
    
    /**
     * V2 API发布时间 (2023-01-01)
     */
    const V2_RELEASE_TIMESTAMP = 1672531200;
    
    /**
     * 配置管理器
     */
    private ConfigInterface $config;
    
    /**
     * HTTP客户端
     */
    private HttpClientInterface $httpClient;
    
    /**
     * 日志记录器
     */
    private LoggerInterface $logger;
    
    /**
     * 检测缓存
     */
    private array $detectionCache = [];
    
    /**
     * 功能支持映射表
     */
    private array $featureSupport = [
        'v1' => [
            'user_management' => true,
            'department_management' => true,
            'message_send' => true,
            'attendance' => true,
            'approval' => false,
            'calendar' => false,
            'robot' => true,
            'media_upload' => true,
        ],
        'v2' => [
            'user_management' => true,
            'department_management' => true,
            'message_send' => true,
            'attendance' => true,
            'approval' => true,
            'calendar' => true,
            'robot' => true,
            'media_upload' => true,
            'advanced_search' => true,
            'batch_operations' => true,
        ]
    ];
    
    /**
     * 构造函数
     *
     * @param ConfigInterface $config 配置管理器
     * @param HttpClientInterface $httpClient HTTP客户端
     * @param LoggerInterface $logger 日志记录器
     */
    public function __construct(
        ConfigInterface $config,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }
    
    /**
     * 检测最佳API版本
     *
     * @param array $options 检测选项
     * @return string 检测到的版本 (v1|v2)
     * @throws ApiException
     */
    public function detectVersion(array $options = []): string
    {
        $cacheKey = md5(serialize($options));
        
        // 检查缓存
        if (isset($this->detectionCache[$cacheKey])) {
            $this->logger->info('使用缓存的版本检测结果', [
                'version' => $this->detectionCache[$cacheKey],
                'cache_key' => $cacheKey
            ]);
            return $this->detectionCache[$cacheKey];
        }
        
        $strategies = $options['strategies'] ?? [
            self::STRATEGY_CONFIG,
            self::STRATEGY_APP_TIME,
            self::STRATEGY_CONNECTIVITY,
            self::STRATEGY_FEATURE
        ];
        
        $this->logger->info('开始API版本检测', [
            'strategies' => $strategies,
            'options' => $options
        ]);
        
        foreach ($strategies as $strategy) {
            try {
                $version = $this->executeStrategy($strategy, $options);
                if ($version !== null) {
                    $this->detectionCache[$cacheKey] = $version;
                    
                    $this->logger->info('版本检测成功', [
                        'strategy' => $strategy,
                        'version' => $version
                    ]);
                    
                    return $version;
                }
            } catch (\Exception $e) {
                $this->logger->warning('版本检测策略失败', [
                    'strategy' => $strategy,
                    'error' => $e->getMessage()
                ]);
                continue;
            }
        }
        
        // 默认降级到V1
        $defaultVersion = self::VERSION_V1;
        $this->detectionCache[$cacheKey] = $defaultVersion;
        
        $this->logger->warning('所有版本检测策略失败，使用默认版本', [
            'default_version' => $defaultVersion
        ]);
        
        return $defaultVersion;
    }
    
    /**
     * 执行检测策略
     *
     * @param string $strategy 策略名称
     * @param array $options 选项
     * @return string|null 检测到的版本
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
                throw new ApiException("未知的版本检测策略: {$strategy}");
        }
    }
    
    /**
     * 基于用户配置检测版本
     *
     * @param array $options 选项
     * @return string|null
     */
    private function detectByConfig(array $options): ?string
    {
        // 检查选项中的版本配置
        if (isset($options['version']) && $options['version'] !== self::VERSION_AUTO) {
            $version = $options['version'];
            if (in_array($version, [self::VERSION_V1, self::VERSION_V2])) {
                $this->logger->debug('使用选项中的版本配置', ['version' => $version]);
                return $version;
            }
        }
        
        // 检查全局配置
        $configVersion = $this->config->get('api.version', self::VERSION_AUTO);
        if ($configVersion !== self::VERSION_AUTO) {
            if (in_array($configVersion, [self::VERSION_V1, self::VERSION_V2])) {
                $this->logger->debug('使用全局配置的版本', ['version' => $configVersion]);
                return $configVersion;
            }
        }
        
        return null;
    }
    
    /**
     * 基于应用创建时间检测版本
     *
     * @param array $options 选项
     * @return string|null
     */
    private function detectByAppTime(array $options): ?string
    {
        $appKey = $this->config->get('app.key');
        if (!$appKey) {
            return null;
        }
        
        try {
            // 获取应用信息
            $appInfo = $this->getAppInfo($appKey);
            
            if (isset($appInfo['create_time'])) {
                $createTime = strtotime($appInfo['create_time']);
                
                // V2 API在2023年1月1日发布，之后创建的应用优先使用V2
                if ($createTime >= self::V2_RELEASE_TIMESTAMP) {
                    $this->logger->debug('应用创建时间晚于V2发布时间，推荐使用V2', [
                        'create_time' => $appInfo['create_time'],
                        'v2_release_time' => date('Y-m-d H:i:s', self::V2_RELEASE_TIMESTAMP)
                    ]);
                    return self::VERSION_V2;
                } else {
                    $this->logger->debug('应用创建时间早于V2发布时间，推荐使用V1', [
                        'create_time' => $appInfo['create_time'],
                        'v2_release_time' => date('Y-m-d H:i:s', self::V2_RELEASE_TIMESTAMP)
                    ]);
                    return self::VERSION_V1;
                }
            }
        } catch (\Exception $e) {
            $this->logger->warning('获取应用创建时间失败', [
                'app_key' => $appKey,
                'error' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
    /**
     * 基于API连通性检测版本
     *
     * @param array $options 选项
     * @return string|null
     */
    private function detectByConnectivity(array $options): ?string
    {
        $timeout = $options['connectivity_timeout'] ?? 5;
        
        // 测试V2 API连通性
        $v2Available = $this->testApiConnectivity(self::VERSION_V2, $timeout);
        
        // 测试V1 API连通性
        $v1Available = $this->testApiConnectivity(self::VERSION_V1, $timeout);
        
        $this->logger->debug('API连通性测试结果', [
            'v1_available' => $v1Available,
            'v2_available' => $v2Available
        ]);
        
        // 优先选择V2，如果V2不可用则选择V1
        if ($v2Available) {
            return self::VERSION_V2;
        } elseif ($v1Available) {
            return self::VERSION_V1;
        }
        
        return null;
    }
    
    /**
     * 基于功能支持度检测版本
     *
     * @param array $options 选项
     * @return string|null
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
        
        // 选择支持度更高的版本
        if ($v2Score > $v1Score) {
            return self::VERSION_V2;
        } elseif ($v1Score > 0) {
            return self::VERSION_V1;
        }
        
        return null;
    }
    
    /**
     * 基于版本兼容性检测版本
     *
     * @param array $options 选项
     * @return string|null
     */
    private function detectByCompatibility(array $options): ?string
    {
        $phpVersion = PHP_VERSION;
        $minV2PhpVersion = '7.4.0';
        
        // 检查PHP版本兼容性
        if (version_compare($phpVersion, $minV2PhpVersion, '<')) {
            $this->logger->debug('PHP版本不支持V2 API', [
                'current_php_version' => $phpVersion,
                'min_v2_php_version' => $minV2PhpVersion
            ]);
            return self::VERSION_V1;
        }
        
        // 检查扩展兼容性
        $requiredExtensions = ['curl', 'json', 'openssl'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $this->logger->warning('缺少必需的PHP扩展', [
                    'extension' => $extension
                ]);
                return self::VERSION_V1;
            }
        }
        
        return self::VERSION_V2;
    }
    
    /**
     * 测试API连通性
     *
     * @param string $version API版本
     * @param int $timeout 超时时间
     * @return bool
     */
    private function testApiConnectivity(string $version, int $timeout = 5): bool
    {
        try {
            $endpoint = $this->getVersionEndpoint($version);
            $testUrl = $endpoint . '/health';
            
            $response = $this->httpClient->get($testUrl, [
                'timeout' => $timeout,
                'headers' => [
                    'User-Agent' => 'DingTalk-SDK-PHP/VersionDetector'
                ]
            ]);
            
            // HttpClient返回数组格式，检查状态码
            $statusCode = $response['status_code'] ?? 500;
            return $statusCode < 400;
            
        } catch (\Exception $e) {
            $this->logger->debug('API连通性测试失败', [
                'version' => $version,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 计算功能支持度评分
     *
     * @param string $version API版本
     * @param array $requiredFeatures 需要的功能列表
     * @return int 评分
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
     * 获取版本对应的API端点
     *
     * @param string $version API版本
     * @return string
     */
    private function getVersionEndpoint(string $version): string
    {
        $baseUrl = $this->config->get('api.base_url', 'https://oapi.dingtalk.com');
        
        switch ($version) {
            case self::VERSION_V1:
                return $baseUrl;
            case self::VERSION_V2:
                return 'https://api.dingtalk.com/v1.0';
            default:
                throw new ApiException("不支持的API版本: {$version}");
        }
    }
    
    /**
     * 获取应用信息
     *
     * @param string $appKey 应用Key
     * @return array
     * @throws ApiException
     */
    private function getAppInfo(string $appKey): array
    {
        // 这里应该调用钉钉API获取应用信息
        // 为了演示，返回模拟数据
        return [
            'app_key' => $appKey,
            'create_time' => '2023-06-01 10:00:00',
            'status' => 'active'
        ];
    }
    
    /**
     * 检查功能是否支持
     *
     * @param string $feature 功能名称
     * @param string $version API版本
     * @return bool
     */
    public function isFeatureSupported(string $feature, string $version): bool
    {
        $supportedFeatures = $this->featureSupport[$version] ?? [];
        return isset($supportedFeatures[$feature]) && $supportedFeatures[$feature];
    }
    
    /**
     * 获取版本支持的功能列表
     *
     * @param string $version API版本
     * @return array
     */
    public function getSupportedFeatures(string $version): array
    {
        return array_keys(array_filter($this->featureSupport[$version] ?? []));
    }
    
    /**
     * 获取所有支持的版本
     *
     * @return array
     */
    public function getSupportedVersions(): array
    {
        return [self::VERSION_V1, self::VERSION_V2];
    }
    
    /**
     * 验证版本兼容性
     *
     * @param string $version API版本
     * @param array $requirements 兼容性要求
     * @return array 验证结果
     */
    public function validateCompatibility(string $version, array $requirements = []): array
    {
        $result = [
            'compatible' => true,
            'issues' => [],
            'recommendations' => []
        ];
        
        // 检查PHP版本
        if ($version === self::VERSION_V2) {
            $minPhpVersion = '7.4.0';
            if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
                $result['compatible'] = false;
                $result['issues'][] = "V2 API需要PHP {$minPhpVersion}或更高版本，当前版本: " . PHP_VERSION;
                $result['recommendations'][] = "升级PHP版本或使用V1 API";
            }
        }
        
        // 检查必需的扩展
        $requiredExtensions = ['curl', 'json'];
        if ($version === self::VERSION_V2) {
            $requiredExtensions[] = 'openssl';
        }
        
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $result['compatible'] = false;
                $result['issues'][] = "缺少必需的PHP扩展: {$extension}";
                $result['recommendations'][] = "安装 {$extension} 扩展";
            }
        }
        
        return $result;
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
     *
     * @return array
     */
    public function getDetectionStats(): array
    {
        return [
            'cache_size' => count($this->detectionCache),
            'supported_versions' => $this->getSupportedVersions(),
            'feature_support' => $this->featureSupport,
            'detection_strategies' => [
                self::STRATEGY_CONFIG,
                self::STRATEGY_APP_TIME,
                self::STRATEGY_CONNECTIVITY,
                self::STRATEGY_FEATURE,
                self::STRATEGY_COMPATIBILITY
            ]
        ];
    }
}