<?php

declare(strict_types=1);

namespace DingTalk\Auth;

use DingTalk\Contracts\AuthInterface;
use DingTalk\Contracts\ConfigInterface;
use DingTalk\Contracts\HttpClientInterface;
use DingTalk\Contracts\CacheInterface;
use DingTalk\Exceptions\AuthException;
use Psr\Log\LoggerInterface;

/**
 * 认证管理器
 * 
 * 负责处理钉钉API的认证授权，实现六项核心功能：
 * 1. Access Token获取和管理
 * 2. Token自动刷新机制
 * 3. Token缓存策略
 * 4. 多应用Token隔离
 * 5. Token过期检测
 * 6. 认证失败重试
 */
class AuthManager implements AuthInterface
{
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
     * 访问令牌缓存键前缀
     */
    private const ACCESS_TOKEN_CACHE_KEY = 'dingtalk_access_token';

    /**
     * JSAPI票据缓存键前缀
     */
    private const JSAPI_TICKET_CACHE_KEY = 'dingtalk_jsapi_ticket';

    /**
     * Token过期时间缓存键前缀
     */
    private const TOKEN_EXPIRE_TIME_KEY = 'dingtalk_token_expire_time';

    /**
     * 默认重试次数
     */
    private const DEFAULT_RETRY_TIMES = 3;

    /**
     * 默认重试间隔（秒）
     */
    private const DEFAULT_RETRY_INTERVAL = 1;

    /**
     * Token提前刷新时间（秒）
     */
    private const TOKEN_REFRESH_BUFFER = 300; // 5分钟

    /**
     * 重试统计
     */
    private array $retryStats = [];

    /**
     * Token管理统计
     */
    private array $tokenStats = [
        'total_requests' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'refresh_count' => 0,
        'retry_count' => 0,
        'failed_count' => 0
    ];

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
     * {@inheritdoc}
     * 
     * 核心功能1: Access Token获取和管理
     * 核心功能3: Token缓存策略
     * 核心功能4: 多应用Token隔离
     */
    public function getAccessToken(bool $refresh = false): string
    {
        $this->tokenStats['total_requests']++;
        $cacheKey = $this->getAccessTokenCacheKey();
        
        // 检查缓存中的Token
        if (!$refresh && $this->cache->has($cacheKey)) {
            $tokenData = $this->cache->get($cacheKey);
            if ($this->isTokenValid($tokenData['token'], $tokenData['expire_time'] ?? null)) {
                $this->tokenStats['cache_hits']++;
                $this->logInfo('Access token retrieved from cache', [
                    'app_key' => $this->getMaskedAppKey(),
                    'cache_key' => $cacheKey
                ]);
                return $tokenData['token'];
            }
        }

        $this->tokenStats['cache_misses']++;
        return $this->refreshAccessToken();
    }

    /**
     * {@inheritdoc}
     * 
     * 核心功能2: Token自动刷新机制
     * 核心功能6: 认证失败重试
     */
    public function refreshAccessToken(): string
    {
        $this->tokenStats['refresh_count']++;
        $retryTimes = $this->config->get('auth.retry_times', self::DEFAULT_RETRY_TIMES);
        $retryInterval = $this->config->get('auth.retry_interval', self::DEFAULT_RETRY_INTERVAL);
        
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $retryTimes; $attempt++) {
            try {
                $this->logInfo('Attempting to refresh access token', [
                    'attempt' => $attempt,
                    'max_attempts' => $retryTimes,
                    'app_key' => $this->getMaskedAppKey()
                ]);

                $apiVersion = $this->config->get('api_version', 'v1');
                
                if ($apiVersion === 'v2') {
                    $token = $this->getV2AccessToken();
                } else {
                    $token = $this->getV1AccessToken();
                }

                $this->logInfo('Access token refreshed successfully', [
                    'attempt' => $attempt,
                    'api_version' => $apiVersion,
                    'app_key' => $this->getMaskedAppKey()
                ]);

                return $token;

            } catch (\Exception $e) {
                $lastException = $e;
                $this->tokenStats['retry_count']++;
                
                $this->logError('Failed to refresh access token', [
                    'attempt' => $attempt,
                    'max_attempts' => $retryTimes,
                    'error' => $e->getMessage(),
                    'app_key' => $this->getMaskedAppKey()
                ]);

                if ($attempt < $retryTimes) {
                    $this->logInfo('Retrying after interval', [
                        'retry_interval' => $retryInterval,
                        'next_attempt' => $attempt + 1
                    ]);
                    sleep($retryInterval);
                }
            }
        }

