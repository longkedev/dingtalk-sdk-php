<?php

declare(strict_types=1);

namespace DingTalk\Auth;

use DingTalk\Auth\Strategies\AbstractAuthStrategy;
use DingTalk\Auth\Strategies\InternalAppAuthStrategy;
use DingTalk\Auth\Strategies\ThirdPartyEnterpriseAuthStrategy;
use DingTalk\Auth\Strategies\ThirdPartyPersonalAuthStrategy;
use DingTalk\Contracts\AuthStrategyInterface;
use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\CacheInterface;
use DingTalk\Exceptions\AuthException;
use Psr\Log\LoggerInterface;

/**
 * 认证策略工厂
 * 
 * 负责创建和管理不同类型的认证策略
 */
class AuthStrategyFactory
{
    /**
     * 支持的认证策略
     */
    private array $supportedStrategies = [
        'internal_app' => InternalAppAuthStrategy::class,
        'third_party_enterprise' => ThirdPartyEnterpriseAuthStrategy::class,
        'third_party_personal' => ThirdPartyPersonalAuthStrategy::class,
    ];

    /**
     * 配置管理器
     */
    private ConfigInterface $config;

    /**
     * HTTP客户端
     */
    private HttpClientInterface $httpClient;

    /**
     * 缓存管理器
     */
    private CacheInterface $cache;

    /**
     * 日志记录器
     */
    private ?LoggerInterface $logger;

    /**
     * 策略实例缓存
     */
    private array $strategyInstances = [];

