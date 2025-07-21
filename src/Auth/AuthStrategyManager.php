<?php

declare(strict_types=1);

namespace DingTalk\Auth;

use DingTalk\Contracts\AuthStrategyInterface;
use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\CacheInterface;
use DingTalk\Exceptions\AuthException;
use Psr\Log\LoggerInterface;

/**
 * 认证策略管理器
 * 
 * 统一管理和使用认证策略
 */
class AuthStrategyManager
{
    /**
     * 策略工厂
     */
    private AuthStrategyFactory $strategyFactory;

    /**
     * 当前活动策略
     */
    private ?AuthStrategyInterface $activeStrategy = null;

    /**
     * 配置管理器
     */
    private ConfigInterface $config;

    /**
     * 日志记录器
     */
    private ?LoggerInterface $logger;

    /**
     * 策略切换历史
     */
    private array $strategyHistory = [];

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
        $this->logger = $logger;
        $this->strategyFactory = new AuthStrategyFactory($config, $httpClient, $cache, $logger);
    }

    /**
     * 设置活动策略
     * 
     * @param string $strategyType
     * @return self
     * @throws AuthException
     */
    public function setStrategy(string $strategyType): self
    {
        $oldStrategy = $this->activeStrategy ? $this->activeStrategy->getStrategyType() : null;
        
        $this->activeStrategy = $this->strategyFactory->createStrategy($strategyType);
        
        // 记录策略切换历史
        $this->strategyHistory[] = [
            'timestamp' => time(),
            'from' => $oldStrategy,
            'to' => $strategyType,
        ];

        $this->log('info', 'Auth strategy changed', [
            'from' => $oldStrategy,
            'to' => $strategyType,
        ]);

        return $this;
    }

    /**
     * 自动设置策略
     * 
     * @return self
     * @throws AuthException
     */
    public function setAutoStrategy(): self
    {
        $this->activeStrategy = $this->strategyFactory->createAutoStrategy();
        
        $this->log('info', 'Auto auth strategy set', [
            'strategy_type' => $this->activeStrategy->getStrategyType(),
        ]);

        return $this;
    }

    /**
     * 获取当前活动策略
     * 
     * @return AuthStrategyInterface
     * @throws AuthException
     */
    public function getActiveStrategy(): AuthStrategyInterface
    {
        if (!$this->activeStrategy) {
            $this->setAutoStrategy();
        }

        return $this->activeStrategy;
    }

    /**
     * 获取访问令牌
     * 
     * @param bool $forceRefresh
     * @return string
     * @throws AuthException
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        return $this->getActiveStrategy()->getAccessToken($forceRefresh);
    }

    /**
     * 刷新访问令牌
     * 
     * @return string
     * @throws AuthException
     */
    public function refreshAccessToken(): string
    {
        return $this->getActiveStrategy()->refreshAccessToken();
    }

    /**
     * 检查令牌是否有效
     * 
     * @param string|null $token
     * @return bool
     */
    public function isTokenValid(?string $token = null): bool
    {
        return $this->getActiveStrategy()->isTokenValid($token);
    }

    /**
     * 获取用户授权URL
     * 
     * @param string $redirectUri
     * @param string $state
     * @param array $scopes
     * @return string
     * @throws AuthException
     */
    public function getUserAuthUrl(string $redirectUri, string $state = '', array $scopes = []): string
    {
        return $this->getActiveStrategy()->getUserAuthUrl($redirectUri, $state, $scopes);
    }

    /**
     * 通过授权码获取用户信息
     * 
     * @param string $authCode
     * @param string $state
     * @return array
     * @throws AuthException
     */
    public function getUserInfoByAuthCode(string $authCode, string $state = ''): array
    {
        return $this->getActiveStrategy()->getUserInfoByAuthCode($authCode, $state);
    }

    /**
     * 获取JSAPI签名
     * 
     * @param string $url
     * @param string $nonce
     * @param int $timestamp
     * @return array
     * @throws AuthException
     */
    public function getJsApiSignature(string $url, string $nonce, int $timestamp): array
    {
        return $this->getActiveStrategy()->getJsApiSignature($url, $nonce, $timestamp);
    }

    /**
     * 验证签名
     * 
     * @param array $params
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    public function verifySignature(array $params, string $signature, string $secret): bool
    {
        return $this->getActiveStrategy()->verifySignature($params, $signature, $secret);
    }

    /**
     * 生成签名
     * 
     * @param array $params
     * @param string $secret
     * @return string
     */
    public function generateSignature(array $params, string $secret): string
    {
        return $this->getActiveStrategy()->generateSignature($params, $secret);
    }

    /**
     * 设置应用凭证
     * 
     * @param string $appKey
     * @param string $appSecret
     * @return self
     */
    public function setCredentials(string $appKey, string $appSecret): self
    {
        if ($this->activeStrategy) {
            $this->activeStrategy->setCredentials($appKey, $appSecret);
        }

        return $this;
    }

    /**
     * 获取应用凭证
     * 
     * @return array
     */
    public function getCredentials(): array
    {
        return $this->activeStrategy ? $this->activeStrategy->getCredentials() : [];
    }

    /**
     * 处理免登认证
     * 
     * @param string $authCode
     * @return array
     * @throws AuthException
     */
    public function handleSsoAuth(string $authCode): array
    {
        return $this->getActiveStrategy()->handleSsoAuth($authCode);
    }

    /**
     * 获取OAuth2.0配置
     * 
     * @return array
     */
    public function getOAuth2Config(): array
    {
        return $this->activeStrategy ? $this->activeStrategy->getOAuth2Config() : [];
    }

    /**
     * 获取支持的认证方式
     * 
     * @return array
     */
    public function getSupportedAuthMethods(): array
    {
        return $this->activeStrategy ? $this->activeStrategy->getSupportedAuthMethods() : [];
    }

    /**
     * 获取当前策略类型
     * 
     * @return string|null
     */
    public function getCurrentStrategyType(): ?string
    {
        return $this->activeStrategy ? $this->activeStrategy->getStrategyType() : null;
    }

    /**
     * 获取策略工厂
     * 
     * @return AuthStrategyFactory
     */
    public function getStrategyFactory(): AuthStrategyFactory
    {
        return $this->strategyFactory;
    }

    /**
     * 批量测试策略
     * 
     * @param array $strategyTypes
     * @return array
     */
    public function testStrategies(array $strategyTypes = []): array
    {
        if (empty($strategyTypes)) {
            $strategyTypes = $this->strategyFactory->getSupportedStrategies();
        }

        $results = [];

        foreach ($strategyTypes as $strategyType) {
            $results[$strategyType] = $this->testStrategy($strategyType);
        }

        return $results;
    }

    /**
     * 测试单个策略
     * 
     * @param string $strategyType
     * @return array
     */
    public function testStrategy(string $strategyType): array
    {
        $result = [
            'strategy_type' => $strategyType,
            'success' => false,
            'error' => null,
            'config_valid' => false,
            'token_obtainable' => false,
            'execution_time' => 0,
        ];

        $startTime = microtime(true);

        try {
            // 验证配置
            $configValidation = $this->strategyFactory->validateStrategyConfig($strategyType);
            $result['config_valid'] = $configValidation['valid'];
            $result['config_errors'] = $configValidation['errors'];
            $result['config_warnings'] = $configValidation['warnings'];

            if ($configValidation['valid']) {
                // 尝试获取访问令牌
                $originalStrategy = $this->activeStrategy;
                $this->setStrategy($strategyType);
                
                $token = $this->getAccessToken();
                $result['token_obtainable'] = !empty($token);
                $result['success'] = true;

                // 恢复原策略
                if ($originalStrategy) {
                    $this->activeStrategy = $originalStrategy;
                }
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        $result['execution_time'] = microtime(true) - $startTime;

        return $result;
    }

    /**
     * 获取策略切换历史
     * 
     * @param int $limit
     * @return array
     */
    public function getStrategyHistory(int $limit = 10): array
    {
        return array_slice(array_reverse($this->strategyHistory), 0, $limit);
    }

    /**
     * 清除策略历史
     */
    public function clearStrategyHistory(): void
    {
        $this->strategyHistory = [];
    }

    /**
     * 获取管理器统计信息
     * 
     * @return array
     */
    public function getStats(): array
    {
        $factoryStats = $this->strategyFactory->getStrategyStats();
        
        return [
            'current_strategy' => $this->getCurrentStrategyType(),
            'strategy_switches' => count($this->strategyHistory),
            'factory_stats' => $factoryStats,
            'last_switch' => !empty($this->strategyHistory) ? end($this->strategyHistory) : null,
        ];
    }

    /**
     * 重置管理器状态
     */
    public function reset(): void
    {
        $this->activeStrategy = null;
        $this->strategyHistory = [];
        $this->strategyFactory->clearStrategyCache();

        $this->log('info', 'Auth strategy manager reset');
    }

    /**
     * 预热策略
     * 
     * @param array $strategyTypes
     * @return array
     */
    public function warmupStrategies(array $strategyTypes = []): array
    {
        if (empty($strategyTypes)) {
            $strategyTypes = $this->strategyFactory->getSupportedStrategies();
        }

        $results = [];

        foreach ($strategyTypes as $strategyType) {
            try {
                $strategy = $this->strategyFactory->createStrategy($strategyType);
                $results[$strategyType] = [
                    'success' => true,
                    'class' => get_class($strategy),
                ];
            } catch (\Exception $e) {
                $results[$strategyType] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $this->log('info', 'Strategies warmed up', [
            'strategy_types' => $strategyTypes,
            'results' => $results,
        ]);

        return $results;
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
}