        $this->tokenStats['failed_count']++;
        $this->logError('All retry attempts failed for access token refresh', [
            'total_attempts' => $retryTimes,
            'app_key' => $this->getMaskedAppKey()
        ]);

        throw new AuthException(
            'Failed to refresh access token after ' . $retryTimes . ' attempts',
            'ACCESS_TOKEN_REFRESH_FAILED',
            ['last_error' => $lastException->getMessage()]
        );
    }

    /**
     * {@inheritdoc}
     * 
     * 核心功能5: Token过期检测
     */
    public function isTokenValid(?string $token = null, ?int $expireTime = null): bool
    {
        if ($token === null) {
            $cacheKey = $this->getAccessTokenCacheKey();
            $tokenData = $this->cache->get($cacheKey);
            if (!$tokenData) {
                return false;
            }
            $token = $tokenData['token'] ?? null;
            $expireTime = $tokenData['expire_time'] ?? null;
        }
        
        if (empty($token)) {
            return false;
        }

        // 检查Token格式（基本验证）
        if (strlen($token) < 10) {
            $this->logWarning('Token format validation failed', [
                'token_length' => strlen($token)
            ]);
            return false;
        }

        // 检查过期时间
        if ($expireTime !== null) {
            $currentTime = time();
            $bufferTime = $this->config->get('auth.refresh_buffer', self::TOKEN_REFRESH_BUFFER);
            
            if ($currentTime >= ($expireTime - $bufferTime)) {
                $this->logInfo('Token will expire soon, marking as invalid', [
                    'current_time' => $currentTime,
                    'expire_time' => $expireTime,
                    'buffer_time' => $bufferTime,
                    'remaining_seconds' => $expireTime - $currentTime
                ]);
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthUrl(string $redirectUri, string $state = '', array $scopes = []): string
    {
        $params = [
            'appid' => $this->config->get('app_key'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(',', $scopes ?: ['snsapi_login']),
            'state' => $state ?: uniqid(),
        ];

        $baseUrl = 'https://oapi.dingtalk.com/connect/oauth2/sns_authorize';
        
        $this->logInfo('Generated auth URL', [
            'redirect_uri' => $redirectUri,
            'scopes' => $scopes,
            'state' => $state
        ]);
        
        return $baseUrl . '?' . http_build_query($params);
    }

    /**
     * {@inheritdoc}
     */
    public function getUserByCode(string $code): array
    {
        $apiVersion = $this->config->get('api_version', 'v1');
        
        try {
            if ($apiVersion === 'v2') {
                $result = $this->getV2UserByCode($code);
            } else {
                $result = $this->getV1UserByCode($code);
            }

            $this->logInfo('User info retrieved by code', [
                'api_version' => $apiVersion,
                'has_user_data' => !empty($result)
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logError('Failed to get user by code', [
                'api_version' => $apiVersion,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getJsApiSignature(string $url, array $jsApiList = []): array
    {
        try {
            $ticket = $this->getJsApiTicket();
            $nonceStr = uniqid();
            $timestamp = time();

            $string = "jsapi_ticket={$ticket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
            $signature = sha1($string);

            $result = [
                'agentId' => $this->config->get('agent_id'),
                'corpId' => $this->config->get('corp_id'),
                'timeStamp' => $timestamp,
                'nonceStr' => $nonceStr,
                'signature' => $signature,
                'jsApiList' => $jsApiList,
            ];

            $this->logInfo('JSAPI signature generated', [
                'url' => $url,
                'jsapi_count' => count($jsApiList)
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logError('Failed to generate JSAPI signature', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function verifySignature(array $data, string $signature): bool
    {
        $generatedSignature = $this->generateSignature($data);
        $isValid = hash_equals($generatedSignature, $signature);
        
        $this->logInfo('Signature verification', [
            'is_valid' => $isValid,
            'data_keys' => array_keys($data)
        ]);
        
        return $isValid;
    }

    /**
     * {@inheritdoc}
     */
    public function generateSignature(array $data): string
    {
        ksort($data);
        $string = '';
        
        foreach ($data as $key => $value) {
            if ($value !== '' && $key !== 'signature') {
                $string .= $key . '=' . $value . '&';
            }
        }
        
        $string = rtrim($string, '&');
        
        return sha1($string);
    }

    /**
     * {@inheritdoc}
     */
    public function setCredentials(string $appKey, string $appSecret): void
    {
        $this->config->set('app_key', $appKey);
        $this->config->set('app_secret', $appSecret);
        
        $this->logInfo('Credentials updated', [
            'app_key' => $this->maskAppKey($appKey)
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getCredentials(): array
    {
        return [
            'app_key' => $this->config->get('app_key'),
            'app_secret' => $this->config->get('app_secret'),
            'corp_id' => $this->config->get('corp_id'),
            'agent_id' => $this->config->get('agent_id'),
        ];
    }

    /**
     * 获取Token管理统计信息
     */
    public function getTokenStats(): array
    {
        return $this->tokenStats;
    }

    /**
     * 获取重试统计信息
     */
    public function getRetryStats(): array
    {
        return $this->retryStats;
    }

    /**
     * 清除指定应用的Token缓存
     * 
     * 核心功能4: 多应用Token隔离
     */
    public function clearTokenCache(?string $appKey = null): bool
    {
        if ($appKey) {
            $cacheKey = $this->getAccessTokenCacheKey($appKey);
        } else {
            $cacheKey = $this->getAccessTokenCacheKey();
        }
        
        $result = $this->cache->delete($cacheKey);
        
        $this->logInfo('Token cache cleared', [
            'app_key' => $appKey ? $this->maskAppKey($appKey) : $this->getMaskedAppKey(),
            'cache_key' => $cacheKey,
            'success' => $result
        ]);
        
        return $result;
    }

    /**
     * 检查Token是否即将过期
     * 
     * 核心功能5: Token过期检测
     */
    public function isTokenExpiringSoon(?string $appKey = null, int $bufferSeconds = null): bool
    {
        $cacheKey = $this->getAccessTokenCacheKey($appKey);
        $tokenData = $this->cache->get($cacheKey);
        
        if (!$tokenData || !isset($tokenData['expire_time'])) {
            return true; // 没有Token或过期时间，认为需要刷新
        }
        
        $bufferTime = $bufferSeconds ?? $this->config->get('auth.refresh_buffer', self::TOKEN_REFRESH_BUFFER);
        $currentTime = time();
        $expireTime = $tokenData['expire_time'];
        
        return $currentTime >= ($expireTime - $bufferTime);
    }

    /**
     * 获取V1版本访问令牌
     */
    private function getV1AccessToken(): string
    {
        $url = $this->config->get('api_base_url', 'https://oapi.dingtalk.com') . '/gettoken';
        
        $response = $this->httpClient->get($url, [
            'appkey' => $this->config->get('app_key'),
            'appsecret' => $this->config->get('app_secret'),
        ]);

        if (!isset($response['access_token'])) {
            throw new AuthException(
                'Failed to get access token: ' . ($response['errmsg'] ?? 'Unknown error'),
                'ACCESS_TOKEN_FAILED',
                $response
            );
        }

        $token = $response['access_token'];
        $expiresIn = $response['expires_in'] ?? 7200;
        $expireTime = time() + $expiresIn;
        
        // 缓存令牌和过期时间
        $this->cacheTokenData($token, $expireTime, $expiresIn);

        return $token;
    }

    /**
     * 获取V2版本访问令牌
     */
    private function getV2AccessToken(): string
    {
        $url = $this->config->get('api_base_url', 'https://api.dingtalk.com') . '/v1.0/oauth2/accessToken';
        
        $response = $this->httpClient->post($url, [
            'appKey' => $this->config->get('app_key'),
            'appSecret' => $this->config->get('app_secret'),
        ]);

        if (!isset($response['accessToken'])) {
            throw new AuthException(
                'Failed to get access token: ' . ($response['message'] ?? 'Unknown error'),
                'ACCESS_TOKEN_FAILED',
                $response
            );
        }

        $token = $response['accessToken'];
        $expiresIn = $response['expireIn'] ?? 7200;
        $expireTime = time() + $expiresIn;
        
        // 缓存令牌和过期时间
        $this->cacheTokenData($token, $expireTime, $expiresIn);

        return $token;
    }

    /**
     * 缓存Token数据
     * 
     * 核心功能3: Token缓存策略
     */
    private function cacheTokenData(string $token, int $expireTime, int $expiresIn): void
    {
        $cacheKey = $this->getAccessTokenCacheKey();
        $bufferTime = $this->config->get('auth.refresh_buffer', self::TOKEN_REFRESH_BUFFER);
        
        $tokenData = [
            'token' => $token,
            'expire_time' => $expireTime,
            'created_at' => time()
        ];
        
        // 缓存时间比实际过期时间提前一些，确保自动刷新
        $cacheTtl = $expiresIn - $bufferTime;
        
        $this->cache->set($cacheKey, $tokenData, $cacheTtl);
        
        $this->logInfo('Token cached successfully', [
            'cache_key' => $cacheKey,
            'expire_time' => $expireTime,
            'cache_ttl' => $cacheTtl,
            'app_key' => $this->getMaskedAppKey()
        ]);
    }

    /**
     * 通过V1 API获取用户信息
     */
    private function getV1UserByCode(string $code): array
    {
        $accessToken = $this->getAccessToken();
        $url = $this->config->get('api_base_url', 'https://oapi.dingtalk.com') . '/user/getuserinfo';
        
        return $this->httpClient->get($url, [
            'access_token' => $accessToken,
            'code' => $code,
        ]);
    }

    /**
     * 通过V2 API获取用户信息
     */
    private function getV2UserByCode(string $code): array
    {
        $accessToken = $this->getAccessToken();
        $url = $this->config->get('api_base_url', 'https://api.dingtalk.com') . '/v1.0/oauth2/userAccessToken';
        
        $response = $this->httpClient->post($url, [
            'clientId' => $this->config->get('app_key'),
            'clientSecret' => $this->config->get('app_secret'),
            'code' => $code,
            'grantType' => 'authorization_code',
        ], [
            'x-acs-dingtalk-access-token' => $accessToken,
        ]);

        return $response;
    }

    /**
     * 获取JSAPI票据
     */
    private function getJsApiTicket(): string
    {
        $cacheKey = $this->getJsApiTicketCacheKey();
        
        if ($this->cache->has($cacheKey)) {
            $ticketData = $this->cache->get($cacheKey);
            if (isset($ticketData['ticket']) && $this->isTicketValid($ticketData)) {
                return $ticketData['ticket'];
            }
        }

        $accessToken = $this->getAccessToken();
        $url = $this->config->get('api_base_url', 'https://oapi.dingtalk.com') . '/get_jsapi_ticket';
        
        $response = $this->httpClient->get($url, [
            'access_token' => $accessToken,
        ]);

        if (!isset($response['ticket'])) {
            throw new AuthException(
                'Failed to get JSAPI ticket: ' . ($response['errmsg'] ?? 'Unknown error'),
                'JSAPI_TICKET_FAILED',
                $response
            );
        }

        $ticket = $response['ticket'];
        $expiresIn = $response['expires_in'] ?? 7200;
        $expireTime = time() + $expiresIn;
        
        // 缓存票据
        $ticketData = [
            'ticket' => $ticket,
            'expire_time' => $expireTime,
            'created_at' => time()
        ];
        
        $bufferTime = $this->config->get('auth.refresh_buffer', self::TOKEN_REFRESH_BUFFER);
        $this->cache->set($cacheKey, $ticketData, $expiresIn - $bufferTime);

        return $ticket;
    }

    /**
     * 检查JSAPI票据是否有效
     */
    private function isTicketValid(array $ticketData): bool
    {
        if (!isset($ticketData['expire_time'])) {
            return false;
        }
        
        $currentTime = time();
        $bufferTime = $this->config->get('auth.refresh_buffer', self::TOKEN_REFRESH_BUFFER);
        
        return $currentTime < ($ticketData['expire_time'] - $bufferTime);
    }

    /**
     * 获取访问令牌缓存键
     * 
     * 核心功能4: 多应用Token隔离
     */
    private function getAccessTokenCacheKey(?string $appKey = null): string
    {
        $appKey = $appKey ?: $this->config->get('app_key');
        return self::ACCESS_TOKEN_CACHE_KEY . '_' . md5($appKey);
    }

    /**
     * 获取JSAPI票据缓存键
     * 
     * 核心功能4: 多应用Token隔离
     */
    private function getJsApiTicketCacheKey(?string $appKey = null): string
    {
        $appKey = $appKey ?: $this->config->get('app_key');
        return self::JSAPI_TICKET_CACHE_KEY . '_' . md5($appKey);
    }

    /**
     * 获取脱敏的AppKey
     */
    private function getMaskedAppKey(): string
    {
        $appKey = $this->config->get('app_key', '');
        return $this->maskAppKey($appKey);
    }

    /**
     * 脱敏AppKey
     */
    private function maskAppKey(string $appKey): string
    {
        if (strlen($appKey) <= 8) {
            return str_repeat('*', strlen($appKey));
        }
        
        return substr($appKey, 0, 4) . str_repeat('*', strlen($appKey) - 8) . substr($appKey, -4);
    }

    /**
     * 记录信息日志
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * 记录警告日志
     */
    private function logWarning(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->warning($message, $context);
        }
    }

    /**
     * 记录错误日志
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
}