    /**
     * 构造函数
     */
    public function __construct(
        ConfigInterface $config,
        HttpClientInterface $httpClient,
        CacheInterface $cache,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * 创建认证策略
     * 
     * @param string $strategyType 策略类型
     * @return AuthStrategyInterface
     * @throws AuthException
     */
    public function createStrategy(string $strategyType): AuthStrategyInterface
    {
        // 检查是否已有实例
        if (isset($this->strategyInstances[$strategyType])) {
            return $this->strategyInstances[$strategyType];
        }

        // 检查策略类型是否支持
        if (!isset($this->supportedStrategies[$strategyType])) {
            throw new AuthException("Unsupported auth strategy: {$strategyType}");
        }

        $strategyClass = $this->supportedStrategies[$strategyType];

        // 创建策略实例
        $strategy = new $strategyClass(
            $this->config,
            $this->httpClient,
            $this->cache,
            $this->logger
        );

        // 缓存实例
        $this->strategyInstances[$strategyType] = $strategy;

        $this->log('info', 'Auth strategy created', [
            'strategy_type' => $strategyType,
            'strategy_class' => $strategyClass,
        ]);

        return $strategy;
    }

    /**
     * 根据配置自动选择策略
     * 
     * @return AuthStrategyInterface
     * @throws AuthException
     */
    public function createAutoStrategy(): AuthStrategyInterface
    {
        $strategyType = $this->detectStrategyType();
        return $this->createStrategy($strategyType);
    }

    /**
     * 获取所有支持的策略类型
     * 
     * @return array
     */
    public function getSupportedStrategies(): array
    {
        return array_keys($this->supportedStrategies);
    }

    /**
     * 检查策略是否支持
     * 
     * @param string $strategyType
     * @return bool
     */
    public function isStrategySupported(string $strategyType): bool
    {
        return isset($this->supportedStrategies[$strategyType]);
    }

    /**
     * 获取策略信息
     * 
     * @param string $strategyType
     * @return array
     * @throws AuthException
     */
    public function getStrategyInfo(string $strategyType): array
    {
        if (!$this->isStrategySupported($strategyType)) {
            throw new AuthException("Unsupported auth strategy: {$strategyType}");
        }

        $strategy = $this->createStrategy($strategyType);

        return [
            'type' => $strategyType,
            'class' => get_class($strategy),
            'supported_methods' => $strategy->getSupportedAuthMethods(),
            'oauth2_config' => $strategy->getOAuth2Config(),
        ];
    }

    /**
     * 批量创建策略
     * 
     * @param array $strategyTypes
     * @return array
     */
    public function createMultipleStrategies(array $strategyTypes): array
    {
        $strategies = [];

        foreach ($strategyTypes as $strategyType) {
            try {
                $strategies[$strategyType] = $this->createStrategy($strategyType);
            } catch (AuthException $e) {
                $this->log('warning', 'Failed to create strategy', [
                    'strategy_type' => $strategyType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $strategies;
    }

    /**
     * 清除策略实例缓存
     * 
     * @param string|null $strategyType 指定策略类型，null表示清除所有
     */
    public function clearStrategyCache(?string $strategyType = null): void
    {
        if ($strategyType === null) {
            $this->strategyInstances = [];
            $this->log('info', 'All strategy instances cleared');
        } elseif (isset($this->strategyInstances[$strategyType])) {
            unset($this->strategyInstances[$strategyType]);
            $this->log('info', 'Strategy instance cleared', [
                'strategy_type' => $strategyType,
            ]);
        }
    }

    /**
     * 注册自定义策略
     * 
     * @param string $strategyType
     * @param string $strategyClass
     * @throws AuthException
     */
    public function registerStrategy(string $strategyType, string $strategyClass): void
    {
        // 检查类是否存在
        if (!class_exists($strategyClass)) {
            throw new AuthException("Strategy class not found: {$strategyClass}");
        }

        // 检查类是否实现了正确的接口
        if (!is_subclass_of($strategyClass, AuthStrategyInterface::class)) {
            throw new AuthException("Strategy class must implement AuthStrategyInterface: {$strategyClass}");
        }

        // 注册策略
        $this->supportedStrategies[$strategyType] = $strategyClass;

        $this->log('info', 'Custom strategy registered', [
            'strategy_type' => $strategyType,
            'strategy_class' => $strategyClass,
        ]);
    }

    /**
     * 自动检测策略类型
     * 
     * @return string
     * @throws AuthException
     */
    private function detectStrategyType(): string
    {
        // 从配置中获取策略类型
        $configuredStrategy = $this->config->get('auth.strategy', '');
        if ($configuredStrategy && $this->isStrategySupported($configuredStrategy)) {
            return $configuredStrategy;
        }

        // 根据配置参数自动检测
        $appType = $this->config->get('app_type', '');
        $suiteKey = $this->config->get('suite_key', '');
        $corpId = $this->config->get('corp_id', '');

        // 第三方企业应用
        if ($suiteKey && $corpId) {
            return 'third_party_enterprise';
        }

        // 第三方个人应用
        if ($appType === 'personal' || $this->config->get('auth.personal_mode', false)) {
            return 'third_party_personal';
        }

        // 默认为企业内部应用
        return 'internal_app';
    }

    /**
     * 记录日志
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * 获取策略统计信息
     * 
     * @return array
     */
    public function getStrategyStats(): array
    {
        return [
            'supported_strategies' => count($this->supportedStrategies),
            'created_instances' => count($this->strategyInstances),
            'strategy_types' => array_keys($this->supportedStrategies),
            'active_instances' => array_keys($this->strategyInstances),
        ];
    }

    /**
     * 验证策略配置
     * 
     * @param string $strategyType
     * @return array
     */
    public function validateStrategyConfig(string $strategyType): array
    {
        $errors = [];
        $warnings = [];

        try {
            $strategy = $this->createStrategy($strategyType);
            
            // 检查基本配置
            $credentials = $strategy->getCredentials();
            if (empty($credentials['app_key'])) {
                $errors[] = 'app_key is required';
            }
            if (empty($credentials['app_secret'])) {
                $errors[] = 'app_secret is required';
            }

            // 策略特定检查
            switch ($strategyType) {
                case 'third_party_enterprise':
                    if (!$this->config->get('suite_key')) {
                        $warnings[] = 'suite_key is recommended for third party enterprise';
                    }
                    break;
                    
                case 'third_party_personal':
                    if (!$this->config->get('redirect_uri')) {
                        $warnings[] = 'redirect_uri is required for OAuth2 flow';
                    }
                    break;
            }

        } catch (AuthException $e) {
            $errors[] = $e->getMessage();
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}