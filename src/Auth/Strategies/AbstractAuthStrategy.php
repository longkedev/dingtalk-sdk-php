<?php

declare(strict_types=1);

namespace DingTalk\Auth\Strategies;

use DingTalk\Contracts\AuthStrategyInterface;
use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\CacheInterface;
use DingTalk\Exceptions\AuthException;
use Psr\Log\LoggerInterface;

/**
 * 认证策略抽象基类
 * 
 * 提供认证策略的通用功能实现
 */
abstract class AbstractAuthStrategy implements AuthStrategyInterface
{
    /**
     * 配置管理器
     */
    protected ConfigInterface $config;

    /**
     * HTTP客户端
     */
    protected HttpClientInterface $httpClient;

    /**
     * 缓存管理器
     */
    protected CacheInterface $cache;

    /**
     * 日志记录器
     */
    protected ?LoggerInterface $logger;

    /**
     * 应用Key
     */
    protected string $appKey;

    /**
     * 应用Secret
     */
    protected string $appSecret;

    /**
     * 当前访问令牌
     */
    protected ?string $accessToken = null;

    /**
     * 令牌过期时间
     */
    protected ?int $tokenExpireTime = null;

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

        // 从配置中获取应用凭证
        $this->appKey = $config->get('app_key', '');
        $this->appSecret = $config->get('app_secret', '');
    }

    /**
     * 获取访问令牌
     */
    public function getAccessToken(bool $refresh = false): string
    {
        if ($refresh || !$this->isTokenValid()) {
            return $this->refreshAccessToken();
        }

        // 尝试从缓存获取
        $cacheKey = $this->getTokenCacheKey();
        $cachedData = $this->cache->get($cacheKey);

        if ($cachedData && is_array($cachedData)) {
            $this->accessToken = $cachedData['token'] ?? null;
            $this->tokenExpireTime = $cachedData['expire_time'] ?? null;

            if ($this->isTokenValid($this->accessToken)) {
                return $this->accessToken;
            }
        }

        return $this->refreshAccessToken();
    }

    /**
     * 检查访问令牌是否有效
     */
    public function isTokenValid(?string $token = null): bool
    {
        $token = $token ?? $this->accessToken;

        if (empty($token)) {
            return false;
        }

        // 检查令牌格式
        if (strlen($token) < 10) {
            return false;
        }

        // 检查过期时间
        if ($this->tokenExpireTime && $this->tokenExpireTime <= time() + 300) {
            return false; // 提前5分钟刷新
        }

        return true;
    }

    /**
     * 验证签名
     */
    public function verifySignature(array $data, string $signature): bool
    {
        $expectedSignature = $this->generateSignature($data);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * 生成签名
     */
    public function generateSignature(array $data): string
    {
        // 排序参数
        ksort($data);
        
        // 构建签名字符串
        $signString = '';
        foreach ($data as $key => $value) {
            if ($key !== 'signature' && $value !== '') {
                $signString .= $key . '=' . $value . '&';
            }
        }
        
        // 添加密钥
        $signString .= 'secret=' . $this->appSecret;
        
        return strtoupper(md5($signString));
    }

    /**
     * 设置应用凭证
     */
    public function setCredentials(string $appKey, string $appSecret): void
    {
        $this->appKey = $appKey;
        $this->appSecret = $appSecret;
    }

    /**
     * 获取应用凭证
     */
    public function getCredentials(): array
    {
        return [
            'app_key' => $this->appKey,
            'app_secret' => $this->maskSecret($this->appSecret),
        ];
    }

    /**
     * 获取令牌缓存键
     */
    protected function getTokenCacheKey(): string
    {
        return sprintf('dingtalk:token:%s:%s', $this->getStrategyType(), $this->appKey);
    }

    /**
     * 缓存令牌
     */
    protected function cacheToken(string $token, int $expiresIn): void
    {
        $cacheKey = $this->getTokenCacheKey();
        $expireTime = time() + $expiresIn;
        
        $this->cache->set($cacheKey, [
            'token' => $token,
            'expire_time' => $expireTime,
        ], $expiresIn);

        $this->accessToken = $token;
        $this->tokenExpireTime = $expireTime;
    }

    /**
     * 清除令牌缓存
     */
    protected function clearTokenCache(): void
    {
        $cacheKey = $this->getTokenCacheKey();
        $this->cache->delete($cacheKey);
        
        $this->accessToken = null;
        $this->tokenExpireTime = null;
    }

    /**
     * 记录日志
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->log($level, $message, $context);
        }
    }

    /**
     * 脱敏处理密钥
     */
    protected function maskSecret(string $secret): string
    {
        if (strlen($secret) <= 8) {
            return str_repeat('*', strlen($secret));
        }
        
        return substr($secret, 0, 4) . str_repeat('*', strlen($secret) - 8) . substr($secret, -4);
    }

    /**
     * 发送HTTP请求
     */
    protected function sendRequest(string $method, string $url, array $data = [], array $headers = []): array
    {
        try {
            $response = $this->httpClient->{strtolower($method)}($url, $data, $headers);
            
            if (!is_array($response)) {
                $response = json_decode($response, true);
            }

            return $response ?: [];
        } catch (\Exception $e) {
            $this->log('error', 'HTTP request failed', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            
            throw new AuthException('HTTP request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 获取API基础URL
     */
    protected function getApiBaseUrl(): string
    {
        return $this->config->get('api_base_url', 'https://oapi.dingtalk.com');
    }

    // 抽象方法，由具体策略实现
    abstract public function refreshAccessToken(): string;
    abstract public function getAuthUrl(string $redirectUri, string $state = '', array $scopes = []): string;
    abstract public function getUserByCode(string $code): array;
    abstract public function getJsApiSignature(string $url, array $jsApiList = []): array;
    abstract public function getStrategyType(): string;
    abstract public function getSupportedAuthMethods(): array;
    abstract public function handleSsoAuth(string $authCode): array;
    abstract public function getOAuth2Config(): array;
}